<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\OverrideAuditLog;
use GovStore\OfficeMembership\Services\MembershipNotices;
use GovStore\OfficeMembership\Services\MembershipWorkflow;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Scopes\UserScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipAdminController extends Controller
{
    private function checkSuperadminAccess()
    {
        if (! auth()->user()->isSuperUser()) {
            abort(403, __('office_membership::member.admin_unauthorized_override'));
        }
    }

    private function getActiveAdminLocation()
    {
        return app(MembershipWorkflow::class)->adminOffice();
    }

    public function index()
    {
        $locId = $this->getActiveAdminLocation();
        $location = Location::findOrFail($locId);
        $profile = LocationProfile::where('location_id', $locId)->first();

        $activeStaff = OfficeMembership::with('user')->where('location_id', $locId)->where('status', 'active')->get();
        $pendingMemberships = OfficeMembership::with('user')->where('location_id', $locId)->where('status', 'pending')->get();

        // Only load floating users for the Claim dropdown.
        $floatingUsers = User::withoutGlobalScope(UserScope::class)
            ->where('company_id', $location->company_id)
            ->whereHas('memberships', function ($q) {
                $q->where('is_home_office', true)
                    ->where('status', 'released');
            })->get();

        $releaseRequests = OfficeMembership::with('user')->where('location_id', $locId)->where('status', 'release_requested')->get();

        return view('govmem::admin.staff', compact('releaseRequests', 'location', 'profile', 'activeStaff', 'pendingMemberships', 'floatingUsers'));
    }

    // =========================================================================
    // WORKFLOW: ADDITIONAL MEMBERSHIP (Strictly Secondary Access)
    // =========================================================================
    public function addEmployeeByToken(Request $request)
    {
        $request->validate(['username' => 'required|string', 'verification_code' => 'required|string|size:6']);
        app(MembershipWorkflow::class)->addByToken($request->input('username'), $request->input('verification_code'));

        return redirect()->back()->with('success', __('office_membership::member.admin_secondary_access_granted'));
    }

    // =========================================================================
    // WORKFLOW: PERMANENT TRANSFER (Claim)
    // =========================================================================
    public function claimEmployee(Request $request)
    {
        $request->validate(['user_id' => 'required|integer']);
        app(MembershipWorkflow::class)->claim((int) $request->user_id);

        return redirect()->back()->with('success', __('office_membership::member.admin_employee_claimed'));
    }

    // =========================================================================
    // OTHER WORKFLOWS
    // =========================================================================
    public function generateInviteCode()
    {
        $locId = $this->getActiveAdminLocation();
        $profile = LocationProfile::where('location_id', $locId)->firstOrFail();

        do {
            $code = strtoupper(Str::random(8));
        } while (LocationProfile::where('invitation_code', $code)->exists());

        $profile->update(['invitation_code' => $code, 'invitation_code_created_at' => now(), 'invitation_code_expires_at' => now()->addDays(30)]);

        return redirect()->back()->with('success', __('office_membership::member.admin_invite_code_generated'));
    }

    public function approveMembership($membershipId)
    {
        app(MembershipWorkflow::class)->decide((int) $membershipId, 'approve');

        return redirect()->back()->with('success', __('office_membership::member.admin_membership_approved'));
    }

    public function rejectMembership($membershipId)
    {
        app(MembershipWorkflow::class)->decide((int) $membershipId, 'reject');

        return redirect()->back()->with('success', __('office_membership::member.admin_membership_rejected'));
    }

    public function approveRelease($membershipId)
    {
        app(MembershipWorkflow::class)->decide((int) $membershipId, 'release');

        return redirect()->back()->with('success', __('office_membership::member.release_signed_off'));
    }

    public function overrideConsole()
    {
        $this->checkSuperadminAccess();
        $logs = OverrideAuditLog::with(['targetUser', 'executor'])->orderBy('created_at', 'desc')->get();

        $pendingUsers = User::whereHas('memberships', function ($q) {
            $q->where('status', 'release_requested');
        })->get();

        $allUsers = User::orderBy('first_name')->get();

        return view('govmem::admin.override_console', compact('logs', 'pendingUsers', 'allUsers'));
    }

    public function forceOverride(Request $request)
    {
        $this->checkSuperadminAccess();
        $request->validate(['user_id' => 'required|integer', 'override_type' => 'required|in:force_release,strip_roles', 'reason' => 'required|string|min:10']);

        DB::transaction(function () use ($request) {
            $user = User::findOrFail($request->user_id);
            $oldLocationId = $user->location_id;

            if ($request->override_type === 'force_release') {
                OfficeMembership::where('user_id', $user->id)->update(['status' => 'released', 'is_home_office' => false]);
            }

            if ($request->override_type === 'strip_roles') {
                OfficeResponsibility::where('user_id', $user->id)->delete();
                DB::table('gov_access_grants')->where('user_id', $user->id)->delete();
                LocationProfile::where('office_admin_id', $user->id)->update(['office_admin_id' => null]);
            }

            app(MembershipNotices::class)->record('membership_override', (int) $oldLocationId, [$user->id, auth()->id()]);
            OverrideAuditLog::create([
                'target_user_id' => $user->id, 'override_type' => $request->override_type, 'reason' => $request->reason,
                'executed_by' => auth()->id(), 'old_location_id' => $oldLocationId,
            ]);
        });

        return redirect()->back()->with('success', __('office_membership::member.admin_override_executed'));
    }
}
