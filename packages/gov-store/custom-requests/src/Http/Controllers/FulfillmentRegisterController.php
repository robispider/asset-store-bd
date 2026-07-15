<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use Exception;

class FulfillmentRegisterController extends Controller
{
    private function checkAccess()
    {
        $user = auth()->user();
        if ($user->isSuperUser() || $user->hasAccess('admin')) return;

        // Verify user has administrative or stores responsibilities
        $hasAccess = OfficeResponsibility::where('user_id', $user->id)
            ->whereIn('role_slug', ['storekeeper', 'primary_approver', 'final_approver'])
            ->exists();

        if (!$hasAccess) abort(403, 'Unauthorized access to the Fulfillment Register.');
    }

    /**
     * Display all historically completed/fulfilled requests for the user's active location.
     */
    public function index()
    {
        $this->checkAccess();
        $user = auth()->user();

        $query = ServiceRequest::with(['requester', 'approvedBy'])
            ->whereIn('fulfillment_status', ['issued', 'partially_issued', 'closed'])
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

        // Fetch all generated system Goods Issue documents for this Request
        $goodsIssues = GoodsIssue::with(['items', 'creator'])
            ->where('reference_type', ServiceRequest::class)
            ->where('reference_id', $id)
            ->get();

        return view('govstore::fulfillment-register.show', compact('serviceRequest', 'goodsIssues'));
    }
}