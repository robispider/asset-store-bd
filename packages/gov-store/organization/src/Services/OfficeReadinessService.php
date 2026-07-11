<?php

namespace GovStore\Organization\Services;

use App\Models\User;
use GovStore\Organization\Models\LocationProfile;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\OrganizationActivityLog;

class OfficeReadinessService
{
    /**
     * Exception-safe checklist evaluator.
     */
    public function evaluateAndTransition(int $locationId): array
    {
        $profile = LocationProfile::where('location_id', $locationId)->first();
        
        // Query the new responsibilities pivot matrix directly
        $hasPrimary = OfficeResponsibility::where('location_id', $locationId)
            ->where('role_slug', 'primary_approver')
            ->exists();

        $hasStorekeeper = OfficeResponsibility::where('location_id', $locationId)
            ->where('role_slug', 'storekeeper')
            ->exists();
        
        $usersCount = User::where('location_id', $locationId)->count();

        // Evaluate checklists based on pivot records
        $checklist = [
            'has_office_admin'     => $profile && !is_null($profile->office_admin_id),
            'has_primary_approver' => $hasPrimary,
            'has_storekeeper'      => $hasStorekeeper,
            'has_users'            => $usersCount > 0,
        ];

        $isOperational = !in_array(false, $checklist, true);

        if ($profile) {
            $oldStatus = $profile->lifecycle_status;
            $newStatus = $isOperational ? 'operational' : 'configured';

            if ($oldStatus !== $newStatus) {
                $profile->update(['lifecycle_status' => $newStatus]);

                // Log status transition changes
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