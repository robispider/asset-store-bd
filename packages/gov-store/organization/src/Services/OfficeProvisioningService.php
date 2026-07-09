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
     * Fully defensive: uses null-coalescing to prevent PHP 8.2+ Undefined Array Key errors.
     */
    public function provisionOffice(array $data, int $executorId): Location
    {
        $geoService = app(GeoAreaService::class);
        $user = \App\Models\User::findOrFail($executorId);

        $geoAreaId = (int)($data['geo_area_id'] ?? 0);

        // 1. SECURITY BOUNDARY CHECK (Enforce geographical boundary for non-superusers)
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            $jurisdiction = IctJurisdiction::where('user_id', $user->id)->firstOrFail();
            if (!$geoService->isWithinBoundary($jurisdiction->geo_area_id, $geoAreaId)) {
                throw new Exception("Access Denied: The chosen territory lies outside of your assigned geographical jurisdiction.");
            }
        }

        // 2. CONTEXTUAL DUPLICATE PREVENTION PRE-CHECK
        $companyId = $data['company_id'] ?? null;
        if (!empty($companyId)) {
            $duplicateExists = Location::where('company_id', $companyId)
                ->whereHas('profile', function($query) use ($geoAreaId) {
                    $query->where('geo_area_id', $geoAreaId);
                })->exists();

            if ($duplicateExists) {
                session()->flash('duplicate_warning', 'Notice: An office belonging to this Department/Ministry is already registered within this geographic territory.');
            }
        }

        return DB::transaction(function () use ($data, $executorId, $geoAreaId) {
            
            $existingId = $data['existing_location_id'] ?? null;
            $name = $data['name'] ?? null;

            // 3. IDENTITY CHECK: If onboarding a legacy location, reload it. Otherwise create fresh.
            if (!empty($existingId)) {
                $location = Location::findOrFail($existingId);
                if (!empty($name)) {
                    $location->update(['name' => $name]);
                }
            } else {
                $location = Location::create([
                    'name' => $name,
                    'parent_id' => $data['parent_id'] ?? null,
                    'company_id' => $data['company_id'] ?? null,
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'country' => 'Bangladesh',
                ]);
            }

            // Sync structural attributes on change safely
            $location->update([
                'parent_id'  => $data['parent_id'] ?? $location->parent_id,
                'company_id' => $data['company_id'] ?? $location->company_id,
                'city'       => $data['city'] ?? $location->city,
                'state'      => $data['state'] ?? $location->state,
            ]);

            // 4. Create active Location Profile
            LocationProfile::create([
                'location_id' => $location->id,
                'geo_area_id' => $geoAreaId,
                'office_admin_id' => $data['office_admin_id'] ?? null,
                'lifecycle_status' => 'provisioned',
            ]);

            // 5. Instantiate Roles
            LocationRole::updateOrCreate(['location_id' => $location->id]);

            // 6. Log change
            OrganizationActivityLog::create([
                'location_id' => $location->id,
                'performed_by' => $executorId,
                'event_type' => 'office_created',
                'details' => [
                    'name' => $location->name,
                    'geo_area_id' => $geoAreaId
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