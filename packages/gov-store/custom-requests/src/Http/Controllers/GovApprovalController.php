<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Models\ItemRequest;
use GovStore\CustomRequests\Services\ApprovalService;
use Illuminate\Support\Facades\Gate;

class GovApprovalController extends Controller
{
    /**
     * Helper to ensure only admins can access these routes.
     * Snipe-IT uses 'isSuperUser()' or permission flags.
     */
    private function checkAdminAccess()
    {
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized access.');
        }
    }

public function index()
    {
        $this->checkAdminAccess();
        
        // Fetch all pending requests with the user and item data attached
        $requests = \GovStore\CustomRequests\Models\ItemRequest::with(['requester', 'requestable'])
                        ->pending()
                        ->orderBy('created_at', 'desc')
                        ->get();

        return view('govstore::admin.index', compact('requests'));
    }

    public function approve($id, ApprovalService $service)
    {
        $this->checkAdminAccess();

        $itemRequest = ItemRequest::findOrFail($id);

        try {
            $service->approve($itemRequest, auth()->user());
            return redirect()->back()->with('success', 'Request approved. Item has been checked out automatically.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, $id, ApprovalService $service)
    {
        $this->checkAdminAccess();

        $itemRequest = ItemRequest::findOrFail($id);
        
        try {
            $service->reject($itemRequest, auth()->user(), $request->input('reason'));
            return redirect()->back()->with('success', 'Request rejected successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Rejection failed: ' . $e->getMessage());
        }
    }
}