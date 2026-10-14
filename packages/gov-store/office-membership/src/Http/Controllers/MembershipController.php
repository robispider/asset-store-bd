<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Http\Controllers\Concerns\RespondsWithRefusals;
use GovStore\OfficeMembership\Models\EmployeeVerificationToken;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Services\MembershipNotices;
use GovStore\OfficeMembership\Services\MembershipWorkflow;
use GovStore\OfficeMembership\Services\OfficePicker;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
    use RespondsWithRefusals;

    /** Statuses shown as current offices; everything else is history. */
    private const CURRENT = ['pending', 'active', 'release_requested', 'suspended'];

    public function index(ClearanceEngine $engine, MembershipNotices $notices)
    {
        $user = auth()->user();
        $memberships = $this->memberships($user);
        [$current, $past] = $memberships->partition(fn ($m) => in_array($m->status, self::CURRENT, true) || ($m->status === 'released' && $m->is_home_office));
        $live = $current->filter(fn ($m) => $m->isLive());
        $officeIds = $live->pluck('location_id')->merge($current->where('status', 'release_requested')->pluck('location_id'))->unique()->all();

        $readiness = [];
        foreach ($current->whereIn('status', ['active', 'release_requested']) as $membership) {
            $readiness[$membership->id] = $engine->runChecks($user, (int) $membership->location_id);
        }

        return view('govmem::user.index', [
            'user' => $user,
            'current' => $current->values(),
            'past' => $past->values(),
            'readiness' => $readiness,
            'engine' => $engine,
            'roles' => $this->rolesByOffice((int) $user->id, $officeIds),
            'cover' => $this->coverByOffice((int) $user->id),
            'admins' => $this->officeAdmins($memberships->pluck('location_id')->all()),
            'colleagues' => $this->colleagues($user, $live->pluck('location_id')->all()),
            'incoming' => RoleHandshake::with(['outgoingUser', 'location' => fn ($q) => $q->withoutGlobalScopes()])
                ->where('incoming_user_id', $user->id)->where('status', 'pending')->latest()->get(),
            'outgoing' => RoleHandshake::with('incomingUser')->where('outgoing_user_id', $user->id)->where('status', 'pending')->get(),
            'activeToken' => $this->activeToken((int) $user->id),
            'notices' => $notices->forUser((int) $user->id)->map(fn ($notice) => $notices->present($notice)),
            'workingOffice' => (int) app(TenantContext::class)->locationId,
        ]);
    }

    /** Join another office: with an office code (checked first, so the person sees the office) or a personal code. */
    public function joinForm(Request $request, MembershipWorkflow $workflow)
    {
        $request->validate(['code' => 'nullable|string|max:20', 'way' => 'nullable|in:office,personal']);
        $user = auth()->user();
        $code = (string) $request->input('code', '');
        $checked = null;
        if ($code !== '') {
            $profile = $workflow->officeForCode($code);
            $existing = $profile ? OfficeMembership::where('user_id', $user->id)->where('location_id', $profile->location_id)->first() : null;
            $checked = ['code' => MembershipWorkflow::normaliseCode($code), 'profile' => $profile,
                'state' => match (true) {
                    ! $profile || ! $profile->location => 'unknown',
                    ! $profile->invitation_code_expires_at?->isFuture() => 'expired',
                    $existing?->status === 'pending' => 'pending',
                    $existing && ! in_array($existing->status, MembershipWorkflow::ENDED, true) => 'member',
                    default => 'ok',
                },
                'admin' => $profile ? LocationProfile::with('officeAdmin')->where('location_id', $profile->location_id)->first()?->officeAdmin : null];
        }

        return view('govmem::user.join', [
            'user' => $user,
            'way' => $request->input('way', $code !== '' || old('office_code') ? 'office' : null),
            'checked' => $checked,
            'activeToken' => $this->activeToken((int) $user->id),
        ]);
    }

    public function joinByCode(Request $request, MembershipWorkflow $workflow)
    {
        $request->validate(['office_code' => 'required|string|max:20']);
        $office = $workflow->officeForCode($request->input('office_code'))?->location?->name ?? '';

        return $this->attempt(fn () => $workflow->join($request->input('office_code')),
            __('office_membership::member.membership_request_sent', ['office' => $office]),
            route('gov.membership.index'));
    }

    public function cancelJoin($id, MembershipWorkflow $workflow)
    {
        $office = OfficeMembership::where('user_id', auth()->id())->with('location')->find((int) $id)?->location?->name ?? '';

        return $this->attempt(fn () => $workflow->cancelJoin((int) $id),
            __('office_membership::member.join_cancelled', ['office' => $office]), route('gov.membership.index'));
    }

    /** Guided leave: readiness, then the request, then a tracker until the next office welcomes the person. */
    public function leave($id, ClearanceEngine $engine)
    {
        $user = auth()->user();
        $membership = OfficeMembership::with(['location.company', 'destination', 'approver'])->where('user_id', $user->id)->findOrFail((int) $id);
        if (! in_array($membership->status, ['active', 'release_requested', 'released'], true)) {
            return redirect()->route('gov.membership.index');
        }
        $office = (int) $membership->location_id;

        return view('govmem::user.leave', [
            'user' => $user,
            'membership' => $membership,
            'checks' => in_array($membership->status, ['active', 'release_requested'], true) ? $engine->runChecks($user, $office) : [],
            'engine' => $engine,
            'roles' => $this->rolesByOffice((int) $user->id, [$office])[$office] ?? [],
            'cover' => $this->coverByOffice((int) $user->id)[$office] ?? [],
            'colleagues' => $this->colleagues($user, [$office]),
            'outgoing' => RoleHandshake::with('incomingUser')->where('outgoing_user_id', $user->id)->where('location_id', $office)->where('status', 'pending')->get(),
            'admin' => $this->officeAdmins([$office])[$office] ?? null,
            'others' => OfficeMembership::with('location')->where('user_id', $user->id)->where('id', '!=', $membership->id)->where('status', 'active')->get(),
        ]);
    }

    public function requestRelease(Request $request, $id, MembershipWorkflow $workflow)
    {
        $request->validate(['destination_location_id' => 'nullable|integer|min:1', 'acknowledged' => 'accepted']);

        return $this->attempt(fn () => $workflow->requestRelease((int) $id, $request->integer('destination_location_id') ?: null),
            __('office_membership::member.release_requested'), route('gov.membership.leave', (int) $id));
    }

    public function withdrawRelease($id, MembershipWorkflow $workflow)
    {
        return $this->attempt(fn () => $workflow->withdrawRelease((int) $id),
            __('office_membership::member.leave_withdrawn'), route('gov.membership.index'));
    }

    /** Offices in the same ministry, for "Where are you going?" (paged JSON for select2). */
    public function destinations(Request $request, $id)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1|max:1000']);
        $membership = OfficeMembership::where('user_id', auth()->id())->findOrFail((int) $id);
        $company = Location::withoutGlobalScopes()->whereKey($membership->location_id)->value('company_id');
        $term = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $query = Location::withoutGlobalScopes()->with(['parent' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereNull('locations.deleted_at')->where('company_id', $company)->whereKeyNot($membership->location_id)->whereHas('profile');
        if ($term !== '') {
            $query->where('locations.name', 'LIKE', '%'.addcslashes($term, '%_\\').'%');
        }
        $rows = $query->orderBy('locations.name')->skip(($page - 1) * OfficePicker::PAGE_SIZE)->take(OfficePicker::PAGE_SIZE + 1)->get();

        return response()->json([
            'results' => $rows->take(OfficePicker::PAGE_SIZE)->map(fn ($l) => ['id' => $l->id, 'text' => $l->name.($l->parent ? ' · '.$l->parent->name : '')])->values(),
            'pagination' => ['more' => $rows->count() > OfficePicker::PAGE_SIZE],
        ]);
    }

    /** A personal code: 6 characters, works once, for 24 hours. Making a new one stops the old one. */
    public function generateVerificationToken()
    {
        $user = auth()->user();
        EmployeeVerificationToken::where('user_id', $user->id)->whereNull('used_at')->delete();
        do {
            $tokenString = strtoupper(Str::random(6));
        } while (EmployeeVerificationToken::where('token', $tokenString)->exists());

        EmployeeVerificationToken::create(['user_id' => $user->id, 'token' => $tokenString, 'expires_at' => now()->addHours(24)]);

        return redirect()->back()->with('success', __('office_membership::member.membership_token_generated'));
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
            $location = Location::withoutGlobalScopes()->findOrFail($request->input('location_id'));
            // Mock a temporary membership in session for the admin
            session()->put('gov_working_membership_id', 'ADMIN_MOCK_'.$location->id);
            app(OfficePicker::class)->remember((int) $user->id, (int) $location->id);

            return redirect()->back()->with('success', __('office_membership::member.membership_context_switched_to', ['office' => $location->name]));
        }

        // Standard user switching via their authorized membership_id
        $request->validate(['membership_id' => 'required|integer']);

        $membership = OfficeMembership::where('user_id', $user->id)
            ->where('id', $request->membership_id)
            ->where('status', 'active')->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()))
            ->firstOrFail();

        session()->put('gov_working_membership_id', $membership->id);
        app(OfficePicker::class)->remember((int) $user->id, (int) $membership->location_id);

        return redirect()->back()->with('success', __('office_membership::member.membership_context_switched_to',
            ['office' => $membership->location->name ?? __('office_membership::member.unknown_office')]));
    }

    /**
     * Office search for the navbar office chip (JSON). Members search their own active
     * memberships; super users search every office with a profile. Paged, never the full list.
     */
    public function offices(Request $request, OfficePicker $picker)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1|max:1000']);

        return response()->json($picker->search(auth()->user(), (string) $request->input('q', ''), (int) $request->input('page', 1)));
    }

    // ------------------------------------------------------------------ view data

    private function memberships(User $user): Collection
    {
        $order = ['active' => 0, 'release_requested' => 1, 'released' => 2, 'suspended' => 3, 'pending' => 4];

        return OfficeMembership::with(['location.company', 'destination', 'approver'])->where('user_id', $user->id)->get()
            ->sortBy(fn ($m) => [$m->is_home_office ? 0 : 1, $order[$m->status] ?? 9, $m->location->name ?? ''])->values();
    }

    /** @return array<int, string[]> role slugs per office: office administrator plus responsibilities */
    private function rolesByOffice(int $userId, array $offices): array
    {
        $roles = [];
        foreach (LocationProfile::whereIn('location_id', $offices)->where('office_admin_id', $userId)->pluck('location_id') as $office) {
            $roles[(int) $office][] = 'office_admin';
        }
        foreach (OfficeResponsibility::whereIn('location_id', $offices)->where('user_id', $userId)->get() as $role) {
            $roles[(int) $role->location_id][] = $role->role_slug;
        }

        return $roles;
    }

    /** @return array<int, array<int, object>> unexpired temporary cover, which can't be handed over and simply ends */
    private function coverByOffice(int $userId): array
    {
        return DB::table('gov_access_grants')->where('user_id', $userId)->where('expires_at', '>', now())
            ->get()->groupBy('location_id')->map(fn ($rows) => $rows->all())->all();
    }

    /** @return array<int, ?User> office administrator per office */
    private function officeAdmins(array $offices): array
    {
        return LocationProfile::with('officeAdmin')->whereIn('location_id', $offices)->get()
            ->mapWithKeys(fn ($profile) => [(int) $profile->location_id => $profile->officeAdmin])->all();
    }

    /**
     * Colleagues per office for the hand-over picker: active members with their job and roles there.
     * People who are leaving are listed but can't be chosen.
     */
    private function colleagues(User $user, array $offices): array
    {
        if (! $offices) {
            return [];
        }
        $rows = OfficeMembership::with('user')->whereIn('location_id', $offices)->where('user_id', '!=', $user->id)
            ->whereIn('status', ['active', 'release_requested'])
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()))->get()
            ->filter(fn ($m) => $m->user && $m->user->activated);
        $roles = [];
        foreach (OfficeResponsibility::whereIn('location_id', $offices)->whereIn('user_id', $rows->pluck('user_id'))->get() as $role) {
            $roles[$role->location_id][$role->user_id][] = __('office_membership::member.role_'.$role->role_slug);
        }
        foreach (LocationProfile::whereIn('location_id', $offices)->whereIn('office_admin_id', $rows->pluck('user_id'))->get() as $profile) {
            $roles[$profile->location_id][$profile->office_admin_id][] = __('office_membership::member.role_office_admin');
        }

        return $rows->groupBy('location_id')->map(fn ($members, $office) => $members->map(fn ($m) => [
            'id' => (int) $m->user_id,
            'name' => trim($m->user->first_name.' '.$m->user->last_name) ?: $m->user->username,
            'job' => $m->user->jobtitle,
            'username' => $m->user->username,
            'roles' => $roles[$office][$m->user_id] ?? [],
            'leaving' => $m->status === 'release_requested',
        ])->sortBy('name')->values()->all())->all();
    }

    private function activeToken(int $userId): ?EmployeeVerificationToken
    {
        return EmployeeVerificationToken::where('user_id', $userId)->whereNull('used_at')->where('expires_at', '>', now())->latest()->first();
    }
}
