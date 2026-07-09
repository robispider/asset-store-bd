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
    /**
     * Switch the user's active session working context.
     */
    public function switchContext(Request $request)
    {
        $request->validate(['location_id' => 'required|integer']);
        $user = auth()->user();

        // Verify the user actually holds an active membership here
        $membership = OfficeMembership::where('user_id', $user->id)
            ->where('location_id', $request->location_id)
            ->where('status', 'active')
            ->firstOrFail();

        // Set session context (This will be read by the TenantScope package!)
        session()->put('gov_working_location_id', $membership->location_id);

        return redirect()->back()->with('success', 'Working context switched to ' . ($membership->location->name ?? 'selected office') . '.');
    }

    /**
     * Office Admin claims an employee who has requested release from another office.
     */
    public function claimEmployee(Request $request, $locationId)
    {
        $admin = auth()->user();
        
        // Verify executor is the Office Admin for this location
        if (!$admin->isSuperUser() && !$admin->hasAccess('admin')) {
            $profile = LocationProfile::where('location_id', $locationId)->where('office_admin_id', $admin->id)->firstOrFail();
        }

        $request->validate(['user_id' => 'required|integer']);

        DB::transaction(function () use ($request, $locationId) {
            $user = User::findOrFail($request->user_id);
            $oldLocationId = $user->location_id;

            // 1. Mark old memberships as released
            OfficeMembership::where('user_id', $user->id)
                ->where('status', 'release_requested')
                ->update(['status' => 'released', 'is_default' => false]);

            // 2. Create or activate the new membership
            OfficeMembership::updateOrCreate(
                ['user_id' => $user->id, 'location_id' => $locationId],
                ['status' => 'active', 'is_default' => true]
            );

            // 3. Sync Snipe-IT Core (This safely moves their physical inventory target!)
            $user->location_id = $locationId;
            $user->save();

            // Log event (Optional tracking)
            OverrideAuditLog::create([
                'target_user_id' => $user->id,
                'override_type' => 'employee_claimed',
                'reason' => 'Standard onboarding claim by Office Admin',
                'executed_by' => auth()->id(),
                'old_location_id' => $oldLocationId,
                'new_location_id' => $locationId
            ]);
        });

        return redirect()->back()->with('success', 'Employee successfully claimed and integrated into this office.');
    }

    /**
     * SUPERADMIN ONLY: Forcibly release an employee and strip their roles.
     */
    public function forceOverride(Request $request)
    {
        $admin = auth()->user();
        if (!$admin->isSuperUser()) abort(403, 'Strictly reserved for Superadmins.');

        $request->validate([
            'user_id' => 'required|integer',
            'override_type' => 'required|string', // 'force_release', 'strip_roles'
            'reason' => 'required|string|min:10'
        ]);

        DB::transaction(function () use ($request, $admin) {
            $user = User::findOrFail($request->user_id);
            $oldLocationId = $user->location_id;

            if ($request->override_type === 'force_release') {
                OfficeMembership::where('user_id', $user->id)->update(['status' => 'released', 'is_default' => false]);
            }

            if ($request->override_type === 'strip_roles') {
                if (class_exists(\GovStore\Organization\Models\LocationRole::class)) {
                    // Strip administrative designations
                    \GovStore\Organization\Models\LocationRole::where('primary_approver_id', $user->id)->update(['primary_approver_id' => null]);
                    \GovStore\Organization\Models\LocationRole::where('final_approver_id', $user->id)->update(['final_approver_id' => null]);
                    \GovStore\Organization\Models\LocationRole::where('storekeeper_id', $user->id)->update(['storekeeper_id' => null]);
                    \GovStore\Organization\Models\LocationProfile::where('office_admin_id', $user->id)->update(['office_admin_id' => null]);
                }
            }

            // Write mandatory compliance log
            OverrideAuditLog::create([
                'target_user_id' => $user->id,
                'override_type' => $request->override_type,
                'reason' => $request->reason,
                'executed_by' => $admin->id,
                'old_location_id' => $oldLocationId
            ]);
        });

        return redirect()->back()->with('success', 'Emergency override executed and logged to audit trail.');
    }
}