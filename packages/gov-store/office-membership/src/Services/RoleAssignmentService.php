<?php

namespace GovStore\OfficeMembership\Services;

use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\RoleAssignment;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    /** Office-admin review grants a responsibility without transferring another person's role. */
    public function grantOfficeAccess(int $locationId, string $role, int $userId, int $actorId, ?string $expiresAt, int $requestId): void
    {
        if (! in_array($role, ['storekeeper', 'primary_approver', 'final_approver', 'committee_registrar'], true)) {
            throw new \InvalidArgumentException('Unsupported office responsibility.');
        }
        DB::transaction(function () use ($locationId, $role, $userId, $actorId, $expiresAt, $requestId) {
            if ($expiresAt) {
                DB::table('gov_access_grants')->updateOrInsert(['location_id' => $locationId, 'user_id' => $userId, 'role_slug' => $role],
                    ['expires_at' => $expiresAt, 'access_request_id' => $requestId, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                OfficeResponsibility::firstOrCreate(['location_id' => $locationId, 'user_id' => $userId, 'role_slug' => $role]);
            }
            Cache::forget("gov_user_role_{$userId}_loc_{$locationId}");
            OrganizationActivityLog::create(['location_id' => $locationId, 'performed_by' => $actorId, 'event_type' => 'roles_configured',
                'details' => ['access_request_id' => $requestId, 'user_id' => $userId, 'role' => $role, 'expires_at' => $expiresAt]]);
        });
    }

    public function proposeTransfer(int $locationId, string $roleType, int $fromUserId, int $toUserId): RoleAssignment
    {
        return app(RoleTransfer::class)->propose(RoleAssignment::class, $locationId, $roleType, $fromUserId, $toUserId);
    }

    public function acceptTransfer(int $id, int $userId): void
    {
        app(RoleTransfer::class)->transition(RoleAssignment::class, $id, $userId, 'accept');
    }

    public function rejectTransfer(int $id, int $userId): void
    {
        app(RoleTransfer::class)->transition(RoleAssignment::class, $id, $userId, 'reject');
    }

    public function cancelTransfer(int $id, int $userId): void
    {
        app(RoleTransfer::class)->transition(RoleAssignment::class, $id, $userId, 'cancel');
    }
}
