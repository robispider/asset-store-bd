<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Category;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ActionFailure;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GovApprovalController extends Controller
{
    private function checkApproverAccess()
    {
        abort_unless(app(GovAccess::class)->permitsRequest(auth()->user(), 'requests.approve'), 403);
    }

    private function checkSystemAdminAccess()
    {
        abort_unless(app(GovAccess::class)->decide(auth()->user(), 'requests.configure')->allowed, 403);
    }

    public function index()
    {
        $this->checkApproverAccess();
        $user = auth()->user();

        $pendingQuery = ServiceRequest::with(['requester', 'items'])->whereIn('approval_status', ['pending_primary', 'pending_final']);
        $processedQuery = ServiceRequest::with(['requester'])->whereNotIn('approval_status', ['draft', 'submitted', 'under_review', 'pending_primary', 'pending_final']);

        if (! $user->isSuperUser()) {
            // Use the working office, including responsibilities granted as cover.
            $myLocationIds = [app(TenantContext::class)->locationId];

            $pendingQuery->whereIn('office_id', $myLocationIds);
            $processedQuery->whereIn('office_id', $myLocationIds)
                ->where(fn ($q) => $q->where('decided_by', $user->id)->orWhere('primary_decided_by', $user->id));
        }

        $access = app(GovAccess::class);
        $pendingQuery->where('requested_by', '!=', $user->id)->whereNotNull('office_id')
            ->where(function ($q) use ($user, $access) {
                $q->whereRaw('1 = 0');
                if ($access->decide($user, 'requests.approve.primary')->allowed) {
                    $q->orWhere('approval_status', 'pending_primary');
                }
                if ($access->decide($user, 'requests.approve.final')->allowed) {
                    $q->orWhere(fn ($final) => $final->where('approval_status', 'pending_final')
                        ->where(fn ($actor) => $actor->whereNull('primary_decided_by')->orWhere('primary_decided_by', '!=', $user->id)));
                }
            });

        $pendingRequests = $pendingQuery->orderBy('created_at', 'desc')->get();
        $processedRequests = $processedQuery->orderBy('updated_at', 'desc')->limit(10)->get();

        return view('govstore::admin.index', compact('pendingRequests', 'processedRequests'));
    }

    public function show($id)
    {
        $this->checkApproverAccess();
        $serviceRequest = ServiceRequest::with(['requester', 'items.requested', 'events.user'])->findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        $canDecide = $serviceRequest->office_id && in_array($serviceRequest->approval_status, ['pending_primary', 'pending_final'])
            && (int) $serviceRequest->requested_by !== (int) auth()->id()
            && ($serviceRequest->approval_status !== 'pending_final' || (int) $serviceRequest->primary_decided_by !== (int) auth()->id())
            && app(GovAccess::class)->decide(auth()->user(), $serviceRequest->approval_status === 'pending_primary' ? 'requests.approve.primary' : 'requests.approve.final')->allowed;

        return view('govstore::admin.show', compact('serviceRequest', 'canDecide'));
    }

    public function process(Request $request, $id, ApprovalService $service)
    {
        $this->checkApproverAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        $request->validate(['items' => 'required|array']);
        try {
            $service->processDecision($serviceRequest, auth()->user(), $request->input('items', []));

            return redirect()->route('gov.requests.admin.index')->with('success', __('requestlabels::requests.govapprovalcontroller_flash_processed', ['number' => $serviceRequest->request_number]));
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }

            return redirect()->back()->with('error', __('requestlabels::requests.govapprovalcontroller_flash_workflow_error', ['message' => app(ActionFailure::class)->message($e)]));
        }
    }

    // NOTE: We deleted locationsIndex and locationsStore because Office Setup is now handled in the Hub!

    public function policiesIndex()
    {
        $this->checkSystemAdminAccess();
        $categories = Category::orderBy('name')->get();
        $policies = ApprovalPolicy::where('target_type', 'category')->get()->keyBy('target_id');

        return view('govstore::admin.policies', compact('categories', 'policies'));
    }

    public function policiesStore(Request $request)
    {
        $this->checkSystemAdminAccess();
        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'target_type' => 'required_without:category_id|in:category,asset_model,accessory,consumable',
            'target_id' => 'required_without:category_id|integer|min:1',
            'policy_name' => 'required|in:AUTO_APPROVE,PRIMARY_ONLY,PRIMARY_AND_FINAL',
            'threshold_qty' => 'nullable|integer|min:1|max:10000',
            'threshold_value' => 'nullable|numeric|min:0.01|max:9999999999999',
        ]);
        DB::transaction(function () use ($data) {
            $type = ! empty($data['category_id']) ? 'category' : $data['target_type'];
            $id = ! empty($data['category_id']) ? $data['category_id'] : $data['target_id'];
            if ($type === 'category') {
                Category::whereKey($id)->lockForUpdate()->firstOrFail();
            } else {
                app(RequestInventory::class)->validateItem($type, $id, true);
            }
            ApprovalPolicy::updateOrCreate(['target_type' => $type, 'target_id' => $id], [
                'policy_name' => $data['policy_name'], 'threshold_qty' => $data['threshold_qty'] ?? null,
                'threshold_value' => $data['threshold_value'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', __('requestlabels::requests.govapprovalcontroller_flash_policy_updated'));
    }
}
