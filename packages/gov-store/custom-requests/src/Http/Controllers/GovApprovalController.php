<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Services\ApprovalService;

class GovApprovalController extends Controller
{
    private function checkAdminAccess()
    {
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized access to approval workflows.');
        }
    }

    public function index()
    {
        $this->checkAdminAccess();

        // 1. Fetch requests waiting for decision
        $pendingRequests = ServiceRequest::with(['requester', 'items'])
                            ->whereIn('approval_status', ['submitted', 'under_review'])
                            ->orderBy('created_at', 'desc')
                            ->get();

        // 2. Fetch recently processed requests for audit history reference
        $processedRequests = ServiceRequest::with(['requester'])
                            ->whereNotIn('approval_status', ['draft', 'submitted', 'under_review'])
                            ->orderBy('updated_at', 'desc')
                            ->limit(10)
                            ->get();

        return view('govstore::admin.index', compact('pendingRequests', 'processedRequests'));
    }

    public function show($id)
    {
        $this->checkAdminAccess();

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
        $this->checkAdminAccess();
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
}