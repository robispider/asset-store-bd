<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use GovStore\OfficeMembership\Services\RoleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RoleAssignmentController extends Controller
{
    public function propose(Request $request, RoleAssignmentService $service)
    {
        $request->validate([
            'location_id' => 'required|integer',
            'role_type' => 'required|string',
            'assigned_user_id' => 'required|integer',
        ]);
        $service->proposeTransfer(
            $request->location_id,
            $request->role_type,
            auth()->id(),
            $request->assigned_user_id
        );

        return redirect()->back()->with('success', __('office_membership::member.assignment_proposed'));
    }

    public function accept($id, RoleAssignmentService $service)
    {
        $service->acceptTransfer($id, auth()->id());

        return redirect()->back()->with('success', __('office_membership::member.assignment_accepted'));
    }

    public function reject($id, RoleAssignmentService $service)
    {
        $service->rejectTransfer($id, auth()->id());

        return redirect()->back()->with('success', __('office_membership::member.assignment_rejected'));
    }

    public function cancel($id, RoleAssignmentService $service)
    {
        $service->cancelTransfer($id, auth()->id());

        return redirect()->back()->with('success', __('office_membership::member.assignment_cancelled'));
    }
}
