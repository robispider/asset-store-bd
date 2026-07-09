<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\OfficeMembership\Services\RoleAssignmentService;

class RoleAssignmentController extends Controller
{
    public function propose(Request $request, RoleAssignmentService $service)
    {
        $request->validate([
            'location_id' => 'required|integer',
            'role_type' => 'required|string',
            'assigned_user_id' => 'required|integer'
        ]);

        try {
            $service->proposeTransfer(
                $request->location_id, 
                $request->role_type, 
                auth()->id(), 
                $request->assigned_user_id
            );
            return redirect()->back()->with('success', 'Role handover proposed. Awaiting colleague acceptance.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function accept($id, RoleAssignmentService $service)
    {
        try {
            $service->acceptTransfer($id, auth()->id());
            return redirect()->back()->with('success', 'Role accepted successfully. Your colleague\'s clearance is updated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject($id, RoleAssignmentService $service)
    {
        try {
            $service->rejectTransfer($id, auth()->id());
            return redirect()->back()->with('success', 'Role handover rejected.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel($id, RoleAssignmentService $service)
    {
        try {
            $service->cancelTransfer($id, auth()->id());
            return redirect()->back()->with('success', 'Pending role handover cancelled.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}