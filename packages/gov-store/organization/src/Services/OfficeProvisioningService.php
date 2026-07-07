<?php

namespace GovStore\Organization\Services;

use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\LocationRole;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\OrganizationActivityLog;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\GeoAreas\Models\GeoArea;
use Illuminate\Support\Facades\DB;
use Exception;

class OfficeProvisioningService
{
    /**
     * Creates a core Snipe-IT Location and maps its mandatory geographic profile.
     */
    public function provisionOffice(array $data, int $executorId): Location
    {
        $geoService = app(GeoAreaService::class);
        $user = \App\Models\User::findOrFail($executorId);

        // 1. SECURITY BOUNDARY CHECK (Enforce geographical boundary for non-superusers)
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::where('user_id', $user->id)->firstOrFail();
            
            if (!$geoService->isWithinBoundary($jurisdiction->geo_area_id, (int)$data['geo_area_id'])) {
                throw new Exception("Access Denied: The chosen territory lies outside of your assigned geographical jurisdiction.");
            }
        }

        // 2. CONTEXTUAL DUPLICATE PREVENTION PRE-CHECK
        // Warn if an office belonging to the same department already exists inside this Upazila
        if (!empty($data['company_id'])) {
            $duplicateExists = Location::where('company_id', $data['company_id'])
                ->whereHas('profile', function($query) use ($data) {
                    $query->where('geo_area_id', $data['geo_area_id']);
                })->exists();

            if ($duplicateExists) {
                // We write a session trigger so the controller can flag a soft duplicate warning on redirect
                session()->flash('duplicate_warning', 'Notice: An office belonging to this Department/Ministry is already registered within this geographic territory.');
            }
        }

        return DB::transaction(function () use ($data, $executorId) {
            // 3. Create Snipe-IT Location
            $location = Location::create([
                'name' => $data['name'],
                'parent_id' => $data['parent_id'] ?: null,
                'company_id' => $data['company_id'] ?: null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => 'Bangladesh',
            ]);

            // 4. Create Location Profile with MANDATORY geo_area_id
            LocationProfile::create([
                'location_id' => $location->id,
                'geo_area_id' => $data['geo_area_id'],
                'office_admin_id' => $data['office_admin_id'] ?: null,
                'lifecycle_status' => 'provisioned',
            ]);

            // 5. Instantiate Roles container
            LocationRole::create([
                'location_id' => $location->id,
            ]);

            // 6. Log immutable action
            OrganizationActivityLog::create([
                'location_id' => $location->id,
                'performed_by' => $executorId,
                'event_type' => 'office_created',
                'details' => [
                    'name' => $data['name'],
                    'geo_area_id' => (int)$data['geo_area_id'],
                    'company_id' => $data['company_id'] ? (int)$data['company_id'] : null
                ]
            ]);

            return $location;
        });
    }

    public function assignOfficeAdmin(int $locationId, ?int $adminId, int $executorId): void
    {
        DB::transaction(function () use ($locationId, $adminId, $executorId) {
            $profile = LocationProfile::where('location_id', $locationId)->firstOrFail();
            $oldAdminId = $profile->office_admin_id;

            if ($oldAdminId === $adminId) {
                return;
            }

            $profile->update([
                'office_admin_id' => $adminId,
                'lifecycle_status' => $adminId ? 'configured' : 'provisioned'
            ]);

            OrganizationActivityLog::create([
                'location_id' => $locationId,
                'performed_by' => $executorId,
                'event_type' => 'admin_assigned',
                'details' => [
                    'old_admin_id' => $oldAdminId,
                    'new_admin_id' => $adminId
                ]
            ]);
        });
    }
}