<?php

namespace GovStore\Organization\Services;

use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\OrganizationActivityLog;

class OfficeReadinessService
{
    /**
     * Exception-safe checklist checker. If a profile doesn't exist, it evaluates
     * gracefully as unconfigured instead of throwing model crashes.
     */
    public function evaluateAndTransition(int $locationId): array
    {
        // Safe lookups using first() instead of firstOrFail()
        $profile = LocationProfile::where('location_id', $locationId)->first();
        $roles = LocationRole::where('location_id', $locationId)->first();
        
        $usersCount = User::where('location_id', $locationId)->count();

        // Evaluate checklist boolean states safely
        $checklist = [
            'has_office_admin'     => $profile && !is_null($profile->office_admin_id),
            'has_primary_approver' => $roles && !is_null($roles->primary_approver_id),
            'has_storekeeper'      => $roles && !is_null($roles->storekeeper_id),
            'has_users'            => $usersCount > 0,
        ];

        $isOperational = !in_array(false, $checklist, true);

        // Only attempt state transitions if a profile exists on disk
        if ($profile) {
            $oldStatus = $profile->lifecycle_status;
            $newStatus = $isOperational ? 'operational' : 'configured';

            if ($oldStatus !== $newStatus) {
                $profile->update(['lifecycle_status' => $newStatus]);

                // Log status changes
                OrganizationActivityLog::create([
                    'location_id' => $locationId,
                    'performed_by' => auth()->id() ?: ($profile->office_admin_id ?: 1),
                    'event_type' => 'status_changed',
                    'details' => [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ]
                ]);
            }
        }

        return [
            'is_operational' => $isOperational,
            'checklist' => $checklist,
            'users_count' => $usersCount
        ];
    }
}