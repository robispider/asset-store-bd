<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Location;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\EmployeeVerificationToken;
use GovStore\OfficeMembership\Models\OverrideAuditLog;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class MembershipAdminController extends Controller
{
    private function checkSuperadminAccess() {
        if (!auth()->user()->isSuperUser()) {
            abort(403, 'Unauthorized. Emergency overrides require system superadministrator access.');
        }
    }

    private function getActiveAdminLocation() {
        $context = app(TenantContext::class);
        $locId = $context->locationId;
        
        if (!$locId) {
            $profile = LocationProfile::where('office_admin_id', auth()->id())->first();
            if ($profile) $locId = $profile->location_id;
        }

        $profile = LocationProfile::where('location_id', $locId)->where('office_admin_id', auth()->id())->first();
        
        if (!$profile && !auth()->user()->isSuperUser()) {
            abort(403, 'Access Denied: You are not the administrator of this office.');
        }
        return $locId;
    }

    public function index() {
        $locId = $this->getActiveAdminLocation();
        $location = Location::findOrFail($locId);
        $profile = LocationProfile::where('location_id', $locId)->first();

        $activeStaff = OfficeMembership::with('user')->where('location_id', $locId)->where('status', 'active')->get();
        $pendingMemberships = OfficeMembership::with('user')->where('location_id', $locId)->where('status', 'pending')->get();
        
        // Only load floating users for the Claim dropdown.
        $floatingUsers = User::withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class)
            ->whereHas('memberships', function($q) { 
                $q->where('is_home_office', true)
                  ->whereIn('status', ['release_requested', 'released']); 
            })->get();

        return view('govmem::admin.staff', compact('location', 'profile', 'activeStaff', 'pendingMemberships', 'floatingUsers'));
    }

    // =========================================================================
    // WORKFLOW: ADDITIONAL MEMBERSHIP (Strictly Secondary Access)
    // =========================================================================
    public function addEmployeeByToken(Request $request) {
        $locId = $this->getActiveAdminLocation();
        $request->validate(['username' => 'required|string', 'verification_code' => 'required|string|size:6']);

        $targetUser = User::withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class)->where('username', trim($request->input('username')))->first();
        if (!$targetUser) return redirect()->back()->with('error', 'User not found.');

        $token = EmployeeVerificationToken::where('user_id', $targetUser->id)->where('token', strtoupper(trim($request->input('verification_code'))))->first();
        if (!$token || !$token->isValid()) return redirect()->back()->with('error', 'Invalid or expired code.');

        $isTransferring = OfficeMembership::where('user_id', $targetUser->id)
            ->where('is_home_office', true)
            ->whereIn('status', ['release_requested', 'released'])
            ->exists();

        if ($isTransferring) {
            return redirect()->back()->with('error', 'This employee is permanently transferring. Please use the "Claim Transferred Employee" widget below instead.');
        }

        if (OfficeMembership::where('user_id', $targetUser->id)->where('location_id', $locId)->where('status', 'active')->exists()) {
            return redirect()->back()->with('error', 'Employee is already an active member of this office.');
        }

        DB::transaction(function () use ($token, $targetUser, $locId) {
            $token->update(['used_at' => now()]);
            
            // =========================================================================
            // REFACTORED: Use updateOrCreate to avoid unique constraint violations
            // =========================================================================
            OfficeMembership::updateOrCreate(
                ['user_id' => $targetUser->id, 'location_id' => $locId],
                [
                    'status' => 'active', 
                    'is_home_office' => false, 
                    'approved_by_user_id' => auth()->id(), 
                    'approved_at' => now(), 
                    'approval_note' => 'Added secondary access via Verification Code'
                ]
            );
            
            OrganizationActivityLog::create(['location_id' => $locId, 'performed_by' => auth()->id(), 'event_type' => 'membership_granted', 'details' => ['message' => "Secondary access granted to {$targetUser->username} via Token.", 'target_user_id' => $targetUser->id]]);
        });

        return redirect()->back()->with('success', 'Employee granted secondary access to this office.');
    }

    // =========================================================================
    // WORKFLOW: PERMANENT TRANSFER (Claim)
    // =========================================================================
    public function claimEmployee(Request $request) {
        $locId = $this->getActiveAdminLocation();
        $request->validate(['user_id' => 'required|integer']);

        DB::transaction(function () use ($request, $locId) {
            $user = User::withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class)->findOrFail($request->user_id);
            
            // 1. Relocate Identity (Home Office) natively and close old HR records
            OfficeMembership::where('user_id', $user->id)
                ->where('is_home_office', true)
                ->whereIn('status', ['release_requested', 'released'])
                ->update(['status' => 'released', 'is_home_office' => false]);
            
            // 2. Establish new Home Office
            OfficeMembership::updateOrCreate(
                ['user_id' => $user->id, 'location_id' => $locId], 
                ['status' => 'active', 'is_home_office' => true, 'approved_by_user_id' => auth()->id(), 'approved_at' => now(), 'approval_note' => 'Claimed Transfer']
            );
            
            // 3. Sync the Native Identity pointer silently (No observer hijacks)
            $user->location_id = $locId;
            $user->saveQuietly();
        });

        return redirect()->back()->with('success', 'Employee claimed successfully as their new Home Office.');
    }

    // =========================================================================
    // OTHER WORKFLOWS
    // =========================================================================
    public function generateInviteCode() {
        $locId = $this->getActiveAdminLocation();
        $profile = LocationProfile::where('location_id', $locId)->firstOrFail();

        do { $code = strtoupper(Str::random(8)); } while (LocationProfile::where('invitation_code', $code)->exists());

        $profile->update(['invitation_code' => $code, 'invitation_code_created_at' => now(), 'invitation_code_expires_at' => now()->addDays(30)]);
        return redirect()->back()->with('success', 'New Office Invitation Code generated.');
    }

    public function approveMembership($membershipId) {
        $locId = $this->getActiveAdminLocation();
        $membership = OfficeMembership::where('location_id', $locId)->where('id', $membershipId)->where('status', 'pending')->firstOrFail();

        // Safety Guard: Pending self-joins are always Secondary Memberships
        $membership->update([
            'status' => 'active', 
            'is_home_office' => false, 
            'approved_by_user_id' => auth()->id(), 
            'approved_at' => now(), 
            'approval_note' => 'Approved Self-Join via Dashboard'
        ]);
        return redirect()->back()->with('success', 'Employee membership request approved.');
    }

    public function rejectMembership($membershipId) {
        $locId = $this->getActiveAdminLocation();
        $membership = OfficeMembership::where('location_id', $locId)->where('id', $membershipId)->where('status', 'pending')->firstOrFail();
        $membership->delete();
        return redirect()->back()->with('success', 'Employee membership request rejected.');
    }

    public function overrideConsole() {
        $this->checkSuperadminAccess();
        $logs = OverrideAuditLog::with(['targetUser', 'executor'])->orderBy('created_at', 'desc')->get();
        
        $pendingUsers = User::whereHas('memberships', function($q) {
            $q->where('status', 'release_requested');
        })->get();

        $allUsers = User::orderBy('first_name')->get();
        return view('govmem::admin.override_console', compact('logs', 'pendingUsers', 'allUsers'));
    }

    public function forceOverride(Request $request) {
        $this->checkSuperadminAccess();
        $request->validate(['user_id' => 'required|integer', 'override_type' => 'required|string', 'reason' => 'required|string|min:10']);

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

            OverrideAuditLog::create([
                'target_user_id' => $user->id, 'override_type'  => $request->override_type, 'reason' => $request->reason,
                'executed_by' => auth()->id(), 'old_location_id'=> $oldLocationId
            ]);
        });

        return redirect()->back()->with('success', 'Emergency compliance override logged and executed.');
    }
}