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
            $guard = app(OfficeAdministration::class);
            $actor = $guard->actor($executorId);
            $location = \App\Models\Location::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($locationId);
            $profile = \GovStore\Organization\Models\LocationProfile::where('location_id', $locationId)->lockForUpdate()->firstOrFail();
            $guard->office($actor, $locationId);
            abort_unless(in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true), 409);
            
            // 2. Map of incoming form keys to our responsibility registry slugs
            $mappings = [
                'primary_approver_id' => 'primary_approver',
                'final_approver_id'   => 'final_approver',
                'storekeeper_id'      => 'storekeeper',
            ];

            foreach ($mappings as $field => $slug) {
                if (! empty($roles[$field])) {
                    $guard->assignee((int) $roles[$field], $locationId, $location->company_id);
                }
            }
            // Preserve responsibilities owned by other workflows (e.g. registrar).
            OfficeResponsibility::where('location_id', $locationId)->whereIn('role_slug', array_values($mappings))->delete();

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
            app(OfficeReadinessService::class)->evaluateAndTransition($locationId, $executorId);
        });
    }
}
