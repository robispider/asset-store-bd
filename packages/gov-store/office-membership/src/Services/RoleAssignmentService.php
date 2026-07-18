<?php

namespace GovStore\OfficeMembership\Services;

use GovStore\OfficeMembership\Models\RoleAssignment;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\DB;
use Exception;

class RoleAssignmentService
{
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
            'status' => 'pending'
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
                $roles = LocationRole::firstOrCreate(['location_id' => $locId]);
                // Ensure the column exists (e.g. primary_approver, final_approver, storekeeper)
                $column = $roleType . '_id';
                $roles->update([$column => $userId]);
            }

            // 2. Mark the assignment as completed
            $assignment->update(['status' => 'completed']);

            // 3. Log the immutable audit event
            OrganizationActivityLog::create([
                'location_id' => $locId,
                'performed_by' => $userId,
                'event_type' => 'roles_configured',
                'details' => [
                    'message' => __('office_membership::member.assignment_audit_message', ['role' => $roleType, 'userId' => $assignment->assigned_by_user_id])
                ]
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