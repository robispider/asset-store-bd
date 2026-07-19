<?php

namespace GovStore\Organization\Services;

use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\OrganizationActivityLog;
use GovStore\GeoAreas\Services\GeoAreaService;
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

        $geoAreaId = (int)($data['geo_area_id'] ?? 0);

        // 1. SECURITY BOUNDARY CHECK
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
                    $location->name = $name;
                }
            } else {
                $location = new Location();
                $location->name = $name;
            }

            // Sync structural attributes
            $location->parent_id  = $data['parent_id'] ?? $location->parent_id;
            $location->company_id = $data['company_id'] ?? $location->company_id;
            $location->city       = $data['city'] ?? $location->city;
            $location->state      = $data['state'] ?? $location->state;
            $location->country    = 'Bangladesh';
            $location->currency   = 'BDT'; // Default currency required by Snipe-IT

            // Attempt to save the core Snipe-IT Location
            if (!$location->save()) {
                // Extract Snipe-IT's internal Watson Validation errors
                $errors = $location->getErrors() ? $location->getErrors()->first() : 'Unknown Snipe-IT validation error.';
                throw new Exception("Failed to save core Snipe-IT Location: " . $errors);
            }

            if (!$location->id) {
                throw new Exception("Critical Error: Location saved but database returned null ID.");
            }

            // 4. Create active Location Profile safely using the verified ID
            LocationProfile::create([
                'location_id' => $location->id,
                'geo_area_id' => $geoAreaId,
                'office_admin_id' => $data['office_admin_id'] ?? null,
                'lifecycle_status' => 'provisioned',
            ]);

            // 5. DEPRECATED TABLE REMOVED: LocationRole::updateOrCreate(...) was removed.
            // Office roles are now handled exclusively by OfficeResponsibility in the membership package.

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