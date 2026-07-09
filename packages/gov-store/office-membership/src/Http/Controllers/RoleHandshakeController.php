<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\OfficeMembership\Services\RoleHandshakeService;

class RoleHandshakeController extends Controller
{
    public function propose(Request $request, RoleHandshakeService $service)
    {
        $request->validate([
            'location_id' => 'required|integer',
            'role_type' => 'required|string',
            'assigned_user_id' => 'required|integer'
        ]);

        try {
            $service->proposeHandshake(
                $request->location_id, 
                $request->role_type, 
                auth()->id(), 
                $request->assigned_user_id
            );
            return redirect()->back()->with('success', 'Handover proposed. Awaiting colleague acceptance.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function accept($id, RoleHandshakeService $service)
    {
        try {
            $service->acceptHandshake($id, auth()->id());
            return redirect()->back()->with('success', 'Handover accepted. Your colleagues clearance has been updated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function reject($id, RoleHandshakeService $service)
    {
        try {
            $service->rejectHandshake($id, auth()->id());
            return redirect()->back()->with('success', 'Handover proposal rejected.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function cancel($id, RoleHandshakeService $service)
    {
        try {
            $service->cancelHandshake($id, auth()->id());
            return redirect()->back()->with('success', 'Handover proposal cancelled.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}