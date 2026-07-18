<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Services\ApprovalService;
use App\Models\Category;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\OfficeMembership\Models\OfficeResponsibility; // IMPORT PIVOT

class GovApprovalController extends Controller
{
    private function checkApproverAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) return;

        // MATRIX LOOKUP
        $isApprover = OfficeResponsibility::where('user_id', $user->id)
            ->whereIn('role_slug', ['primary_approver', 'final_approver'])
            ->exists();

        if (!$isApprover) abort(403, __('requestlabels::requests.govapprovalcontroller_abort_unauthorized'));
    }

    private function checkSystemAdminAccess()
    {
        $user = auth()->user();
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            abort(403, __('requestlabels::requests.govapprovalcontroller_abort_admin_required'));
        }
    }

    public function index()
    {
        $this->checkApproverAccess();
        $user = auth()->user();

        $pendingQuery = ServiceRequest::with(['requester', 'items'])->whereIn('approval_status', ['submitted', 'under_review', 'pending_primary', 'pending_final']);
        $processedQuery = ServiceRequest::with(['requester'])->whereNotIn('approval_status', ['draft', 'submitted', 'under_review', 'pending_primary', 'pending_final']);

        if (!$user->isSuperUser()) {
            // SHARED QUEUE: Fetch all locations where this user is an approver
            $myLocationIds = OfficeResponsibility::where('user_id', $user->id)
                ->whereIn('role_slug', ['primary_approver', 'final_approver'])
                ->pluck('location_id');

            $pendingQuery->whereIn('delivery_location_id', $myLocationIds);
            $processedQuery->where('approved_by', $user->id); // Keep history to what they personally processed
        }

        $pendingRequests = $pendingQuery->orderBy('created_at', 'desc')->get();
        $processedRequests = $processedQuery->orderBy('updated_at', 'desc')->limit(10)->get();

        return view('govstore::admin.index', compact('pendingRequests', 'processedRequests'));
    }

    public function show($id)
    {
        $this->checkApproverAccess();
        $serviceRequest = ServiceRequest::with(['requester', 'items.requested', 'events.user'])->findOrFail($id);

        if ($serviceRequest->approval_status === 'submitted') {
            $serviceRequest->update(['approval_status' => 'under_review']);
            RequestEvent::create(['request_id' => $serviceRequest->id, 'user_id' => auth()->id(), 'event_type' => 'under_review', 'details' => ['message' => 'Review initiated']]);
        }

        return view('govstore::admin.show', compact('serviceRequest'));
    }

    public function process(Request $request, $id, ApprovalService $service)
    {
        $this->checkApproverAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);
        try {
            $service->processDecision($serviceRequest, auth()->user(), $request->input('items', []));
            return redirect()->route('gov.requests.admin.index')->with('success', __('requestlabels::requests.govapprovalcontroller_flash_processed', ['number' => $serviceRequest->request_number]));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('requestlabels::requests.govapprovalcontroller_flash_workflow_error', ['message' => $e->getMessage()]));
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
        $request->validate(['category_id' => 'required|integer', 'policy_name' => 'required|string']);
        ApprovalPolicy::updateOrCreate(['target_type' => 'category', 'target_id' => $request->category_id], ['policy_name' => $request->policy_name]);
        return redirect()->back()->with('success', __('requestlabels::requests.govapprovalcontroller_flash_policy_updated'));
    }
}