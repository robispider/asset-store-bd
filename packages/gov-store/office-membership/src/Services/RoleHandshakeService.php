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
            throw new Exception(__('office_membership::member.handshake_self_delegate_error'));
        }

        // Check active responsibilities (New Pivot uses role_slug)
        $holdsRole = OfficeResponsibility::where('location_id', $locationId)
            ->where('user_id', $fromUserId)
            ->where('role_slug', $roleSlug)
            ->exists();

        if (!$holdsRole) {
            throw new Exception(__('office_membership::member.handshake_no_role_error'));
        }

        // ANTI-CORRUPTION LAYER: Map domain $roleSlug to legacy DB 'role_type'
        $existing = RoleHandshake::where('location_id', $locationId)
            ->where('role_type', $roleSlug)
            ->where('outgoing_user_id', $fromUserId)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            throw new Exception(__('office_membership::member.handshake_pending_exists'));
        }

        return RoleHandshake::create([
            'location_id' => $locationId,
            'role_type' => $roleSlug, // Map to legacy DB column
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
            
            // Read via the Domain Accessor
            $roleSlug = $handshake->role_slug; 

            OfficeResponsibility::where('location_id', $locId)
                ->where('user_id', $handshake->outgoing_user_id)
                ->where('role_slug', $roleSlug)
                ->delete();

            OfficeResponsibility::create([
                'location_id' => $locId,
                'user_id' => $userId,
                'role_slug' => $roleSlug
            ]);

            // Map to legacy DB column for query
            RoleHandshake::where('location_id', $locId)
                ->where('role_type', $roleSlug) 
                ->where('id', '!=', $handshakeId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $handshake->update(['status' => 'accepted']);

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

    public function rejectHandshake(int $handshakeId, int $userId): void {
        $handshake = RoleHandshake::where('incoming_user_id', $userId)->where('status', 'pending')->findOrFail($handshakeId);
        $handshake->update(['status' => 'rejected']);
    }

    public function cancelHandshake(int $handshakeId, int $userId): void {
        $handshake = RoleHandshake::where('outgoing_user_id', $userId)->where('status', 'pending')->findOrFail($handshakeId);
        $handshake->update(['status' => 'cancelled']);
    }
}