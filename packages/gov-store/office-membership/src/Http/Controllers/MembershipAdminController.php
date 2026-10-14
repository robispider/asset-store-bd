<?php

namespace GovStore\OfficeMembership\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Http\Controllers\Concerns\RespondsWithRefusals;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\OverrideAuditLog;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Services\MembershipWorkflow;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Scopes\UserScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MembershipAdminController extends Controller
{
    use RespondsWithRefusals;

    public const TABS = ['staff', 'waiting', 'leaving', 'arriving', 'past'];

    private const PAGE = 25;

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

    /** Office staff: one page, five tabs with counts, and what waits on the administrator first. */
    public function index(Request $request, ClearanceEngine $engine)
    {
        $request->validate(['tab' => 'nullable|in:'.implode(',', self::TABS), 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $locId = $this->getActiveAdminLocation();
        $location = Location::withoutGlobalScopes()->with('company')->findOrFail($locId);
        $profile = LocationProfile::where('location_id', $locId)->first();
        $tab = $request->input('tab', 'staff');
        $term = trim((string) $request->input('q', ''));

        $arriving = $this->arrivingQuery($location);
        $counts = [
            'staff' => OfficeMembership::where('location_id', $locId)->whereIn('status', ['active', 'suspended'])->count(),
            'waiting' => OfficeMembership::where('location_id', $locId)->where('status', 'pending')->count(),
            'leaving' => OfficeMembership::where('location_id', $locId)->where('status', 'release_requested')->count(),
            'arriving' => (clone $arriving)->count(),
            'past' => $this->pastQuery($locId)->count(),
        ];
        $expected = (clone $arriving)->where('destination_location_id', $locId)->count();

        $query = match ($tab) {
            'waiting' => OfficeMembership::where('location_id', $locId)->where('status', 'pending')->oldest('updated_at'),
            'leaving' => OfficeMembership::with('destination')->where('location_id', $locId)->where('status', 'release_requested')->oldest('updated_at'),
            'arriving' => $arriving->with(['location', 'approver', 'destination'])
                ->orderByRaw('CASE WHEN destination_location_id = ? THEN 0 ELSE 1 END', [$locId])->latest('approved_at'),
            'past' => $this->pastQuery($locId)->latest('updated_at'),
            default => OfficeMembership::where('location_id', $locId)->whereIn('status', ['active', 'suspended'])
                ->orderByDesc('is_home_office')->oldest('created_at'),
        };
        $query->with('user');
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('user', fn ($q) => $q->withoutGlobalScope(UserScope::class)->where(fn ($q) => $q
                ->where('first_name', 'LIKE', $like)->orWhere('last_name', 'LIKE', $like)->orWhere('username', 'LIKE', $like)));
        }
        $rows = $query->paginate(self::PAGE)->withQueryString();
        $userIds = $rows->getCollection()->pluck('user_id')->all();

        $readiness = [];
        if ($tab === 'leaving') {
            foreach ($rows as $row) {
                if ($row->user) {
                    $readiness[$row->id] = $engine->runChecks($row->user, $locId);
                }
            }
        }

        return view('govmem::admin.staff', [
            'location' => $location, 'profile' => $profile, 'tab' => $tab, 'term' => $term, 'rows' => $rows,
            'counts' => $counts, 'expected' => $expected, 'readiness' => $readiness, 'engine' => $engine,
            'roles' => $this->rolesHere($locId, $userIds),
            'homes' => $this->homeOffices($userIds),
            'overridden' => $tab === 'past' ? OverrideAuditLog::where('old_location_id', $locId)->where('override_type', 'force_release')
                ->whereIn('target_user_id', $userIds)->pluck('target_user_id')->map(fn ($id) => (int) $id)->all() : [],
            'codeUses' => $profile?->invitation_code_created_at ? OfficeMembership::where('location_id', $locId)->where('is_home_office', false)
                ->where('created_at', '>=', $profile->invitation_code_created_at)->whereNull('approved_by_user_id')->count() : 0,
        ]);
    }

    public function addEmployeeByToken(Request $request)
    {
        $request->validate(['username' => 'required|string|max:191', 'verification_code' => 'required|string|max:20'], [], [
            'username' => __('office_membership::member.field_username'), 'verification_code' => __('office_membership::member.field_personal_code')]);
        $name = User::withoutGlobalScopes()->where('username', trim($request->input('username')))->first();

        return $this->attempt(fn () => app(MembershipWorkflow::class)->addByToken($request->input('username'), $request->input('verification_code')),
            __('office_membership::member.admin_secondary_access_granted', ['name' => $name ? trim($name->first_name.' '.$name->last_name) : '']));
    }

    /** Welcome a transferred employee (claim): the old office has signed them off; this office becomes home. */
    public function claimEmployee(Request $request)
    {
        $request->validate(['user_id' => 'required|integer', 'confirmation' => 'accepted'], [], ['confirmation' => __('office_membership::member.welcome_ack_field')]);
        $name = $this->name((int) $request->user_id);

        return $this->attempt(fn () => app(MembershipWorkflow::class)->claim((int) $request->user_id),
            __('office_membership::member.admin_employee_claimed', ['name' => $name]), route('gov.membership.admin.index'));
    }

    /** A new office code: 8 characters, 30 days. Making one stops the old one. */
    public function generateInviteCode()
    {
        $locId = $this->getActiveAdminLocation();
        $profile = LocationProfile::where('location_id', $locId)->firstOrFail();

        do {
            $code = strtoupper(Str::random(8));
        } while (LocationProfile::where('invitation_code', $code)->exists());

        $profile->update(['invitation_code' => $code, 'invitation_code_created_at' => now(), 'invitation_code_expires_at' => now()->addDays(30)]);

        return redirect()->route('gov.membership.admin.index', ['dialog' => 'code'])->with('success', __('office_membership::member.admin_invite_code_generated'));
    }

    public function approveMembership($membershipId)
    {
        $name = $this->memberName((int) $membershipId);

        return $this->attempt(fn () => app(MembershipWorkflow::class)->decide((int) $membershipId, 'approve'),
            __('office_membership::member.admin_membership_approved', ['name' => $name]));
    }

    public function rejectMembership(Request $request, $membershipId)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);
        $name = $this->memberName((int) $membershipId);

