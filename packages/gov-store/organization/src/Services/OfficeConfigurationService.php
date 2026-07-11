<?php

namespace GovStore\Organization\Services;

use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\DB;

class OfficeConfigurationService
{
    /**
     * Saves assigned roles to the multi-tenant responsibilities pivot matrix.
     */
    public function saveRoles(int $locationId, array $roles, int $executorId): void
    {
        DB::transaction(function () use ($locationId, $roles, $executorId) {
            
            // 1. Purge existing assigned roles for this location
            OfficeResponsibility::where('location_id', $locationId)->delete();

            // 2. Map of incoming form keys to our responsibility registry slugs
            $mappings = [
                'primary_approver_id' => 'primary_approver',
                'final_approver_id'   => 'final_approver',
                'storekeeper_id'      => 'storekeeper',
            ];

            // 3. Write active assignments to the database
            foreach ($mappings as $formField => $slug) {
                if (!empty($roles[$formField])) {
                    OfficeResponsibility::create([
                        'location_id' => $locationId,
                        'user_id' => (int) $roles[$formField],
                        'role_slug' => $slug
                    ]);
                }
            }

            // 4. Log the administrative configuration update
            OrganizationActivityLog::create([
                'location_id' => $locationId,
                'performed_by' => $executorId,
                'event_type' => 'roles_configured',
                'details' => [
                    'primary_approver_id' => $roles['primary_approver_id'] ?? null,
                    'final_approver_id'   => $roles['final_approver_id'] ?? null,
                    'storekeeper_id'      => $roles['storekeeper_id'] ?? null,
                ]
            ]);

            // 5. Instantly re-evaluate office operational status
            app(OfficeReadinessService::class)->evaluateAndTransition($locationId);
        });
    }
}