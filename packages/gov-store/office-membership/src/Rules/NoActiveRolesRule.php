<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;

class NoActiveRolesRule implements IClearanceRule
{
    public function getName(): string { return 'Office Responsibility Check'; }

    public function check(User $user, int $locationId): ClearanceResult
    {
        if (!class_exists(\GovStore\Organization\Models\LocationRole::class)) return new ClearanceResult(true);

        $role = \GovStore\Organization\Models\LocationRole::where('location_id', $locationId)
            ->where(function ($q) use ($user) {
                $q->where('primary_approver_id', $user->id)
                  ->orWhere('final_approver_id', $user->id)
                  ->orWhere('storekeeper_id', $user->id);
            })->first();

        if ($role) {
            return new ClearanceResult(false, "You hold an active administrative or storekeeper role. You must delegate this responsibility to another user first.");
        }

        return new ClearanceResult(true, "No blocking administrative roles.");
    }
}