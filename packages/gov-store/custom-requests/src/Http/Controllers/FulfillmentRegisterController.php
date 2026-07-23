<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\OfficeMembership\Models\OfficeResponsibility;

class FulfillmentRegisterController extends Controller
{
    private function checkAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) return;

        // Verify user has administrative or stores responsibilities (Includes office_admin)
        $hasAccess = OfficeResponsibility::where('user_id', $user->id)
            ->whereIn('role_slug', ['storekeeper', 'primary_approver', 'final_approver', 'office_admin'])
            ->exists();

        if (!$hasAccess) abort(403, __('requestlabels::requests.fulfillmentregistercontroller_abort_unauthorized'));
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
        if (!$user->isSuperUser()) {
            $myLocationIds = OfficeResponsibility::where('user_id', $user->id)
                ->pluck('location_id');
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

        // Fetch all generated system Goods Issue documents for this Request.
        // This query safely ignores 'asset_model' lines (which do not generate GI documents).
        $goodsIssues = GoodsIssue::with(['items', 'creator'])
            ->where('reference_type', ServiceRequest::class)
            ->where('reference_id', $id)
            ->get();

        return view('govstore::fulfillment-register.show', compact('serviceRequest', 'goodsIssues'));
    }
}