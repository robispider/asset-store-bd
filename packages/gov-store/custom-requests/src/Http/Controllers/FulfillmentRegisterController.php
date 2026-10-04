<?php

namespace GovStore\CustomRequests\Http\Controllers;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Routing\Controller;

class FulfillmentRegisterController extends Controller
{
    private function checkAccess()
    {
        abort_unless(app(GovAccess::class)->permitsRequest(auth()->user(), 'storeops.documents.view'), 403);
    }

    /**
     * Display all historically completed/fulfilled requests for the user's active location.
     */
    public function index()
    {
        $this->checkAccess();
        $user = auth()->user();

        // Eager load items to prevent N+1 queries when calculating total lines on the index view
        $query = ServiceRequest::with(['requester', 'approvedBy', 'items'])
            ->whereIn('fulfillment_status', ['issued', 'partially_issued', 'closed', 'cannot_fulfill'])
            ->orderBy('closed_at', 'desc');

        // Non-superusers only see records for their active office locations
        if (! $user->isSuperUser()) {
            $myLocationIds = [app(TenantContext::class)->locationId];
            $query->whereIn('delivery_location_id', $myLocationIds);
        }

        $completedRequests = $query->get();

        return view('govstore::fulfillment-register.index', compact('completedRequests'));
    }

    /**
     * Show details of a specific fulfilled request and its associated Goods Issue documents.
     */
    public function show($id)
    {
        $this->checkAccess();

        $serviceRequest = ServiceRequest::with(['requester', 'items.requested', 'events.user'])->findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->delivery_location_id === app(TenantContext::class)->locationId, 404);

        // Fetch all generated system Goods Issue documents for this Request.
        // This query safely ignores 'asset_model' lines (which do not generate GI documents).
        $goodsIssues = GoodsIssue::with(['items', 'creator'])
            ->where('reference_type', ServiceRequest::class)
            ->where('reference_id', $id)
            ->get();

        return view('govstore::fulfillment-register.show', compact('serviceRequest', 'goodsIssues'));
    }
}
