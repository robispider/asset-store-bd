<?php

namespace GovStore\UserOnboarding\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Services\OfficeMembershipService;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Services\OfficeRequestIntake;
use GovStore\UserOnboarding\Models\UserOnboarding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserOnboardingService
{
    public function assignToOffice(int $onboardingId, int $locationId): void
    {
        DB::transaction(function () use ($onboardingId, $locationId) {
            $access = app(OnboardingAccess::class);
            $actor = $access->actor();
            // Match membership/organization lock order: office, profile, user, queue.
            $office = Location::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($locationId);
            $profile = LocationProfile::where('location_id', $locationId)->lockForUpdate()->firstOrFail();
            $access->office($actor, $office, $profile);
            app(OfficeRequestIntake::class)->assertOpen([$locationId], true);
            $hint = $access->queue($actor)->findOrFail($onboardingId);
            $user = User::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($hint->user_id);
            $item = $access->queue($actor)->lockForUpdate()->findOrFail($onboardingId);
            abort_unless($item->status === 'WAITING', 409, __('govonboard::onboard.stale'));
            abort_unless(! $user->company_id || (int) $user->company_id === (int) $office->company_id, 404);
            abort_unless(! $user->location_id || (int) $user->location_id === $locationId, 409, __('govonboard::onboard.already_assigned'));
            $memberships = OfficeMembership::where('user_id', $user->id)->lockForUpdate()->get();
            // A trusted importer may already have projected this exact first membership.
            abort_if($memberships->contains(fn ($m) => (int) $m->location_id !== $locationId || $m->status !== 'active' || ! $m->is_home_office
                || ($m->valid_until && $m->valid_until->lt(today()))), 409, __('govonboard::onboard.already_assigned'));
            $before = $item->only(['status', 'owner_type', 'owner_id']);
            app(OfficeMembershipService::class)->grantMembership($user->id, $locationId, true);
            $membership = OfficeMembership::where('user_id', $user->id)->where('location_id', $locationId)->firstOrFail();
            // Preserve activation, credentials and permissions. This assigns the first office only.
            $user->forceFill(['location_id' => $locationId, 'company_id' => $office->company_id])->saveQuietly();
            if ($office->company_id) {
                $user->companies()->syncWithoutDetaching([$office->company_id]);
            }
            $item->update(['status' => 'COMPLETED', 'assigned_membership_id' => $membership->id]);
            $this->record($item, 'assigned', $actor->id, null, $before, $locationId, [$profile->office_admin_id]);
        });
    }

    public function decide(int $id, string $action, string $reason, ?int $ownerId = null, ?string $ownerType = null): void
    {
        $reason = trim($reason);
        Validator::make(compact('action', 'reason', 'ownerId', 'ownerType'), [
            'action' => 'required|in:cancel,reject,reassign,reopen', 'reason' => 'required|string|min:5|max:1000',
            'ownerId' => 'required_if:action,reassign|nullable|integer|min:1',
            'ownerType' => 'required_if:action,reassign|nullable|in:OFFICE_ADMIN,COMPANY_ADMIN,ICT_OFFICER',
        ])->validate();
        DB::transaction(function () use ($id, $action, $reason, $ownerId, $ownerType) {
            $access = app(OnboardingAccess::class);
            $actor = $access->actor();
            $hint = $access->queue($actor)->findOrFail($id);
            $user = User::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($hint->user_id);
            $item = $access->queue($actor)->lockForUpdate()->findOrFail($id);
            abort_unless($item->status === ($action === 'reopen' ? 'CANCELLED' : 'WAITING'), 409, __('govonboard::onboard.stale'));
            abort_if($item->assigned_membership_id || $user->location_id || OfficeMembership::where('user_id', $user->id)->lockForUpdate()->first(), 409, __('govonboard::onboard.already_assigned'));
            $before = $item->only(['status', 'owner_type', 'owner_id']);
            $extra = [$item->owner_id];
            if ($action === 'reassign') {
                abort_if((int) $item->owner_id === $ownerId && $item->owner_type === $ownerType, 409);
                $access->manager($item, $user, $ownerId, $ownerType);
                $item->owner_id = $ownerId;
                $item->owner_type = $ownerType;
            } else {
                $item->status = $action === 'reopen' ? 'WAITING' : 'CANCELLED';
            }
            $item->save();
            $this->record($item, $action, $actor->id, $reason, $before, null, $extra);
        });
    }

    /** Durable notices and history commit together; no SMTP in business transactions. */
    public function record(UserOnboarding $item, string $event, ?int $actor, ?string $reason = null, array $before = [], ?int $office = null, array $extra = []): void
    {
        $eventId = (string) Str::uuid();
        DB::table('gov_onboarding_events')->insert(['event_id' => $eventId, 'onboarding_id' => $item->id,
            'actor_id' => $actor, 'event_key' => $event, 'reason' => $reason,
            'before_state' => json_encode($before), 'after_state' => json_encode($item->only(['status', 'owner_type', 'owner_id', 'assigned_membership_id'])), 'created_at' => now()]);
        foreach (array_unique(array_filter([$item->user_id, $item->creator_user_id, $item->owner_id, ...$extra])) as $user) {
            DB::table('gov_onboarding_notices')->insert(['event_id' => $eventId, 'onboarding_id' => $item->id,
                'user_id' => $user, 'event_key' => $event, 'location_id' => $office, 'created_at' => now()]);
        }
    }
}
