<?php

namespace GovStore\Organization\Services;

use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\OrganizationActivityLog;

class OfficeReadinessService
{
    /**
     * Runs checklist validation queries on a specific office location.
     * Automatically transitions lifecycle status to 'operational' if all required fields are satisfied.
     */
    public function evaluateAndTransition(int $locationId): array
    {
        $profile = LocationProfile::where('location_id', $locationId)->firstOrFail();
        $roles = LocationRole::where('location_id', $locationId)->first();
        
        // Check if there is at least one local employee assigned to this location inside Snipe-IT's core Users table
        $usersCount = User::where('location_id', $locationId)->count();

        // Required setup checks
        $checklist = [
            'has_office_admin'     => !is_null($profile->office_admin_id),
            'has_primary_approver' => !is_null($roles?->primary_approver_id),
            'has_storekeeper'      => !is_null($roles?->storekeeper_id),
            'has_users'            => $usersCount > 0,
        ];

        // An office is ready only when all 4 critical checks are complete
        $isOperational = !in_array(false, $checklist, true);

        $oldStatus = $profile->lifecycle_status;
        $newStatus = $isOperational ? 'operational' : 'configured';

        if ($oldStatus !== $newStatus) {
            $profile->update(['lifecycle_status' => $newStatus]);

            // Log transition permanently in the immutable activity log
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

        return [
            'is_operational' => $isOperational,
            'checklist' => $checklist,
            'users_count' => $usersCount
        ];
    }
}