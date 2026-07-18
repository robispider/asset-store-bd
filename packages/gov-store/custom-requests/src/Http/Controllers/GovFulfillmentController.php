<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\LocationRole;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\OfficeMembership\Models\OfficeResponsibility;

class GovFulfillmentController extends Controller
{
   private function checkStorekeeperAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) return;

        // MATRIX LOOKUP
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