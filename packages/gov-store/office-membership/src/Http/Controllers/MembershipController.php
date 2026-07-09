<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Services\ClearanceEngine;

class MembershipController extends Controller
{
    public function index(ClearanceEngine $engine)
    {
        $user = auth()->user();
        
        $memberships = OfficeMembership::with('location.company')
                            ->where('user_id', $user->id)
                            ->orderBy('is_home_office', 'desc') // Sort by primary home base
                            ->get();

        $clearanceMatrix = [];
        $myActiveRoles = [];
        $eligibleColleagues = [];

        foreach ($memberships as $membership) {
            if ($membership->status === 'active') {
                $locId = $membership->location_id;
                $clearanceMatrix[$membership->id] = $engine->runChecks($user, $locId);

                // Fetch colleagues assigned to this location inside Snipe-IT
                $eligibleColleagues[$locId] = \App\Models\User::where('location_id', $locId)
                                                ->where('id', '!=', $user->id)
                                                ->get();

                // Load any active administrative roles currently held by this user
                if (class_exists(\GovStore\Organization\Models\LocationRole::class)) {
                    $profile = \GovStore\Organization\Models\LocationProfile::where('location_id', $locId)->first();
                    $roles = \GovStore\Organization\Models\LocationRole::where('location_id', $locId)->first();

                    if ($profile && $profile->office_admin_id === $user->id) $myActiveRoles[$locId][] = 'office_admin';
                    if ($roles && $roles->primary_approver_id === $user->id) $myActiveRoles[$locId][] = 'primary_approver';
                    if ($roles && $roles->final_approver_id === $user->id) $myActiveRoles[$locId][] = 'final_approver';
                    if ($roles && $roles->storekeeper_id === $user->id) $myActiveRoles[$locId][] = 'storekeeper';
                }
            }
        }

        // Fetch pending handshakes (Incoming and Outgoing) using updated column mappings
        $incomingRequests = \GovStore\OfficeMembership\Models\RoleHandshake::with(['outgoingUser', 'location'])
                                ->where('incoming_user_id', $user->id) // UPDATED
                                ->where('status', 'pending')->get();
                                
        $outgoingRequests = \GovStore\OfficeMembership\Models\RoleHandshake::with(['incomingUser', 'location'])
                                ->where('outgoing_user_id', $user->id) // UPDATED
                                ->where('status', 'pending')->get();

        return view('govmem::user.index', compact(
            'memberships', 'clearanceMatrix', 'engine', 
            'myActiveRoles', 'eligibleColleagues', 'incomingRequests', 'outgoingRequests'
        ));
    }

    public function requestRelease($id, ClearanceEngine $engine)
    {
        $user = auth()->user();
        $membership = OfficeMembership::where('user_id', $user->id)->findOrFail($id);

        if ($membership->status !== 'active') {
            return redirect()->back()->with('error', 'Only active memberships can be released.');
        }

        // Final backend validation guard
        $results = $engine->runChecks($user, $membership->location_id);
        if (!$engine->isCleared($results)) {
            return redirect()->back()->with('error', 'Clearance failed. Resolve outstanding issues first.');
        }

        $membership->update(['status' => 'release_requested']);

        return redirect()->back()->with('success', 'Release requested successfully. Awaiting final office sign-off.');
    }

    public function switchContext(Request $request)
    {
        $request->validate(['location_id' => 'required|integer']);
        $user = auth()->user();

        // GLOBAL RESTORE HOOK: 
        // If an Admin/Superuser selects "0", clear session context to restore unrestricted global views
        if ((int)$request->location_id === 0 && ($user->isSuperUser() || $user->hasAccess('admin'))) {
            session()->forget('gov_working_location_id');
            return redirect()->back()->with('success', 'Working context restored to Global Overview.');
        }

        // Verify the user actually holds an active membership here
        $membership = OfficeMembership::where('user_id', $user->id)
            ->where('location_id', $request->location_id)
            ->where('status', 'active')
            ->firstOrFail();

        // Set session context (This dynamically drives the TenantScope packages!)
        session()->put('gov_working_location_id', $membership->location_id);

        return redirect()->back()->with('success', 'Working context switched to ' . ($membership->location->name ?? 'selected office') . '.');
    }
}