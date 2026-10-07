<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use GovStore\OfficeMembership\Services\RoleHandshakeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RoleHandshakeController extends Controller
{
    public function propose(Request $request, RoleHandshakeService $service)
    {
        $request->validate([
            'location_id' => 'required|integer',
            'role_type' => 'required|string',
            'assigned_user_id' => 'required|integer',
        ]);
        $service->proposeHandshake(
            $request->location_id,
            $request->role_type,
            auth()->id(),
            $request->assigned_user_id
        );

        return redirect()->back()->with('success', __('office_membership::member.handshake_proposed'));
    }

    public function accept($id, RoleHandshakeService $service)
    {
        $service->acceptHandshake($id, auth()->id());

        return redirect()->back()->with('success', __('office_membership::member.handshake_accepted'));
    }

    public function reject($id, RoleHandshakeService $service)
    {
        $service->rejectHandshake($id, auth()->id());

        return redirect()->back()->with('success', __('office_membership::member.handshake_rejected'));
    }

    public function cancel($id, RoleHandshakeService $service)
    {
        $service->cancelHandshake($id, auth()->id());

        return redirect()->back()->with('success', __('office_membership::member.handshake_cancelled'));
    }
}
