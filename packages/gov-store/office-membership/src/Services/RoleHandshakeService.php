<?php

namespace GovStore\OfficeMembership\Services;

use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\DB;
use Exception;

class RoleHandshakeService
{
    public function proposeHandshake(int $locationId, string $roleSlug, int $fromUserId, int $toUserId): RoleHandshake
    {
        if ($fromUserId === $toUserId) {
            throw new Exception("You cannot delegate a role to yourself.");
        }

        // Verify the outgoing user actually holds this responsibility currently
        $holdsRole = OfficeResponsibility::where('location_id', $locationId)
            ->where('user_id', $fromUserId)
            ->where('role_slug', $roleSlug)
            ->exists();

        if (!$holdsRole) {
            throw new Exception("You cannot hand over a responsibility that you do not hold.");
        }

        // Prevent duplicate pending requests for the same role
        $existing = RoleHandshake::where('location_id', $locationId)
            ->where('role_slug', $roleSlug)
            ->where('outgoing_user_id', $fromUserId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            throw new Exception("You already have a pending handover proposal in flight for this role.");
        }

        return RoleHandshake::create([
            'location_id' => $locationId,
            'role_slug' => $roleSlug,
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
            $roleSlug = $handshake->role_slug;

            // 1. ATOMIC TRANSACTION: Revoke outgoing, grant incoming
            OfficeResponsibility::where('location_id', $locId)
                ->where('user_id', $handshake->outgoing_user_id)
                ->where('role_slug', $roleSlug)
                ->delete();

            OfficeResponsibility::create([
                'location_id' => $locId,
                'user_id' => $userId,
                'role_slug' => $roleSlug
            ]);

            // 2. Terminate any other pending handshakes for this same role
            RoleHandshake::where('location_id', $locId)
                ->where('role_slug', $roleSlug)
                ->where('id', '!=', $handshakeId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            // 3. Mark active handshake as accepted
            $handshake->update(['status' => 'accepted']);

            // 4. Log the administrative sign-off in the activity log
            OrganizationActivityLog::create([
                'location_id' => $locId,
                'performed_by' => $userId,
                'event_type' => 'roles_configured',
                'details' => [
                    'message' => "Responsibility matrix updated. Role '{$roleSlug}' handed over from user ID {$handshake->outgoing_user_id} to user ID {$userId}"
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