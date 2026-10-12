<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\RoleAssignment;
use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use GovStore\Organization\Services\OfficeRequestIntake;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Both legacy proposals and handshakes use the same ownership and transition rules. */
class RoleTransfer
{
    public const ROLES = ['office_admin', 'storekeeper', 'primary_approver', 'final_approver', 'committee_registrar'];

    public function activeMember(int $userId, int $office, bool $lock = true): OfficeMembership
    {
        $user = User::withoutGlobalScopes()->whereKey($userId)->whereNull('deleted_at')->where('activated', true);
        $membership = OfficeMembership::where('user_id', $userId)->where('location_id', $office)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()));
        if ($lock) {
            $user->lockForUpdate();
            $membership->lockForUpdate();
        }
        $user->firstOrFail();

        return $membership->firstOrFail();
    }

    public function lockOffice(int $office, bool $requireIntake = true): LocationProfile
    {
        Location::withoutGlobalScopes()->whereKey($office)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
        $profile = LocationProfile::where('location_id', $office)->lockForUpdate()->firstOrFail();
        if ($requireIntake) {
            app(OfficeRequestIntake::class)->assertOpen([$office]);
        }

        return $profile;
    }

    private function owner(LocationProfile $profile, string $role, int $user): void
    {
        abort_unless(in_array($role, self::ROLES, true), 422);
        $owns = $role === 'office_admin' ? (int) $profile->office_admin_id === $user
            : (bool) OfficeResponsibility::where('location_id', $profile->location_id)->where('user_id', $user)->where('role_slug', $role)->lockForUpdate()->first();
        abort_unless($owns, 409, __('office_membership::member.transfer_stale'));
    }

    public function propose(string $model, int $office, string $role, int $from, int $to)
    {
        $this->columns($model);
        abort_unless((int) auth()->id() === $from, 403);
        abort_if($from === $to, 422);

        return DB::transaction(function () use ($model, $office, $role, $from, $to) {
            $profile = $this->lockOffice($office);
            foreach (array_unique([min($from, $to), max($from, $to)]) as $user) {
                $this->activeMember($user, $office);
            }
            $this->owner($profile, $role, $from);
            foreach ([RoleHandshake::class, RoleAssignment::class] as $type) {
                abort_if($type::where('location_id', $office)->where('role_type', $role)->where('status', 'pending')->exists(), 409);
            }
            [$sender, $recipient] = $this->columns($model);
            $proposal = $model::create(['location_id' => $office, 'role_type' => $role, $sender => $from, $recipient => $to, 'status' => 'pending']);
            app(MembershipNotices::class)->record('transfer_proposed', $office, [$from, $to], ['role' => $role]);

            return $proposal;
        });
    }

    public function transition(string $model, int $id, int $actor, string $action): void
    {
        abort_unless(in_array($action, ['accept', 'reject', 'cancel'], true), 422);
        abort_unless((int) auth()->id() === $actor, 403);
        DB::transaction(function () use ($model, $id, $actor, $action) {
            [$sender, $recipient] = $this->columns($model);
            $column = $action === 'cancel' ? $sender : $recipient;
            // Bound lookup before locking prevents unrelated users learning proposal details.
            $initial = $model::where($column, $actor)->findOrFail($id);
            $profile = $this->lockOffice($initial->location_id, $action === 'accept');
            $proposal = $model::where($column, $actor)->lockForUpdate()->findOrFail($id);
            abort_unless($proposal->status === 'pending', 409);
            $from = (int) $proposal->$sender;
            $to = (int) $proposal->$recipient;
            if ($action === 'accept') {
                foreach ([min($from, $to), max($from, $to)] as $user) {
                    $this->activeMember($user, $proposal->location_id);
                }
                $this->owner($profile, $proposal->role_type, $from);
                if ($proposal->role_type === 'office_admin') {
                    $profile->update(['office_admin_id' => $to]);
                } else {
                    OfficeResponsibility::where('location_id', $proposal->location_id)->where('user_id', $from)->where('role_slug', $proposal->role_type)->delete();
                    OfficeResponsibility::firstOrCreate(['location_id' => $proposal->location_id, 'user_id' => $to, 'role_slug' => $proposal->role_type]);
                }
                foreach ([$from, $to] as $user) {
                    Cache::forget("gov_user_role_{$user}_loc_{$proposal->location_id}");
                }
                OrganizationActivityLog::create(['location_id' => $proposal->location_id, 'performed_by' => $actor,
                    'event_type' => 'roles_configured', 'details' => ['role' => $proposal->role_type, 'from_user_id' => $from, 'to_user_id' => $to]]);
            }
            $status = match ($action) {
                'accept' => $model === RoleHandshake::class ? 'accepted' : 'completed', 'reject' => 'rejected', 'cancel' => 'cancelled'
            };
            $proposal->update(['status' => $status]);
            app(MembershipNotices::class)->record('transfer_'.$status, $proposal->location_id, [$from, $to], ['role' => $proposal->role_type]);
        });
    }

    private function columns(string $model): array
    {
        abort_unless(in_array($model, [RoleHandshake::class, RoleAssignment::class], true), 422);

        return $model === RoleHandshake::class ? ['outgoing_user_id', 'incoming_user_id'] : ['assigned_by_user_id', 'assigned_user_id'];
    }
}
