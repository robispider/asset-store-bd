<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Asset;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ActionFailure;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GovFulfillmentController extends Controller
{
    private function checkStorekeeperAccess()
    {
        abort_unless(app(GovAccess::class)->permitsRequest(auth()->user(), 'requests.fulfill'), 403);
    }

    public function index()
    {
        $this->checkStorekeeperAccess();
        $user = auth()->user();

        $query = ServiceRequest::with(['requester', 'items'])
            ->whereIn('approval_status', ['approved', 'partially_approved'])
            ->whereNotIn('fulfillment_status', ['closed', 'issued']);

        if (! $user->isSuperUser()) {
            $myLocationIds = [app(TenantContext::class)->locationId];
            $query->whereIn('office_id', $myLocationIds);
        }

        $activeRequests = $query->orderBy('approved_at', 'asc')->get();

        return view('govstore::fulfillment.index', compact('activeRequests'));
    }

    public function show($id)
    {
        $this->checkStorekeeperAccess();
        $user = auth()->user();

        $serviceRequest = ServiceRequest::with([
            'requester',
            'items.requested',
            'events.user',
        ])->findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        abort_unless($serviceRequest->office_id && in_array($serviceRequest->approval_status, ['approved', 'partially_approved'])
            && in_array($serviceRequest->fulfillment_status, ['unstarted', 'partially_issued']), 409);

        // Pre-load available, deployable assets for the Barcode Scanners
        $availableAssets = [];
        $myLocationIds = [$serviceRequest->office_id];

        foreach ($serviceRequest->items as $item) {
            $type = strtolower(class_basename($item->requested_type));

            if (in_array($type, ['assetmodel', 'asset_model']) && $item->line_approval_status === 'approved') {
                $query = Asset::with('location')
                    ->where('model_id', $item->fulfilled_id ?: $item->requested_id)
                    ->whereNull('assigned_to')->where('requestable', 1)->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0));

                if (! empty($myLocationIds)) {
                    $query->whereIn('location_id', $myLocationIds);
                }
                app(RequestInventory::class)->scopeCompany($query, 'assets.company_id');

                $availableAssets[$item->id] = $query->get();
            }
        }

        return view('govstore::fulfillment.show', compact('serviceRequest', 'availableAssets'));
    }

    public function process(Request $request, $id, FulfillmentService $service)
    {
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);

        $request->validate([
            'issue' => 'required|array',
            'substitutions' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $service->issueItems(
                $serviceRequest,
                auth()->user(),
                $request->input('issue'),
                $request->input('substitutions', []),
                $request->input('notes')
            );

            return redirect()->route('gov.requests.fulfillment.index')
                ->with('success', __('requestlabels::requests.govfulfillmentcontroller_flash_fulfillment'));
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return redirect()->back()->with('error', __('requestlabels::requests.govfulfillmentcontroller_flash_fulfillment_error', ['message' => app(ActionFailure::class)->message($e)]));
        }
    }

    public function close(Request $request, $id, FulfillmentService $service)
    {
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        $request->validate(['reason' => 'required|string|min:5|max:2000']);

        try {
            $service->forceClose($serviceRequest, auth()->user(), $request->input('reason'));

            return redirect()->route('gov.requests.fulfillment.index')
                ->with('success', __('requestlabels::requests.govfulfillmentcontroller_flash_closed', ['number' => $serviceRequest->request_number]));
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return redirect()->back()->with('error', app(ActionFailure::class)->message($e));
        }
    }
}
