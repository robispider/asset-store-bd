<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use App\Models\Asset;

class GovFulfillmentController extends Controller
{
    private function checkStorekeeperAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) return;

        $isStorekeeper = OfficeResponsibility::where('user_id', $user->id)->where('role_slug', 'storekeeper')->exists();
        if (!$isStorekeeper) abort(403, __('requestlabels::requests.govfulfillmentcontroller_abort_unauthorized'));
    }

    public function index()
    {
        $this->checkStorekeeperAccess();
        $user = auth()->user();

        $query = ServiceRequest::with(['requester', 'items'])
                    ->whereIn('approval_status', ['approved', 'partially_approved'])
                    ->whereNotIn('fulfillment_status', ['closed', 'issued']);

        if (!$user->isSuperUser()) {
            $myLocationIds = OfficeResponsibility::where('user_id', $user->id)->where('role_slug', 'storekeeper')->pluck('location_id');
            $query->whereIn('delivery_location_id', $myLocationIds);
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
            'events.user'
        ])->findOrFail($id);

        // Pre-load available, deployable assets for the Barcode Scanners
        $availableAssets = [];
        $myLocationIds = $user->isSuperUser() ? [] : OfficeResponsibility::where('user_id', $user->id)->where('role_slug', 'storekeeper')->pluck('location_id')->toArray();

        foreach ($serviceRequest->items as $item) {
            $type = strtolower(class_basename($item->requested_type));
            
            if (in_array($type, ['assetmodel', 'asset_model']) && $item->line_approval_status === 'approved') {
                $query = Asset::with('location')
                    ->where('model_id', $item->requested_id)
                    ->whereNull('assigned_to');

                if (!empty($myLocationIds)) {
                    $query->whereIn('location_id', $myLocationIds);
                }

                $availableAssets[$item->id] = $query->get();
            }
        }

        return view('govstore::fulfillment.show', compact('serviceRequest', 'availableAssets'));
    }

    public function process(Request $request, $id, FulfillmentService $service)
    {
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);

        $request->validate([
            'issue' => 'required|array',
            'substitutions' => 'nullable|array'
        ]);

        try {
            $service->issueItems(
                $serviceRequest, 
                auth()->user(), 
                $request->input('issue'),
                $request->input('substitutions', [])
            );
            return redirect()->route('gov.requests.fulfillment.index')
                             ->with('success', __('requestlabels::requests.govfulfillmentcontroller_flash_fulfillment'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('requestlabels::requests.govfulfillmentcontroller_flash_fulfillment_error', ['message' => $e->getMessage()]));
        }
    }

    public function close(Request $request, $id, FulfillmentService $service)
    {
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);

        try {
            $service->forceClose($serviceRequest, auth()->user(), $request->input('reason'));
            return redirect()->route('gov.requests.fulfillment.index')
                             ->with('success', __('requestlabels::requests.govfulfillmentcontroller_flash_closed', ['number' => $serviceRequest->request_number]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}