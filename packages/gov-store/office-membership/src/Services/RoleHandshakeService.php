<?php

namespace GovStore\OfficeMembership\Services;

use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\DB;
use Exception;

class RoleHandshakeService
{
    public function proposeHandshake(int $locationId, string $roleType, int $fromUserId, int $toUserId): RoleHandshake
    {
        if ($fromUserId === $toUserId) {
            throw new Exception("You cannot delegate a role to yourself.");
        }

        // Prevent duplicate pending requests for the same role
        $existing = RoleHandshake::where('location_id', $locationId)
            ->where('role_type', $roleType)
            ->where('outgoing_user_id', $fromUserId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            throw new Exception("You already have a pending handover proposal in flight for this role.");
        }

        return RoleHandshake::create([
            'location_id' => $locationId,
            'role_type' => $roleType,
            'incoming_user_id' => $toUserId,
            'outgoing_user_id' => $fromUserId,
            'status' => 'pending'
        ]);
    }

    public function acceptHandshake(int $handshakeId, int $userId): void
    {
        DB::transaction(function () use ($handshakeId, $userId) {
            $handshake = RoleHandshake::where('incoming_user_id', $userId)
                                      ->where('status', 'pending')
                                      ->findOrFail($handshakeId);

            $locId = $handshake->location_id;
            $roleType = $handshake->role_type;

            // 1. Shift the actual administrative role pointers
            if ($roleType === 'office_admin') {
                $profile = LocationProfile::where('location_id', $locId)->firstOrFail();
                $profile->update(['office_admin_id' => $userId]);
            } else {
                $roles = LocationRole::firstOrCreate(['location_id' => $locId]);
                $column = $roleType . '_id'; // primary_approver_id, final_approver_id, storekeeper_id
                $roles->update([$column => $userId]);
            }

            // 2. Terminate any other pending handshakes for this same role
            RoleHandshake::where('location_id', $locId)
                ->where('role_type', $roleType)
                ->where('id', '!=', $handshakeId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            // 3. Mark the active handshake as accepted
            $handshake->update(['status' => 'accepted']);

            // 4. Log the administrative sign-off in the immutable activity log
            OrganizationActivityLog::create([
                'location_id' => $locId,
                'performed_by' => $userId,
                'event_type' => 'roles_configured',
                'details' => [
                    'message' => "Role Handshake completed. Swapped role: {$roleType} from user ID {$handshake->outgoing_user_id} to user ID {$userId}"
                ]
            ]);
        });
    }

    public function rejectHandshake(int $handshakeId, int $userId): void
    {
        $handshake = RoleHandshake::where('incoming_user_id', $userId)
                                  ->where('status', 'pending')
                                  ->findOrFail($handshakeId);
                                    
        $handshake->update(['status' => 'rejected']);
    }

    public function cancelHandshake(int $handshakeId, int $userId): void
    {
        $handshake = RoleHandshake::where('outgoing_user_id', $userId)
                                  ->where('status', 'pending')
                                  ->findOrFail($handshakeId);
                                    
        $handshake->update(['status' => 'cancelled']);
    }
}