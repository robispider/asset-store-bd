<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class LegacyUserSynchronizationService
{
    protected ClearanceEngine $clearanceEngine;

    public function __construct(ClearanceEngine $clearanceEngine)
    {
        $this->clearanceEngine = $clearanceEngine;
    }

    /**
     * Membership approval belongs to onboarding/join, never native account creation.
     */
    public function handleNewUser(User $user): void
    {
        // Native fields describe an account; only reviewed onboarding/join creates membership.
    }

    /** Native edits cannot approve, revive, release or transfer a membership. */
    public function handleUpdatedUser(User $user): void
    {
        $original = $user->getOriginal('location_id');
        if ($user->location_id == $original) {
            return;
        }
        $user->location_id = $original;
        $user->saveQuietly();
        Log::warning('Native office change refused; use the membership workflow.', ['user_id' => $user->id, 'actor_id' => auth()->id()]);
        if (app()->bound('session')) {
            session()->flash('error', __('office_membership::member.sync_transfer_reverted_flash'));
        }
    }
}
