<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\FulfillmentService;

class GovFulfillmentController extends Controller
{
    private function checkStorekeeperAccess()
    {
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized access to fulfillment logs.');
        }
    }

    public function index()
    {
        $this->checkStorekeeperAccess();

        // Get requests that are approved or partially approved, and not yet closed
        $activeRequests = ServiceRequest::with(['requester', 'items'])
                            ->whereIn('approval_status', ['approved', 'partially_approved'])
                            ->whereNotIn('fulfillment_status', ['closed', 'issued'])
                            ->orderBy('approved_at', 'asc')
                            ->get();

        return view('govstore::fulfillment.index', compact('activeRequests'));
    }

    public function show($id)
    {
        $this->checkStorekeeperAccess();

        $serviceRequest = ServiceRequest::with([
            'requester', 
            'items.requested', 
            'events.user'
        ])->findOrFail($id);

        return view('govstore::fulfillment.show', compact('serviceRequest'));
    }

    public function process(Request $request, $id, FulfillmentService $service)
    {
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);

        $request->validate([
            'issue' => 'required|array'
        ]);

        try {
            $service->issueItems($serviceRequest, auth()->user(), $request->input('issue'));
            return redirect()->route('gov.requests.fulfillment.index')
                             ->with('success', "Fulfillment logged. Snipe-IT inventory updated.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Fulfillment error: ' . $e->getMessage());
        }
    }

    public function close(Request $request, $id, FulfillmentService $service)
    {
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);

        try {
            $service->forceClose($serviceRequest, auth()->user(), $request->input('reason'));
            return redirect()->route('gov.requests.fulfillment.index')
                             ->with('success', "Service Request {$serviceRequest->request_number} closed permanently.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}