<?php

namespace GovStore\Organization\Services;

use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\DB;

class OfficeConfigurationService
{
    public function saveRoles(int $locationId, array $roles, int $executorId): void
    {
        DB::transaction(function () use ($locationId, $roles, $executorId) {
            // Save or update workflow role pointers
            LocationRole::updateOrCreate(
                ['location_id' => $locationId],
                [
                    'primary_approver_id' => $roles['primary_approver_id'] ?: null,
                    'final_approver_id'   => $roles['final_approver_id'] ?: null,
                    'storekeeper_id'      => $roles['storekeeper_id'] ?: null,
                ]
            );

            // Log change event
            OrganizationActivityLog::create([
                'location_id' => $locationId,
                'performed_by' => $executorId,
                'event_type' => 'roles_configured',
                'details' => [
                    'primary_approver_id' => $roles['primary_approver_id'],
                    'final_approver_id'   => $roles['final_approver_id'],
                    'storekeeper_id'      => $roles['storekeeper_id'],
                ]
            ]);

            // Re-evaluate operational checklist instantly
            app(OfficeReadinessService::class)->evaluateAndTransition($locationId);
        });
    }
}