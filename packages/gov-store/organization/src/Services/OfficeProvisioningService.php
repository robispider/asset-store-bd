<?php

namespace GovStore\Organization\Services;

use App\Models\Location;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\OrganizationActivityLog;
use GovStore\GeoAreas\Services\GeoAreaService;
use Illuminate\Support\Facades\DB;
use Exception;
use GovStore\Organization\Events\OfficeProvisioned;

class OfficeProvisioningService
{
    /**
     * Creates a core Snipe-IT Location and maps its mandatory geographic profile.
     */
    public function provisionOffice(array $data, int $executorId): Location
    {
        $geoService = app(GeoAreaService::class);
        $guard = app(OfficeAdministration::class);
        $user = $guard->actor($executorId);

        $geoAreaId = (int)($data['geo_area_id'] ?? 0);
        $officeType = $data['office_type'] ?? 'default';
        if (!in_array($officeType, ['default', 'hospital', 'school', 'ict_office'], true)) {
            throw new Exception('The selected office type is not supported.');
        }

        // 1. SECURITY BOUNDARY CHECK
        $guard->geography($user, $geoAreaId);

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

        return DB::transaction(function () use ($data, $executorId, $geoAreaId, $officeType, $guard, $user) {
            
            $existingId = $data['existing_location_id'] ?? null;
            $name = $data['name'] ?? null;

            // 3. IDENTITY CHECK: If onboarding a legacy location, reload it. Otherwise create fresh.
            if (!empty($existingId)) {
                $location = Location::whereNull('deleted_at')->lockForUpdate()->findOrFail($existingId);
                abort_if(LocationProfile::where('location_id', $existingId)->exists(), 409);
                abort_if(isset($data['company_id']) && (int) $data['company_id'] !== (int) $location->company_id, 409);
                if (!empty($name)) {
                    $location->name = $name;
                }
            } else {
                $location = new Location();
                $location->name = $name;
            }

            if (! empty($data['company_id'])) {
                \App\Models\Company::whereNull('deleted_at')->findOrFail((int) $data['company_id']);
            }
            if (! empty($data['parent_id'])) {
                $parent = Location::whereNull('deleted_at')->findOrFail((int) $data['parent_id']);
                $guard->office($user, (int) $parent->id, true);
                abort_unless((int) $parent->company_id === (int) ($data['company_id'] ?? $location->company_id), 422);
                abort_if($existingId && (int) $parent->id === (int) $existingId, 422);
            }
            if (! empty($data['office_admin_id'])) {
                // Assign after membership onboarding; a fresh office has no members yet.
                abort_unless($existingId, 422);
                $guard->assignee((int) $data['office_admin_id'], (int) $existingId, $location->company_id);
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
                'office_type' => $officeType,
                'office_admin_id' => $data['office_admin_id'] ?? null,
                'lifecycle_status' => ! empty($data['office_admin_id']) ? 'configured' : 'provisioned',
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

            $catalogActorId = !empty($data['office_admin_id']) ? (int) $data['office_admin_id'] : null;
            DB::afterCommit(static function () use ($location, $executorId, $officeType, $catalogActorId) {
                event(new OfficeProvisioned($location, $executorId, $officeType, $catalogActorId));
            });

            return $location;
        });
    }

    public function assignOfficeAdmin(int $locationId, ?int $adminId, int $executorId): void
    {
        DB::transaction(function () use ($locationId, $adminId, $executorId) {
            $guard = app(OfficeAdministration::class);
            $actor = $guard->actor($executorId);
            $location = Location::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($locationId);
            $profile = LocationProfile::where('location_id', $locationId)->lockForUpdate()->firstOrFail();
            $guard->office($actor, $locationId);
            abort_unless(in_array($profile->lifecycle_status, OfficeLifecycleService::ACTIVE, true), 409);
            if ($adminId) {
                $guard->assignee($adminId, $locationId, $location->company_id);
            }
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

            if ($adminId) {
                DB::afterCommit(static function () use ($locationId, $executorId, $adminId) {
                    $location = Location::find($locationId);
                    $profile = LocationProfile::where('location_id', $locationId)->first();
                    if ($location && $profile) {
                        event(new OfficeProvisioned(
                            $location,
                            $executorId,
                            $profile->office_type ?: 'default',
                            $adminId
                        ));
                    }
                });
            }
        });
    }
}
