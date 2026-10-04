<?php

namespace GovStore\OfficeMembership\Services;

use Exception;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Models\RoleAssignment;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    /** Office-admin review grants a responsibility without transferring another person's role. */
    public function grantOfficeAccess(int $locationId, string $role, int $userId, int $actorId, ?string $expiresAt, int $requestId): void
    {
        if (! in_array($role, ['storekeeper', 'primary_approver', 'final_approver'], true)) {
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
        if ($fromUserId === $toUserId) {
            throw new Exception(__('office_membership::member.assignment_self_delegate_error'));
        }

        // Prevent duplicate pending requests for the same role
        $existing = RoleAssignment::where('location_id', $locationId)
            ->where('role_type', $roleType)
            ->where('assigned_by_user_id', $fromUserId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            throw new Exception(__('office_membership::member.assignment_pending_exists'));
        }

        return RoleAssignment::create([
            'location_id' => $locationId,
            'role_type' => $roleType,
            'assigned_user_id' => $toUserId,
            'assigned_by_user_id' => $fromUserId,
            'status' => 'pending',
        ]);
    }

    public function acceptTransfer(int $assignmentId, int $userId): void
    {
        DB::transaction(function () use ($assignmentId, $userId) {
            $assignment = RoleAssignment::where('assigned_user_id', $userId)
                ->where('status', 'pending')
                ->findOrFail($assignmentId);

            $locId = $assignment->location_id;
            $roleType = $assignment->role_type;

            // 1. Update the actual underlying roles
            if ($roleType === 'office_admin') {
                $profile = LocationProfile::where('location_id', $locId)->firstOrFail();
                $profile->update(['office_admin_id' => $userId]);
            } else {
                if (! in_array($roleType, ['storekeeper', 'primary_approver', 'final_approver'], true)) {
                    throw new \InvalidArgumentException('Unsupported office responsibility.');
                }
                OfficeResponsibility::where('location_id', $locId)
                    ->where('user_id', $assignment->assigned_by_user_id)->where('role_slug', $roleType)->delete();
                OfficeResponsibility::firstOrCreate(['location_id' => $locId, 'user_id' => $userId, 'role_slug' => $roleType]);
            }

            // 2. Mark the assignment as completed
            $assignment->update(['status' => 'completed']);
            Cache::forget("gov_user_role_{$userId}_loc_{$locId}");
            Cache::forget("gov_user_role_{$assignment->assigned_by_user_id}_loc_{$locId}");

            // 3. Log the immutable audit event
            OrganizationActivityLog::create([
                'location_id' => $locId,
                'performed_by' => $userId,
                'event_type' => 'roles_configured',
                'details' => [
                    'message' => __('office_membership::member.assignment_audit_message', ['role' => $roleType, 'userId' => $assignment->assigned_by_user_id]),
                ],
            ]);
        });
    }

    public function rejectTransfer(int $assignmentId, int $userId): void
    {
        $assignment = RoleAssignment::where('assigned_user_id', $userId)
            ->where('status', 'pending')
            ->findOrFail($assignmentId);

        $assignment->update(['status' => 'rejected']);
    }

    public function cancelTransfer(int $assignmentId, int $userId): void
    {
        $assignment = RoleAssignment::where('assigned_by_user_id', $userId)
            ->where('status', 'pending')
            ->findOrFail($assignmentId);

        $assignment->delete(); // Hard delete cancelled drafts to keep tables clean
    }
}
