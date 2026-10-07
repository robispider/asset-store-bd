<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Models\EmployeeVerificationToken;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Services\MembershipNotices;
use GovStore\OfficeMembership\Services\MembershipWorkflow;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Scopes\UserScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

                $eligibleColleagues[$locId] = User::withoutGlobalScope(UserScope::class)
                    ->where('activated', true)
                    ->whereHas('memberships', fn ($q) => $q->where('location_id', $locId)->where('status', 'active')
                        ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString())))
                    ->where('id', '!=', $user->id)
                    ->get(['id', 'first_name', 'last_name', 'username']);

                $profile = LocationProfile::where('location_id', $locId)->first();
                $roles = OfficeResponsibility::where('location_id', $locId)->where('user_id', $user->id)->get();

                if ($profile && (int) $profile->office_admin_id === (int) $user->id) {
                    $myActiveRoles[$locId][] = 'office_admin';
                }
                foreach ($roles as $role) {
                    $myActiveRoles[$locId][] = $role->role_slug;
                }
            }
        }

        $incomingRequests = RoleHandshake::with(['outgoingUser', 'location'])
            ->where('incoming_user_id', $user->id)
            ->where('status', 'pending')->get();

        $outgoingRequests = RoleHandshake::with(['incomingUser', 'location'])
            ->where('outgoing_user_id', $user->id)
            ->where('status', 'pending')->get();

        // Fetch the currently active verification token
        $activeToken = EmployeeVerificationToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $notices = app(MembershipNotices::class)->forUser((int) $user->id);

        return view('govmem::user.index', compact('notices',
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
        app(MembershipWorkflow::class)->requestRelease((int) $id);

        return redirect()->back()->with('success', __('office_membership::member.release_requested'));
    }

    public function switchContext(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isSuperUser();

        // Global restore hook for admins
        if ($isAdmin && $request->has('location_id') && (int) $request->location_id === 0) {
            session()->forget('gov_working_membership_id');

            return redirect()->back()->with('success', __('office_membership::member.membership_context_restored'));
        }

        // Admin switching via raw location_id
        if ($isAdmin && $request->has('location_id')) {
            $request->validate(['location_id' => 'required|integer|min:1']);
            $locId = Location::withoutGlobalScopes()->findOrFail($request->input('location_id'))->id;
            // Mock a temporary membership in session for the admin
            session()->put('gov_working_membership_id', 'ADMIN_MOCK_'.$locId);

            return redirect()->back()->with('success', __('office_membership::member.membership_context_switched'));
        }

        // Standard user switching via their authorized membership_id
        $request->validate(['membership_id' => 'required|integer']);

        $membership = OfficeMembership::where('user_id', $user->id)
            ->where('id', $request->membership_id)
            ->where('status', 'active')->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()))
            ->firstOrFail();

        session()->put('gov_working_membership_id', $membership->id);

        return redirect()->back()->with('success', str_replace(':office', $membership->location->name ?? __('office_membership::member.staff_claim_hint'), __('office_membership::member.membership_context_switched_to')));
    }

    /**
     * Submits a request to join an office using the Office Invitation Code.
     */
    public function joinByCode(Request $request)
    {
        $request->validate(['office_code' => 'required|string|max:15']);
        app(MembershipWorkflow::class)->join($request->input('office_code'));

        return redirect()->back()->with('success', __('office_membership::member.membership_request_sent'));
    }
}
