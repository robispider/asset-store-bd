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
                            ->orderBy('is_default', 'desc')
                            ->get();

        $clearanceMatrix = [];
        $myActiveRoles = [];
        $eligibleColleagues = [];

        foreach ($memberships as $membership) {
            if ($membership->status === 'active') {
                $locId = $membership->location_id;
                $clearanceMatrix[$membership->id] = $engine->runChecks($user, $locId);

                // Fetch colleagues in the same building (for delegation dropdown)
                $eligibleColleagues[$locId] = \App\Models\User::where('location_id', $locId)
                                                ->where('id', '!=', $user->id)
                                                ->get();

                // Detect what roles I currently hold in this office
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

        // Fetch pending handshakes (Incoming and Outgoing)
        $incomingRequests = \GovStore\OfficeMembership\Models\RoleAssignment::with(['assignedBy', 'location'])
                                ->where('assigned_user_id', $user->id)
                                ->where('status', 'pending')->get();
                                
        $outgoingRequests = \GovStore\OfficeMembership\Models\RoleAssignment::with(['assignedUser', 'location'])
                                ->where('assigned_by_user_id', $user->id)
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
            return redirect()->back()->with('error', 'Clearance failed. You must resolve all outstanding assets and roles before requesting release.');
        }

        $membership->update(['status' => 'release_requested']);

        return redirect()->back()->with('success', 'Release requested successfully. Awaiting final office sign-off.');
    }
}