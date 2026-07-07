<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\LocationRole;
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
        $user = auth()->user();

        // 1. Base query for active fulfillment requests
        $query = ServiceRequest::with(['requester', 'items'])
                    ->whereIn('approval_status', ['approved', 'partially_approved'])
                    ->whereNotIn('fulfillment_status', ['closed', 'issued']);

        // 2. ZERO-TOUCH LOCATION FILTER: 
        // If not a Superuser, restrict queue strictly to the locations where this user is the Storekeeper
        if (!$user->isSuperUser()) {
            $myLocationIds = LocationRole::where('storekeeper_id', $user->id)->pluck('location_id');
            $query->whereIn('delivery_location_id', $myLocationIds);
        }

        $activeRequests = $query->orderBy('approved_at', 'asc')->get();

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