<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OfficeMembershipService
{
    /**
     * Retrieves all active users assigned to a specific office building.
     */
    public function getActiveMembers(int $locationId): Collection
    {
        return User::whereHas('memberships', function ($q) use ($locationId) {
            $q->where('location_id', $locationId)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()));
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
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()))
            ->orderBy('is_home_office', 'desc')
            ->get();
    }

    /**
     * Core Method: Authorizes an employee to access a specific office.
     */
    public function grantMembership(int $userId, int $locationId, bool $isHome = false, $validUntil = null): void
    {
        DB::transaction(function () use ($userId, $locationId, $isHome, $validUntil) {
            User::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($userId);
            if ($isHome) {
                // A user can only have exactly ONE HR Home Office base. Reset other home office tags for this user.
                OfficeMembership::where('user_id', $userId)->update(['is_home_office' => false]);
            }

            OfficeMembership::updateOrCreate(
                ['user_id' => $userId, 'location_id' => $locationId],
                [
                    'is_home_office' => $isHome,
                    'status' => 'active',
                    'valid_until' => $validUntil ?: null,
                ]
            );
        });
    }

    /**
     * Revokes access to an office building.
     */
    public function revokeMembership(int $userId, int $locationId): void
    {
        DB::transaction(function () use ($userId, $locationId) {
            $membership = OfficeMembership::where('user_id', $userId)->where('location_id', $locationId)->lockForUpdate()->firstOrFail();
            $membership->update(['status' => 'inactive', 'is_home_office' => false]);
        });
    }
}
