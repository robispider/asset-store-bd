<?php

namespace GovStore\OfficeMembership\Services;

use GovStore\OfficeMembership\Models\OfficeMembership;
use Illuminate\Support\Collection;
use App\Models\User;

class OfficeMembershipService
{
    /**
     * Retrieves all active users assigned to a specific office building.
     */
    public function getActiveMembers(int $locationId): Collection
    {
        return User::whereHas('memberships', function ($q) use ($locationId) {
            $q->where('location_id', $locationId)->where('status', 'active');
        })->orderBy('first_name')->get();
    }

    /**
     * Retrieves all authorized office locations for a given user.
     */
    public function getUserMemberships(int $userId): Collection
    {
        return OfficeMembership::with('location.company')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('is_home_office', 'desc')
            ->get();
    }

    /**
     * Core Method: Authorizes an employee to access a specific office.
     */
    public function grantMembership(int $userId, int $locationId, bool $isHome = false, $validUntil = null): void
    {
        if ($isHome) {
            // A user can only have exactly ONE HR Home Office base. Reset other home office tags for this user.
            OfficeMembership::where('user_id', $userId)->update(['is_home_office' => false]);
        }

        OfficeMembership::updateOrCreate(
            ['user_id' => $userId, 'location_id' => $locationId],
            [
                'is_home_office' => $isHome,
                'status' => 'active',
                'valid_until' => $validUntil ?: null
            ]
        );
    }

    /**
     * Revokes access to an office building.
     */
    public function revokeMembership(int $userId, int $locationId): void
    {
        OfficeMembership::where('user_id', $userId)
            ->where('location_id', $locationId)
            ->update(['status' => 'inactive', 'is_home_office' => false]);
    }
}