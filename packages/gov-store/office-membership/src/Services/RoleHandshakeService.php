<?php

namespace GovStore\OfficeMembership\Services;

use GovStore\OfficeMembership\Models\RoleHandshake;

class RoleHandshakeService
{
    public function proposeHandshake(int $locationId, string $roleSlug, int $fromUserId, int $toUserId): RoleHandshake
    {
        return app(RoleTransfer::class)->propose(RoleHandshake::class, $locationId, $roleSlug, $fromUserId, $toUserId);
    }

    public function acceptHandshake(int $id, int $userId): void
    {
        app(RoleTransfer::class)->transition(RoleHandshake::class, $id, $userId, 'accept');
    }

    public function rejectHandshake(int $id, int $userId): void
    {
        app(RoleTransfer::class)->transition(RoleHandshake::class, $id, $userId, 'reject');
    }

    public function cancelHandshake(int $id, int $userId): void
    {
        app(RoleTransfer::class)->transition(RoleHandshake::class, $id, $userId, 'cancel');
    }
}
