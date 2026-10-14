<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Exceptions\MembershipRefused;
use GovStore\OfficeMembership\Models\EmployeeVerificationToken;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\OverrideAuditLog;
use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MembershipWorkflow
{
    /** Statuses after which a person may ask to join the same office again. */
    public const ENDED = ['inactive', 'rejected', 'released', 'cancelled'];

    /** Statuses an emergency release can end. */
    public const OPEN = ['active', 'pending', 'release_requested', 'suspended'];

    /** Codes are shown in groups and may be typed with spaces or dashes, in any case. */
    public static function normaliseCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /** The office behind an office code, for the "check the code" step. Null when the code is unknown. */
    public function officeForCode(string $code): ?LocationProfile
    {
        $code = self::normaliseCode($code);

        return $code === '' ? null : LocationProfile::with(['location' => fn ($q) => $q->withoutGlobalScopes()->with('company')])
            ->where('invitation_code', $code)->first();
    }

    public function join(string $code): void
    {
        $code = self::normaliseCode($code);
        DB::transaction(function () use ($code) {
            $initial = $code === '' ? null : LocationProfile::where('invitation_code', $code)->first();
            MembershipRefused::unless((bool) $initial, 404, 'membership_invalid_code', 'office_code');
            $profile = app(RoleTransfer::class)->lockOffice($initial->location_id);
            MembershipRefused::unless($profile->invitation_code === $code, 404, 'membership_invalid_code', 'office_code');
            MembershipRefused::unless((bool) $profile->invitation_code_expires_at?->isFuture(), 422, 'membership_code_expired', 'office_code');
            User::withoutGlobalScopes()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $existing = OfficeMembership::where('user_id', auth()->id())->where('location_id', $profile->location_id)->lockForUpdate()->first();
            MembershipRefused::when($existing?->status === 'pending', 409, 'membership_request_pending', 'office_code');
            MembershipRefused::when($existing && ! in_array($existing->status, self::ENDED, true), 409, 'membership_already_member', 'office_code');
            MembershipRefused::when((bool) $existing?->is_home_office, 409, 'membership_already_member', 'office_code');
            OfficeMembership::updateOrCreate(['user_id' => auth()->id(), 'location_id' => $profile->location_id],
                ['status' => 'pending', 'is_home_office' => false, 'valid_until' => null, 'approval_note' => null]);
        });
    }

    /** The person withdraws their own request to join, before the office decides. */
    public function cancelJoin(int $id): void
    {
        DB::transaction(function () use ($id) {
            $initial = OfficeMembership::where('user_id', auth()->id())->findOrFail($id);
            app(RoleTransfer::class)->lockOffice($initial->location_id, false);
            $membership = OfficeMembership::where('user_id', auth()->id())->lockForUpdate()->findOrFail($id);
            MembershipRefused::unless($membership->status === 'pending', 409, 'join_already_decided');
            $membership->update(['status' => 'cancelled']);
        });
    }

    public function addByToken(string $username, string $code): void
    {
        DB::transaction(function () use ($username, $code) {
            $office = $this->adminOffice();
            $profile = app(RoleTransfer::class)->lockOffice($office);
            $this->adminOffice($profile);
            $company = Location::withoutGlobalScopes()->findOrFail($office)->company_id;
            $user = User::withoutGlobalScopes()->where('username', trim($username))->where('company_id', $company)
                ->where('activated', true)->whereNull('deleted_at')->lockForUpdate()->first();
            MembershipRefused::unless((bool) $user, 404, 'admin_user_not_found', 'username');
            $name = trim($user->first_name.' '.$user->last_name);
            $token = EmployeeVerificationToken::where('user_id', $user->id)->where('token', self::normaliseCode($code))->lockForUpdate()->first();
            MembershipRefused::unless((bool) $token, 404, 'admin_invalid_code', 'verification_code', ['name' => $name]);
            MembershipRefused::when($token->used_at !== null, 422, 'admin_code_used', 'verification_code', ['name' => $name]);
            MembershipRefused::unless($token->isValid(), 422, 'admin_code_expired', 'verification_code', ['name' => $name]);
            MembershipRefused::when(OfficeMembership::where('user_id', $user->id)->where('is_home_office', true)
                ->whereIn('status', ['release_requested', 'released'])->exists(), 409, 'admin_permanently_transferring', 'username', ['name' => $name]);
            MembershipRefused::when(OfficeMembership::where('user_id', $user->id)->where('location_id', $office)->where('status', 'active')->exists(),
                409, 'admin_already_member', 'username', ['name' => $name]);
            $token->update(['used_at' => now()]);
            OfficeMembership::updateOrCreate(['user_id' => $user->id, 'location_id' => $office], ['status' => 'active',
                'is_home_office' => false, 'valid_until' => null, 'approved_by_user_id' => auth()->id(), 'approved_at' => now()]);
        });
    }

    public function adminOffice(?LocationProfile $lockedProfile = null): int
    {
        $office = app(TenantContext::class)->locationId;
        abort_unless($office, 403);
        Location::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($office);
        $isAdmin = $lockedProfile ? (int) $lockedProfile->office_admin_id === (int) auth()->id()
            : LocationProfile::where('location_id', $office)->where('office_admin_id', auth()->id())->exists();
        abort_unless(auth()->user()->isSuperUser() || $isAdmin, 403);
        if (! auth()->user()->isSuperUser()) {
            app(RoleTransfer::class)->activeMember((int) auth()->id(), $office, $lockedProfile !== null);
        }

        return $office;
    }

    /** Refuses with the open items, in words, when any clearance rule still blocks. */
    private function cleared(User $user, int $office, string $key = 'membership_clearance_failed'): void
    {
        $engine = app(ClearanceEngine::class);
        $results = $engine->runChecks($user, $office);
        if (! $engine->isCleared($results)) {
            $open = collect($results)->reject(fn ($result) => $result->isPassed)->map(fn ($result) => $result->reason)->filter()->implode(' ');
            throw new MembershipRefused(409, $key, 'membership', ['details' => $open, 'name' => trim($user->first_name.' '.$user->last_name)]);
        }
    }

    /**
     * Ask to leave an office. For a home office the person may say where they are going (D3), so that
     * office's administrator can expect them; the destination must be another office in the same ministry.
     */
    public function requestRelease(int $id, ?int $destination = null): void
    {
        DB::transaction(function () use ($id, $destination) {
            $initial = OfficeMembership::where('user_id', auth()->id())->findOrFail($id);
            app(RoleTransfer::class)->lockOffice($initial->location_id, false);
            $membership = app(RoleTransfer::class)->activeMember((int) auth()->id(), $initial->location_id);
            $this->cleared(auth()->user(), $membership->location_id);
            if ($destination && $membership->is_home_office) {
                $company = Location::withoutGlobalScopes()->whereKey($membership->location_id)->value('company_id');
                $valid = $destination !== (int) $membership->location_id && Location::withoutGlobalScopes()->whereKey($destination)
                    ->whereNull('deleted_at')->where('company_id', $company)->whereHas('profile')->exists();
                MembershipRefused::unless($valid, 422, 'leave_destination_invalid', 'destination_location_id');
            } else {
                $destination = null;
            }
            $membership->update(['status' => 'release_requested', 'destination_location_id' => $destination]);
            if ($destination) {
                app(MembershipNotices::class)->record('membership_arriving', $destination,
                    [LocationProfile::where('location_id', $destination)->value('office_admin_id')],
                    ['name' => trim(auth()->user()->first_name.' '.auth()->user()->last_name), 'from' => $membership->location?->name]);
            }
        });
    }

    /** The person takes back their request to leave, before the office signs them off. */
    public function withdrawRelease(int $id): void
    {
        DB::transaction(function () use ($id) {
            $initial = OfficeMembership::where('user_id', auth()->id())->findOrFail($id);
            app(RoleTransfer::class)->lockOffice($initial->location_id, false);
            $membership = OfficeMembership::where('user_id', auth()->id())->lockForUpdate()->findOrFail($id);
            MembershipRefused::unless($membership->status === 'release_requested', 409, 'leave_already_signed_off');
            $membership->update(['status' => 'active', 'destination_location_id' => null]);
        });
    }

    public function decide(int $id, string $decision, ?string $note = null): void
    {
        abort_unless(in_array($decision, ['approve', 'reject', 'release'], true), 422);
        DB::transaction(function () use ($id, $decision, $note) {
            $office = $this->adminOffice();
            $profile = app(RoleTransfer::class)->lockOffice($office, $decision === 'approve');
            $this->adminOffice($profile);
            $membership = OfficeMembership::where('location_id', $office)->lockForUpdate()->findOrFail($id);
            abort_if((int) $membership->user_id === (int) auth()->id(), 403);
            $user = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($membership->user_id);
            $expected = $decision === 'release' ? 'release_requested' : 'pending';
            MembershipRefused::unless($membership->status === $expected, 409, 'staff_already_decided', 'membership',
                ['name' => trim($user->first_name.' '.$user->last_name)]);
            if ($decision === 'release') {
                $this->cleared($user, $office, 'staff_signoff_not_clear');
            }
            $membership->update(['status' => match ($decision) {
                'release' => 'released', 'approve' => 'active', 'reject' => 'rejected'
            },
                'is_home_office' => $decision === 'release' && $membership->is_home_office,
                'approval_note' => $decision === 'reject' && $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
                'approved_by_user_id' => auth()->id(), 'approved_at' => now()]);
        });
    }

    /** An administrator ends someone's additional (not home) access to their office. */
    public function endAccess(int $id): void
    {
        DB::transaction(function () use ($id) {
            $office = $this->adminOffice();
            $profile = app(RoleTransfer::class)->lockOffice($office, false);
            $this->adminOffice($profile);
            $membership = OfficeMembership::where('location_id', $office)->lockForUpdate()->findOrFail($id);
            abort_if((int) $membership->user_id === (int) auth()->id(), 403);
            MembershipRefused::unless($membership->status === 'active' && ! $membership->is_home_office, 409, 'staff_end_only_additional');
            $user = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($membership->user_id);
            $this->cleared($user, $office, 'staff_end_not_clear');
            $membership->update(['status' => 'inactive']);
        });
    }

    public function claim(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $office = $this->adminOffice();
            $sources = OfficeMembership::where('user_id', $userId)->where('is_home_office', true)->get();
            MembershipRefused::unless($sources->count() === 1 && $sources->first()->status === 'released', 409, 'staff_not_ready_to_transfer');
            $source = $sources->first();
            MembershipRefused::when((int) $source->location_id === $office, 409, 'staff_not_ready_to_transfer');
            $officeIds = [$office, (int) $source->location_id];
            sort($officeIds);
            foreach ($officeIds as $id) {
                $profile = app(RoleTransfer::class)->lockOffice($id, $id === $office);
                if ($id === $office) {
                    $this->adminOffice($profile);
                }
            }
            $user = User::withoutGlobalScopes()->whereNull('deleted_at')->where('activated', true)->lockForUpdate()->findOrFail($userId);
            $company = Location::withoutGlobalScopes()->findOrFail($office)->company_id;
            abort_unless((int) $user->company_id === (int) $company, 404);
            $source = OfficeMembership::whereKey($source->id)->lockForUpdate()->firstOrFail();
            MembershipRefused::unless($source->is_home_office && $source->status === 'released', 409, 'staff_not_ready_to_transfer');
            $this->cleared($user, $source->location_id, 'staff_welcome_not_clear');
            $source->update(['is_home_office' => false]);
            OfficeMembership::updateOrCreate(['user_id' => $userId, 'location_id' => $office],
                ['status' => 'active', 'is_home_office' => true, 'valid_until' => null, 'approved_by_user_id' => auth()->id(), 'approved_at' => now()]);
            $user->location_id = $office;
            $user->saveQuietly();
            app(MembershipNotices::class)->record('employee_claimed', $office, [$userId, auth()->id(), LocationProfile::where('location_id', $source->location_id)->value('office_admin_id')]);
        });
    }

    /**
     * An emergency change, after the national review has been confirmed. A release ends one office only (D1)
     * and can also remove the person's roles there (D2); "strip_roles" removes every role and keeps memberships.
     * A released home office stays the home office, so another office in the ministry can welcome the person.
     */
    public function override(int $userId, string $type, ?int $office, bool $removeOfficeRoles, string $reason, ?string $reference): void
    {
        abort_unless(in_array($type, ['force_release', 'strip_roles'], true), 422);
        DB::transaction(function () use ($userId, $type, $office, $removeOfficeRoles, $reason, $reference) {
            $user = User::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($userId);
            $details = ['office_id' => $office];
            if ($type === 'force_release') {
                $membership = $office ? OfficeMembership::where('user_id', $userId)->where('location_id', $office)->lockForUpdate()->first() : null;
                MembershipRefused::unless($membership && in_array($membership->status, self::OPEN, true), 409, 'override_office_not_open', 'location_id');
                $membership->update(['status' => 'released', 'destination_location_id' => null]);
                $details['home'] = (bool) $membership->is_home_office;
                $affected = [$office];
                $details['roles_removed'] = $removeOfficeRoles ? $this->removeRoles($userId, $office) : 0;
            } else {
                $affected = $this->roleOffices($userId);
                $details['roles_removed'] = $this->removeRoles($userId, null);
            }
            foreach ($affected as $location) {
                app(MembershipNotices::class)->record('membership_override', (int) $location,
                    [$userId, LocationProfile::where('location_id', $location)->value('office_admin_id'), auth()->id()]);
            }
            OverrideAuditLog::create(['target_user_id' => $userId, 'override_type' => $type, 'reason' => $reason,
                'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null, 'details' => $details,
                'executed_by' => auth()->id(), 'old_location_id' => $office ?? $user->location_id]);
        });
    }

    /** @return int[] offices where the person holds a role, an administrator slot or unexpired cover */
    public function roleOffices(int $userId): array
    {
        return collect()
            ->merge(OfficeResponsibility::where('user_id', $userId)->pluck('location_id'))
            ->merge(LocationProfile::where('office_admin_id', $userId)->pluck('location_id'))
            ->merge(DB::table('gov_access_grants')->where('user_id', $userId)->where('expires_at', '>', now())->pluck('location_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Removes the person's roles at one office (or everywhere) and cancels handovers that can no longer finish. */
    private function removeRoles(int $userId, ?int $office): int
    {
        $at = fn ($query) => $office ? $query->where('location_id', $office) : $query;
        $locations = $office ? [$office] : $this->roleOffices($userId);
        $removed = $at(OfficeResponsibility::where('user_id', $userId))->delete();
        $removed += $at(DB::table('gov_access_grants')->where('user_id', $userId))->delete();
        $removed += $at(LocationProfile::where('office_admin_id', $userId))->update(['office_admin_id' => null]);
        $at(RoleHandshake::where('status', 'pending')->where(fn ($q) => $q->where('outgoing_user_id', $userId)->orWhere('incoming_user_id', $userId)))
            ->update(['status' => 'cancelled']);
        foreach ($locations as $location) {
            Cache::forget("gov_user_role_{$userId}_loc_{$location}");
        }

        return (int) $removed;
    }
}
