<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OverrideAuditLog;
use GovStore\Organization\Models\LocationProfile;
use Illuminate\Support\Facades\DB;

class MembershipAdminController extends Controller
{
    private function checkSuperadminAccess()
    {
        if (!auth()->user()->isSuperUser()) {
            abort(403, 'Unauthorized. Emergency overrides require system superadministrator access.');
        }
    }

    /**
     * Office Administrator claims an employee who has requested release or is floating
     */
    public function claimEmployee(Request $request, $locationId)
    {
        $admin = auth()->user();
        
        // Safety verification: Is the executor authorized to manage this office building?
        if (!$admin->isSuperUser() && !$admin->hasAccess('admin')) {
            LocationProfile::where('location_id', $locationId)->where('office_admin_id', $admin->id)->firstOrFail();
        }

        $request->validate(['user_id' => 'required|integer']);

        DB::transaction(function () use ($request, $locationId) {
            $user = User::findOrFail($request->user_id);
            $oldLocationId = $user->location_id;

            // 1. Mark previous active office memberships as released/archived
            OfficeMembership::where('user_id', $user->id)
                ->where('status', 'release_requested')
                ->update(['status' => 'released', 'is_home_office' => false]);

            // 2. Set up their active membership under the new office
            OfficeMembership::updateOrCreate(
                ['user_id' => $user->id, 'location_id' => $locationId],
                ['status' => 'active', 'is_home_office' => true]
            );

            // 3. HANDSHAKE: Update Snipe-IT's core column (keeping physical checkouts stable)
            $user->location_id = $locationId;
            $user->save();
        });

        return redirect()->back()->with('success', 'Employee successfully claimed and assigned to your office.');
    }

    /**
     * Superadmin emergency override console
     */
    public function overrideConsole()
    {
        $this->checkSuperadminAccess();

        $logs = OverrideAuditLog::with(['targetUser', 'executor'])->orderBy('created_at', 'desc')->get();
        
        // Fetch users who are currently stuck in transitional release requested phases
        $pendingUsers = User::whereHas('memberships', function($q) {
            $q->where('status', 'release_requested');
        })->get();

        $allUsers = User::orderBy('first_name')->get();

        return view('govmem::admin.override_console', compact('logs', 'pendingUsers', 'allUsers'));
    }

    /**
     * Forcibly drops memberships or clears administrative slots, bypass checks
     */
    public function forceOverride(Request $request)
    {
        $this->checkSuperadminAccess();

        $request->validate([
            'user_id' => 'required|integer',
            'override_type' => 'required|string', // 'force_release' or 'strip_roles'
            'reason' => 'required|string|min:10'
        ]);

        DB::transaction(function () use ($request) {
            $user = User::findOrFail($request->user_id);
            $oldLocationId = $user->location_id;

            if ($request->override_type === 'force_release') {
                OfficeMembership::where('user_id', $user->id)->update(['status' => 'released', 'is_home_office' => false]);
            }

            if ($request->override_type === 'strip_roles') {
                if (class_exists(\GovStore\Organization\Models\LocationRole::class)) {
                    \GovStore\Organization\Models\LocationRole::where('primary_approver_id', $user->id)->update(['primary_approver_id' => null]);
                    \GovStore\Organization\Models\LocationRole::where('final_approver_id', $user->id)->update(['final_approver_id' => null]);
                    \GovStore\Organization\Models\LocationRole::where('storekeeper_id', $user->id)->update(['storekeeper_id' => null]);
                    \GovStore\Organization\Models\LocationProfile::where('office_admin_id', $user->id)->update(['office_admin_id' => null]);
                }
            }

            // Write permanent justification log
            OverrideAuditLog::create([
                'target_user_id' => $user->id,
                'override_type'  => $request->override_type,
                'reason'         => $request->reason,
                'executed_by'    => auth()->id(),
                'old_location_id'=> $oldLocationId
            ]);
        });

        return redirect()->back()->with('success', 'Emergency compliance override logged and executed.');
    }
}