        return $this->attempt(fn () => app(MembershipWorkflow::class)->decide((int) $membershipId, 'reject', $request->input('reason')),
            __('office_membership::member.admin_membership_rejected', ['name' => $name]));
    }

    public function approveRelease($membershipId)
    {
        $name = $this->memberName((int) $membershipId);

        return $this->attempt(fn () => app(MembershipWorkflow::class)->decide((int) $membershipId, 'release'),
            __('office_membership::member.release_signed_off', ['name' => $name]));
    }

    public function endAccess($membershipId)
    {
        $name = $this->memberName((int) $membershipId);

        return $this->attempt(fn () => app(MembershipWorkflow::class)->endAccess((int) $membershipId),
            __('office_membership::member.staff_access_ended', ['name' => $name]));
    }

    /**
     * Emergency changes: find a person, see their reach (offices, roles, holdings), choose one action.
     * The history tab lists every change as a sentence. Nothing is preselected (plan F2).
     */
    public function overrideConsole(Request $request, ClearanceEngine $engine)
    {
        $this->checkSuperadminAccess();
        $request->validate(['tab' => 'nullable|in:change,history', 'q' => 'nullable|string|max:100', 'user' => 'nullable|integer',
            'type' => 'nullable|in:force_release,strip_roles', 'page' => 'nullable|integer|min:1']);
        $tab = $request->input('tab', 'change');
        $term = trim((string) $request->input('q', ''));
        $like = '%'.addcslashes($term, '%_\\').'%';
        $people = collect();
        $person = null;
        $reach = null;

        if ($tab === 'change' && $request->filled('user')) {
            $person = User::withoutGlobalScopes()->whereNull('deleted_at')->find((int) $request->input('user'));
        }
        if ($tab === 'change' && ! $person && mb_strlen($term) >= 2) {
            $people = User::withoutGlobalScopes()->whereNull('deleted_at')->where(fn ($q) => $q->where('first_name', 'LIKE', $like)
                ->orWhere('last_name', 'LIKE', $like)->orWhere('username', 'LIKE', $like))
                ->orderBy('first_name')->limit(21)->get(['id', 'first_name', 'last_name', 'username', 'jobtitle', 'company_id']);
        }
        if ($person) {
            $memberships = OfficeMembership::with('location')->where('user_id', $person->id)->get()
                ->sortBy(fn ($m) => [$m->is_home_office ? 0 : 1, $m->location->name ?? ''])->values();
            $roleOffices = app(MembershipWorkflow::class)->roleOffices((int) $person->id);
            $roles = [];
            foreach (OfficeResponsibility::where('user_id', $person->id)->get() as $role) {
                $roles[$role->location_id][] = __('office_membership::member.role_'.$role->role_slug);
            }
            foreach (LocationProfile::where('office_admin_id', $person->id)->pluck('location_id') as $office) {
                $roles[$office][] = __('office_membership::member.role_office_admin');
            }
            foreach (DB::table('gov_access_grants')->where('user_id', $person->id)->where('expires_at', '>', now())->get() as $grant) {
                $roles[$grant->location_id][] = __('office_membership::member.override_cover', ['role' => __('office_membership::member.role_'.$grant->role_slug)]);
            }
            $holdings = [];
            foreach ($memberships->whereIn('status', MembershipWorkflow::OPEN) as $membership) {
                $open = collect($engine->runChecks($person, (int) $membership->location_id))->reject(fn ($r) => $r->isPassed);
                // Items only: roles are listed on their own, and other rules speak to the person ("your requests").
                $items = $open->reject(fn ($r, $name) => $name === __('office_membership::member.rule_office_responsibility_name'))->filter(fn ($r) => $r->items);
                $others = $open->count() - $items->count() - ($open->has(__('office_membership::member.rule_office_responsibility_name')) ? 1 : 0);
                $holdings[$membership->location_id] = array_values(array_filter(array_merge($items->map(fn ($r) => $r->summary())->values()->all(),
                    [$others > 0 ? trans_choice('office_membership::member.override_other_checks', $others, ['count' => $others]) : null])));
            }
            $offices = Location::withoutGlobalScopes()->whereIn('id', array_merge($roleOffices, $memberships->pluck('location_id')->all()))->pluck('name', 'id');
            $reach = compact('memberships', 'roles', 'holdings', 'offices') + [
                'admins' => LocationProfile::with('officeAdmin')->whereIn('location_id', $offices->keys())->get()->mapWithKeys(fn ($p) => [$p->location_id => $p->officeAdmin])->all(),
            ];
        }

        $history = OverrideAuditLog::with(['targetUser', 'executor', 'office']);
        if ($tab === 'history') {
            if ($request->filled('type')) {
                $history->where('override_type', $request->input('type'));
            }
            if ($term !== '') {
                $history->where(fn ($q) => $q->where('reason', 'LIKE', $like)->orWhere('reference', 'LIKE', $like)
                    ->orWhereHas('targetUser', fn ($u) => $u->where('first_name', 'LIKE', $like)->orWhere('last_name', 'LIKE', $like)->orWhere('username', 'LIKE', $like)));
            }
        }

        return view('govmem::admin.override_console', [
            'tab' => $tab, 'term' => $term, 'people' => $people, 'person' => $person, 'reach' => $reach,
            'historyCount' => OverrideAuditLog::count(),
            'logs' => $tab === 'history' ? $history->latest()->paginate(self::PAGE)->withQueryString() : null,
        ]);
    }

    /** Runs after the national review (typed confirmation) in RequireGovAbility; the review froze this input. */
    public function forceOverride(Request $request)
    {
        $this->checkSuperadminAccess();
        $back = route('gov.membership.override.console', ['user' => (int) $request->input('user_id')]);
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'override_type' => 'required|in:force_release,strip_roles',
            'location_id' => 'nullable|required_if:override_type,force_release|integer',
            'also_remove_roles' => 'nullable|boolean',
            'reason' => 'required|string|min:10|max:1000',
            'reference' => 'nullable|string|max:100',
            'acknowledged' => 'accepted',
        ], [], [
            'override_type' => __('office_membership::member.override_step_what'), 'location_id' => __('office_membership::member.override_which_office'),
            'reason' => __('office_membership::member.override_reason'), 'acknowledged' => __('office_membership::member.override_ack_field'),
        ]);
        if ($validator->fails()) {
            // The review page that sent this request is used up, so return to the console rather than "back".
            return redirect($back)->withErrors($validator)->withInput();
        }
        $name = $this->name((int) $request->input('user_id'));

        return $this->attempt(fn () => app(MembershipWorkflow::class)->override((int) $request->input('user_id'), $request->input('override_type'),
            $request->input('override_type') === 'force_release' ? ($request->integer('location_id') ?: null) : null, $request->boolean('also_remove_roles'), $request->input('reason'), $request->input('reference')),
            __('office_membership::member.admin_override_executed', ['name' => $name]),
            route('gov.membership.override.console', ['tab' => 'history']), $back);
    }

    // ------------------------------------------------------------------ view data

    /** People in this ministry who are ready to transfer (signed off by their home office), not from here. */
    private function arrivingQuery(Location $location)
    {
        return OfficeMembership::where('is_home_office', true)->where('status', 'released')->where('location_id', '!=', $location->id)
            ->whereHas('user', fn ($q) => $q->withoutGlobalScope(UserScope::class)->where('company_id', $location->company_id)
                ->where('activated', true)->whereNull('deleted_at'));
    }

    /** Former staff of this office in the last 12 months. */
    private function pastQuery(int $office)
    {
        return OfficeMembership::where('location_id', $office)->whereIn('status', ['released', 'inactive', 'rejected'])
            ->where(fn ($q) => $q->where('is_home_office', false)->orWhere('status', '!=', 'released'))
            ->where('updated_at', '>=', now()->subYear());
    }

    /** @return array<int, string[]> role labels per user at this office */
    private function rolesHere(int $office, array $userIds): array
    {
        $roles = [];
        foreach (LocationProfile::where('location_id', $office)->whereIn('office_admin_id', $userIds)->pluck('office_admin_id') as $id) {
            $roles[(int) $id][] = __('office_membership::member.role_office_admin');
        }
        foreach (OfficeResponsibility::where('location_id', $office)->whereIn('user_id', $userIds)->get() as $role) {
            $roles[(int) $role->user_id][] = __('office_membership::member.role_'.$role->role_slug);
        }

        return $roles;
    }

    /** @return array<int, string> each user's current home office name */
    private function homeOffices(array $userIds): array
    {
        return OfficeMembership::with('location')->whereIn('user_id', $userIds)->where('is_home_office', true)
            ->whereIn('status', ['active', 'release_requested'])->get()
            ->mapWithKeys(fn ($m) => [(int) $m->user_id => $m->location->name ?? ''])->all();
    }

    private function name(int $userId): string
    {
        $user = User::withoutGlobalScopes()->find($userId);

        return $user ? trim($user->first_name.' '.$user->last_name) : '';
    }

    private function memberName(int $membershipId): string
    {
        return $this->name((int) OfficeMembership::whereKey($membershipId)->value('user_id'));
    }
}
