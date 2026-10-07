<?php

namespace GovStore\Organization\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Contracts\MembershipContextResolver;

/** Object checks remain mandatory even during role shadow observation. */
class OfficeAdministration
{
    public function actor(int $id): User
    {
        $actor = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        abort_unless($actor->activated, 403);

        return $actor;
    }

    public function office(User $actor, int $id, bool $territorial = false): LocationProfile
    {
        $query = LocationProfile::where('location_id', $id);
        if (\Illuminate\Support\Facades\DB::transactionLevel()) {
            $query->lockForUpdate();
        }
        $profile = $query->firstOrFail();
        $office = Location::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
        if ($actor->isSuperUser()) {
            return $profile;
        }
        if ($this->withinTerritory($actor, (int) $profile->geo_area_id)) {
            return $profile;
        }
        $context = app(TenantContext::class);
        abort_unless(! $territorial && (int) $profile->office_admin_id === (int) $actor->id
            && $context->locationId === $id && (int) $actor->company_id === (int) $office->company_id
            && app(MembershipContextResolver::class)->hasActiveMembershipAt((int) $actor->id, $id), 404);

        return $profile;
    }

    public function geography(User $actor, int $id): GeoArea
    {
        $geo = GeoArea::findOrFail($id);
        abort_unless($actor->isSuperUser() || $this->withinTerritory($actor, $id), 404);

        return $geo;
    }

    private function withinTerritory(User $actor, int $id): bool
    {
        foreach (IctJurisdiction::where('user_id', $actor->id)->get() as $jurisdiction) {
            $origin = GeoArea::find($jurisdiction->geo_area_id);
            $target = GeoArea::find($id);
            if ($origin?->hid && $target?->hid && app(GeoAreaService::class)->isWithinBoundary((int) $jurisdiction->geo_area_id, $id)) {
                return true;
            }
        }

        return false;
    }

    public function assignee(int $userId, int $officeId, ?int $companyId): User
    {
        // Assignment callers hold the office/profile lock. Lock the recipient and
        // membership rows too so retirement cannot race the assignment check.
        if (\Illuminate\Support\Facades\DB::transactionLevel()) {
            $lockedUser = User::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($userId)->lockForUpdate()->firstOrFail();
            $query = \GovStore\OfficeMembership\Models\OfficeMembership::where('user_id', $userId)
                ->where('location_id', $officeId)->where('status', 'active');
            if (app(\GovStore\TenantScope\Services\SchemaKnowledge::class)->hasColumn(new \GovStore\OfficeMembership\Models\OfficeMembership, 'valid_until')) {
                $query->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()));
            }
            abort_unless($query->lockForUpdate()->first(), 422);
        }
        $user = $lockedUser ?? $this->actor($userId);
        abort_unless($user->activated, 403);
        abort_unless((int) $user->company_id === (int) $companyId
            && app(MembershipContextResolver::class)->hasActiveMembershipAt($userId, $officeId), 422);

        return $user;
    }
}
