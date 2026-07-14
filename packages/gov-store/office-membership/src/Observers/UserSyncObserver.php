<?php

namespace GovStore\OfficeMembership\Observers;

use App\Models\User;
use GovStore\OfficeMembership\Services\LegacyUserSynchronizationService;

class UserSyncObserver
{
    protected LegacyUserSynchronizationService $syncService;

    public function __construct(LegacyUserSynchronizationService $syncService)
    {
        $this->syncService = $syncService;
    }

    public function created(User $user)
    {
        $this->syncService->handleNewUser($user);
    }

    public function updated(User $user)
    {
        if ($user->wasChanged('location_id')) {
            $this->syncService->handleUpdatedUser($user);
        }
    }
}