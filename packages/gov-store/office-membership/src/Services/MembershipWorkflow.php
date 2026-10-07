<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Models\EmployeeVerificationToken;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class MembershipWorkflow
{
    public function join(string $code): void
    {
        DB::transaction(function () use ($code) {
            $initial = LocationProfile::where('invitation_code', strtoupper(trim($code)))->firstOrFail();
            $profile = app(RoleTransfer::class)->lockOffice($initial->location_id);
            abort_unless($profile->invitation_code === strtoupper(trim($code)) && $profile->invitation_code_expires_at?->isFuture(), 422);
            User::withoutGlobalScopes()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $existing = OfficeMembership::where('user_id', auth()->id())->where('location_id', $profile->location_id)->lockForUpdate()->first();
            abort_if($existing && ! in_array($existing->status, ['inactive', 'rejected', 'released'], true), 409);
            abort_if($existing?->is_home_office, 409);
            OfficeMembership::updateOrCreate(['user_id' => auth()->id(), 'location_id' => $profile->location_id],
                ['status' => 'pending', 'is_home_office' => false, 'valid_until' => null]);
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
                ->where('activated', true)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
            $token = EmployeeVerificationToken::where('user_id', $user->id)->where('token', strtoupper(trim($code)))->lockForUpdate()->firstOrFail();
            abort_unless($token->isValid(), 422);
            abort_if(OfficeMembership::where('user_id', $user->id)->where('is_home_office', true)->whereIn('status', ['release_requested', 'released'])->exists(), 409);
            abort_if(OfficeMembership::where('user_id', $user->id)->where('location_id', $office)->where('status', 'active')->exists(), 409);
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

    private function cleared(User $user, int $office): void
    {
        $engine = app(ClearanceEngine::class);
        abort_unless($engine->isCleared($engine->runChecks($user, $office)), 409, __('office_membership::member.membership_clearance_failed'));
    }

    public function requestRelease(int $id): void
    {
        DB::transaction(function () use ($id) {
            $initial = OfficeMembership::where('user_id', auth()->id())->findOrFail($id);
            app(RoleTransfer::class)->lockOffice($initial->location_id, false);
            $membership = app(RoleTransfer::class)->activeMember((int) auth()->id(), $initial->location_id);
            $this->cleared(auth()->user(), $membership->location_id);
            $membership->update(['status' => 'release_requested']);
        });
    }

    public function decide(int $id, string $decision): void
    {
        abort_unless(in_array($decision, ['approve', 'reject', 'release'], true), 422);
        DB::transaction(function () use ($id, $decision) {
            $office = $this->adminOffice();
            $profile = app(RoleTransfer::class)->lockOffice($office, $decision === 'approve');
            $this->adminOffice($profile);
            $membership = OfficeMembership::where('location_id', $office)->lockForUpdate()->findOrFail($id);
            abort_if((int) $membership->user_id === (int) auth()->id(), 403);
            $user = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($membership->user_id);
            $expected = $decision === 'release' ? 'release_requested' : 'pending';
            abort_unless($membership->status === $expected, 409);
            if ($decision === 'release') {
                $this->cleared($user, $office);
            }
            $membership->update(['status' => match ($decision) {
                'release' => 'released', 'approve' => 'active', 'reject' => 'rejected'
            },
                'is_home_office' => $decision === 'release' && $membership->is_home_office,
                'approved_by_user_id' => auth()->id(), 'approved_at' => now()]);
        });
    }

    public function claim(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $office = $this->adminOffice();
            $sources = OfficeMembership::where('user_id', $userId)->where('is_home_office', true)->get();
            abort_unless($sources->count() === 1 && $sources->first()->status === 'released', 409);
            $source = $sources->first();
            abort_if((int) $source->location_id === $office, 409);
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
            abort_unless($source->is_home_office && $source->status === 'released', 409);
            $this->cleared($user, $source->location_id);
            $source->update(['is_home_office' => false]);
            OfficeMembership::updateOrCreate(['user_id' => $userId, 'location_id' => $office],
                ['status' => 'active', 'is_home_office' => true, 'valid_until' => null, 'approved_by_user_id' => auth()->id(), 'approved_at' => now()]);
            $user->location_id = $office;
            $user->saveQuietly();
            app(MembershipNotices::class)->record('employee_claimed', $office, [$userId, auth()->id(), LocationProfile::where('location_id', $source->location_id)->value('office_admin_id')]);
        });
    }
}
