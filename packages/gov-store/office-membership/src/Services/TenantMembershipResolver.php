<?php

namespace GovStore\OfficeMembership\Services;

use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\TenantScope\Contracts\MembershipContextResolver;

class TenantMembershipResolver implements MembershipContextResolver
{
    public function workingMembership(int $userId, mixed $selection): ?array
    {
        $query = OfficeMembership::with(['location' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')])
            ->where('user_id', $userId)->where('status', 'active');
        $this->unexpired($query);
        if ($selection !== null) {
            $membershipId = filter_var($selection, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($membershipId === false) {
                return null;
            }
            $query->whereKey($membershipId);
        } else {
            $query->orderByDesc('is_home_office')->orderBy('created_at')->orderBy('id');
        }
        $membership = $query->first();
        if (! $membership || ! $membership->location) {
            return null;
        }

        return ['id' => (int) $membership->id, 'location_id' => (int) $membership->location_id,
            'company_id' => $membership->location->company_id ? (int) $membership->location->company_id : null,
            'is_home_office' => (bool) $membership->is_home_office];
    }

    public function hasMemberships(int $userId): bool
    {
        return OfficeMembership::where('user_id', $userId)->exists();
    }

    public function responsibilityRoles(int $userId, int $locationId): array
    {
        return OfficeResponsibility::where('user_id', $userId)->where('location_id', $locationId)->pluck('role_slug')->all();
    }

    public function hasActiveMembershipAt(int $userId, int $locationId): bool
    {
        $query = OfficeMembership::where('user_id', $userId)->where('location_id', $locationId)->where('status', 'active');
        $this->unexpired($query);

        return $query->exists();
    }

    private function unexpired($query): void
    {
        if (app(\GovStore\TenantScope\Services\SchemaKnowledge::class)->hasColumn(new OfficeMembership, 'valid_until')) {
            $query->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString()));
        }
    }

    public function responsibilityUserIds(int $locationId, array $roles): array
    {
        return OfficeResponsibility::where('location_id', $locationId)->whereIn('role_slug', $roles)->pluck('user_id')->all();
    }
}
