<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Services\ApprovalService;

use App\Models\Location;
use App\Models\Category;
use App\Models\User;
use GovStore\Organization\Models\LocationRole; // Correct namespace mapped to organization package
use GovStore\CustomRequests\Models\ApprovalPolicy;

class GovApprovalController extends Controller
{
    /**
     * Scoped Check: For local line-item approvals (permits delegated Primary/Final Approvers and Admins)
     */
    private function checkApproverAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) {
            return;
        }

        // Checks if they are assigned as primary or final approver inside the LocationRole table
        $isApprover = LocationRole::where('primary_approver_id', $user->id)
            ->orWhere('final_approver_id', $user->id)
            ->exists();

        if (!$isApprover) {
            abort(403, 'Unauthorized access to approval workflows.');
        }
    }

    /**
     * Strict Check: For global administrative settings (strictly limited to system administrators)
     */
    private function checkSystemAdminAccess()
    {
        $user = auth()->user();
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            abort(403, 'Unauthorized. Policy configuration requires system administrator privileges.');
        }
    }

    public function index()
    {
        $this->checkApproverAccess();
        $user = auth()->user();

        // 1. Prepare base query for pending requests
        $pendingQuery = ServiceRequest::with(['requester', 'items'])
                            ->whereIn('approval_status', ['submitted', 'under_review', 'pending_primary', 'pending_final']);

        // 2. Prepare base query for recently processed requests
        $processedQuery = ServiceRequest::with(['requester'])
                            ->whereNotIn('approval_status', ['draft', 'submitted', 'under_review', 'pending_primary', 'pending_final']);

        // 3. ZERO-TOUCH SECURITY FILTER: 
        // If not a Superuser, restrict views strictly to items assigned to them
        if (!$user->isSuperUser()) {
            $pendingQuery->where('assigned_approver_id', $user->id);
            $processedQuery->where('approved_by', $user->id);
        }

        $pendingRequests = $pendingQuery->orderBy('created_at', 'desc')->get();
        $processedRequests = $processedQuery->orderBy('updated_at', 'desc')->limit(10)->get();

        return view('govstore::admin.index', compact('pendingRequests', 'processedRequests'));
    }

    public function show($id)
    {
        $this->checkApproverAccess();

        $serviceRequest = ServiceRequest::with([
            'requester', 
            'items.requested', 
            'events.user'
        ])->findOrFail($id);

        // If the request was just opened, transition status to 'under_review' and write to timeline
        if ($serviceRequest->approval_status === 'submitted') {
            $serviceRequest->update(['approval_status' => 'under_review']);
            
            RequestEvent::create([
                'request_id' => $serviceRequest->id,
                'user_id' => auth()->id(),
                'event_type' => 'under_review',
                'details' => ['message' => 'Review initiated by administrator']
            ]);
        }

        return view('govstore::admin.show', compact('serviceRequest'));
    }

    public function process(Request $request, $id, ApprovalService $service)
    {
        $this->checkApproverAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);

        try {
            // Pass the itemised decision inputs to our transactional service
            $service->processDecision($serviceRequest, auth()->user(), $request->input('items', []));
            
            return redirect()->route('gov.requests.admin.index')
                             ->with('success', "Service Request {$serviceRequest->request_number} has been processed.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Workflow error: ' . $e->getMessage());
        }
    }

    public function locationsIndex()
    {
        $this->checkSystemAdminAccess();

        $locations = Location::orderBy('name')->get();
        // Load existing roles mapped to location IDs
        $locationRoles = LocationRole::all()->keyBy('location_id');
        $users = User::orderBy('first_name')->get();

        return view('govstore::admin.locations', compact('locations', 'locationRoles', 'users'));
    }

    public function locationsStore(Request $request)
    {
        $this->checkSystemAdminAccess();

        $request->validate([
            'location_id' => 'required|integer',
            'primary_approver_id' => 'required|integer',
            'final_approver_id' => 'nullable|integer',
            'storekeeper_id' => 'required|integer',
        ]);

        LocationRole::updateOrCreate(
            ['location_id' => $request->location_id],
            [
                'primary_approver_id' => $request->primary_approver_id,
                'final_approver_id' => $request->final_approver_id ?: null,
                'storekeeper_id' => $request->storekeeper_id,
            ]
        );

        return redirect()->back()->with('success', 'Office roles updated successfully.');
    }

    public function policiesIndex()
    {
        $this->checkSystemAdminAccess();

        $categories = Category::orderBy('name')->get();
        // Fetch existing category policies
        $policies = ApprovalPolicy::where('target_type', 'category')->get()->keyBy('target_id');

        return view('govstore::admin.policies', compact('categories', 'policies'));
    }

    public function policiesStore(Request $request)
    {
        $this->checkSystemAdminAccess();

        $request->validate([
            'category_id' => 'required|integer',
            'policy_name' => 'required|string',
        ]);

        ApprovalPolicy::updateOrCreate(
            [
                'target_type' => 'category',
                'target_id' => $request->category_id
            ],
            [
                'policy_name' => $request->policy_name
            ]
        );

        return redirect()->back()->with('success', 'Category approval policy updated successfully.');
    }
}