<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;
use Illuminate\Support\Facades\Log;

class LegacyUserSynchronizationService
{
    protected ClearanceEngine $clearanceEngine;

    public function __construct(ClearanceEngine $clearanceEngine)
    {
        $this->clearanceEngine = $clearanceEngine;
    }

    /**
     * Safely grants a home membership when a new user is created in Snipe-IT.
     */
    public function handleNewUser(User $user): void
    {
        if (!$user->location_id) return;

        OfficeMembership::create([
            'user_id' => $user->id,
            'location_id' => $user->location_id,
            'is_home_office' => true,
            'status' => 'active',
            'approved_by_user_id' => auth()->id() ?? 1,
            'approved_at' => now(),
            'approval_note' => 'System Auto-Onboarding'
        ]);
    }

    /**
     * Safely evaluates if a native location change can be processed as a transfer.
     */
    public function handleUpdatedUser(User $user): void
    {
        $newLocationId = $user->location_id;
        $oldLocationId = $user->getOriginal('location_id');

        if (!$newLocationId || $newLocationId == $oldLocationId) return;

        // 1. Verify if the user is cleared to leave their old home office
        $oldMembership = OfficeMembership::where('user_id', $user->id)
            ->where('location_id', $oldLocationId)
            ->where('is_home_office', true)
            ->first();

        if ($oldMembership) {
            $clearanceResults = $this->clearanceEngine->runChecks($user, $oldLocationId);
            
            if (!$this->clearanceEngine->isCleared($clearanceResults)) {
                // BLOCK THE TRANSFER: User holds assets or roles.
                // Revert the native location_id to prevent data corruption.
                $user->location_id = $oldLocationId;
                $user->saveQuietly();

                Log::warning("Native User Transfer Blocked: User {$user->username} attempted native location change but holds uncleared assets/roles in Location {$oldLocationId}.");
                session()->flash('error', "Warning: User location update was reverted. The user must return assets and delegate roles before transferring.");
                return;
            }

            // User is clear. Release the old membership.
            $oldMembership->update([
                'status' => 'released',
                'is_home_office' => false
            ]);
        }

        // 2. Grant the new Home Office membership
        OfficeMembership::updateOrCreate(
            ['user_id' => $user->id, 'location_id' => $newLocationId],
            [
                'status' => 'active',
                'is_home_office' => true,
                'approved_by_user_id' => auth()->id() ?? 1,
                'approved_at' => now(),
                'approval_note' => 'Native Admin Transfer'
            ]
        );
    }
}