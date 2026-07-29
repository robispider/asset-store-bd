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
            'approval_note' => __('office_membership::member.sync_auto_onboarding_note')
        ]);
    }

    /**
     * Safely evaluates if a native location change can be processed as a transfer.
     */
    public function handleUpdatedUser(User $user): void
    {
        $newLocationId = $user->location_id;
        $oldLocationId = $user->getOriginal('location_id');

        // No change made, ignore.
        if ($newLocationId == $oldLocationId) return;

        // Check if the user is bound to an active home office
        $oldMembership = OfficeMembership::where('user_id', $user->id)
            ->where('location_id', $oldLocationId)
            ->where('is_home_office', true)
            ->first();

        // =========================================================================
        // SCENARIO A: Admin is trying to wipe the location to NULL natively
        // =========================================================================
        if (!$newLocationId) {
            if ($oldMembership && $oldMembership->status !== 'released') {
                // FORCE REVERT: Block unauthorized removal
                $user->location_id = $oldLocationId;
                $user->saveQuietly();

                Log::warning("Native User location removal blocked for {$user->username}. Location ID {$oldLocationId} preserved.");
                
                // Fallback translation string if key doesn't exist
                session()->flash('error', __('office_membership::member.sync_removal_blocked_flash') ?: 'Update Reverted: You cannot remove a user\'s location natively. Use the Staff Management module to process a formal release.');
                return;
            }
            return; // Allowed if they genuinely had no active membership anyway
        }

        // =========================================================================
        // SCENARIO B: Admin is trying to change the location natively (Transfer)
        // =========================================================================
        if ($oldMembership) {
            $clearanceResults = $this->clearanceEngine->runChecks($user, $oldLocationId);
            
            if (!$this->clearanceEngine->isCleared($clearanceResults)) {
                // FORCE REVERT: User holds assets or roles.
                $user->location_id = $oldLocationId;
                $user->saveQuietly();

                Log::warning(__('office_membership::member.sync_transfer_blocked_warning', ['username' => $user->username, 'locationId' => $oldLocationId]));
                session()->flash('error', __('office_membership::member.sync_transfer_reverted_flash'));
                return;
            }

            // User is clear. Release the old membership.
            $oldMembership->update([
                'status' => 'released',
                'is_home_office' => false
            ]);
        }

        // Grant the new Home Office membership
        OfficeMembership::updateOrCreate(
            ['user_id' => $user->id, 'location_id' => $newLocationId],
            [
                'status' => 'active',
                'is_home_office' => true,
                'approved_by_user_id' => auth()->id() ?? 1,
                'approved_at' => now(),
                'approval_note' => __('office_membership::member.sync_native_transfer_note')
            ]
        );
    }
}