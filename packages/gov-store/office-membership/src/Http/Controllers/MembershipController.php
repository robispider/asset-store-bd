<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Models\EmployeeVerificationToken;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
     public function index(ClearanceEngine $engine)
    {
        $user = auth()->user();
        
        $memberships = OfficeMembership::with('location.company')
                            ->where('user_id', $user->id)
                            ->orderBy('is_home_office', 'desc')
                            ->get();

        $clearanceMatrix = [];
        $myActiveRoles = [];
        $eligibleColleagues = [];

        foreach ($memberships as $membership) {
            if ($membership->status === 'active') {
                $locId = $membership->location_id;
                $clearanceMatrix[$membership->id] = $engine->runChecks($user, $locId);

                $eligibleColleagues[$locId] = \App\Models\User::withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class)
                                                ->where('location_id', $locId)
                                                ->where('id', '!=', $user->id)
                                                ->get();

                if (class_exists(\GovStore\Organization\Models\LocationRole::class)) {
                    $profile = \GovStore\Organization\Models\LocationProfile::where('location_id', $locId)->first();
                    $roles = \GovStore\OfficeMembership\Models\OfficeResponsibility::where('location_id', $locId)->get();

                    if ($profile && $profile->office_admin_id === $user->id) $myActiveRoles[$locId][] = 'office_admin';
                    if ($roles->where('role_slug', 'primary_approver')->first()?->user_id === $user->id) $myActiveRoles[$locId][] = 'primary_approver';
                    if ($roles->where('role_slug', 'final_approver')->first()?->user_id === $user->id) $myActiveRoles[$locId][] = 'final_approver';
                    if ($roles->where('role_slug', 'storekeeper')->first()?->user_id === $user->id) $myActiveRoles[$locId][] = 'storekeeper';
                }
            }
        }

        $incomingRequests = \GovStore\OfficeMembership\Models\RoleHandshake::with(['outgoingUser', 'location'])
                                ->where('incoming_user_id', $user->id)
                                ->where('status', 'pending')->get();
                                
        $outgoingRequests = \GovStore\OfficeMembership\Models\RoleHandshake::with(['incomingUser', 'location'])
                                ->where('outgoing_user_id', $user->id)
                                ->where('status', 'pending')->get();

        // Fetch the currently active verification token
        $activeToken = EmployeeVerificationToken::where('user_id', $user->id)
                            ->whereNull('used_at')
                            ->where('expires_at', '>', now())
                            ->latest()
                            ->first();

        return view('govmem::user.index', compact(
            'memberships', 'clearanceMatrix', 'engine', 
            'myActiveRoles', 'eligibleColleagues', 'incomingRequests', 'outgoingRequests', 'activeToken'
        ));
    }

    /**
     * Generates a new 6-character onboarding verification token.
     */
    public function generateVerificationToken()
    {
        $user = auth()->user();

        // Delete any previously unused tokens to prevent clutter and enforce single-active-token rule
        EmployeeVerificationToken::where('user_id', $user->id)->whereNull('used_at')->delete();

        // Generate a random 6-character uppercase alphanumeric string
        do {
            $tokenString = strtoupper(Str::random(6));
        } while (EmployeeVerificationToken::where('token', $tokenString)->exists());

        EmployeeVerificationToken::create([
            'user_id' => $user->id,
            'token' => $tokenString,
            'expires_at' => now()->addHours(24),
        ]);

        return redirect()->back()->with('success', __('office_membership::member.membership_token_generated'));
    }


    public function requestRelease($id, ClearanceEngine $engine)
    {
        $user = auth()->user();
        $membership = OfficeMembership::where('user_id', $user->id)->findOrFail($id);

        if ($membership->status !== 'active') {
            return redirect()->back()->with('error', __('office_membership::member.membership_only_active_release'));
        }

        // Final backend validation guard
        $results = $engine->runChecks($user, $membership->location_id);
        if (!$engine->isCleared($results)) {
            return redirect()->back()->with('error', __('office_membership::member.membership_clearance_failed'));
        }

        $membership->update(['status' => 'release_requested']);

        return redirect()->back()->with('success', 'Release requested successfully. Awaiting final office sign-off.');
    }

   public function switchContext(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isSuperUser() || $user->hasAccess('admin');

        // Global restore hook for admins
        if ($isAdmin && $request->has('location_id') && (int)$request->location_id === 0) {
            session()->forget('gov_working_membership_id');
            return redirect()->back()->with('success', __('office_membership::member.membership_context_restored'));
        }

        // Admin switching via raw location_id
        if ($isAdmin && $request->has('location_id')) {
            $locId = $request->input('location_id');
            // Mock a temporary membership in session for the admin
            session()->put('gov_working_membership_id', 'ADMIN_MOCK_' . $locId);
            return redirect()->back()->with('success', __('office_membership::member.membership_context_switched'));
        }

        // Standard user switching via their authorized membership_id
        $request->validate(['membership_id' => 'required|integer']);
        
        $membership = OfficeMembership::where('user_id', $user->id)
            ->where('id', $request->membership_id)
            ->where('status', 'active')
            ->firstOrFail();

        session()->put('gov_working_membership_id', $membership->id);

        return redirect()->back()->with('success', str_replace(':office', $membership->location->name ?? __('office_membership::member.staff_claim_hint'), __('office_membership::member.membership_context_switched_to')));
    }

    /**
     * Submits a request to join an office using the Office Invitation Code.
     */
    public function joinByCode(Request $request)
    {
        $request->validate(['office_code' => 'required|string']);
        $code = strtoupper(trim($request->input('office_code')));
        $user = auth()->user();

        $profile = \GovStore\Organization\Models\LocationProfile::where('invitation_code', $code)->first();

        if (!$profile || !$profile->invitation_code_expires_at || $profile->invitation_code_expires_at->isPast()) {
            return redirect()->back()->with('error', __('office_membership::member.membership_invalid_code'));
        }

        $existing = OfficeMembership::where('user_id', $user->id)->where('location_id', $profile->location_id)->first();

        if ($existing) {
            if ($existing->status === 'active') return redirect()->back()->with('error', __('office_membership::member.membership_already_member'));
            if ($existing->status === 'pending') return redirect()->back()->with('error', __('office_membership::member.membership_request_pending'));
        }

        // =========================================================================
        // REFACTORED: Use updateOrCreate to prevent unique key violations
        // =========================================================================
        OfficeMembership::updateOrCreate(
            ['user_id' => $user->id, 'location_id' => $profile->location_id],
            [
                'status' => 'pending',
                'is_home_office' => false
            ]
        );

        return redirect()->back()->with('success', __('office_membership::member.membership_request_sent'));
    }
}