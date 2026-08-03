<?php

namespace GovStore\Tracking\Services;

use App\Models\Location;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Organization\Models\LocationProfile;

class ScopeValidatorService
{
    /**
     * Evaluates if a given Tracking Code can be utilized by the requesting Location.
     * Returns an array: ['is_valid' => bool, 'message' => string|null]
     */
    public function validateExecutionScope(TrackingCode $trackingCode, int $locationId): array
    {
        // Only load scopes. Do NOT reload 'initiative' here, because the controller 
        // has already safely loaded it using withoutGlobalScopes() to prevent tenant lockouts.
        $trackingCode->load(['scopes']);
        
        $initiative = $trackingCode->initiative;

        if (!$initiative) {
            return ['is_valid' => false, 'message' => 'Unauthorized. Initiative context is missing or inaccessible.'];
        }

        // Fetch location and its geographical placement
        $location = Location::find($locationId);
        if (!$location) {
            return ['is_valid' => false, 'message' => 'Invalid location ID provided.'];
        }

        $locationProfile = LocationProfile::where('location_id', $locationId)->first();
        $locationGeoId = $locationProfile ? $locationProfile->geo_area_id : null;

        // 1. Evaluate Geographical Scope
        $geoPassed = $this->evaluateGeography($trackingCode, $locationGeoId);
        if (!$geoPassed) {
            return [
                'is_valid' => false,
                'message'  => 'This Tracking Code is strictly scoped to a specific geographical boundary. Your office location is outside this area.'
            ];
        }

        // 2. Evaluate Participants (Organizational) Scope
        $participantPassed = $this->evaluateParticipants($trackingCode, $locationId, $location->company_id, $initiative->owner_company_id);
        if (!$participantPassed) {
            // Safely fetch the owning company name without triggering Eloquent relationship locks
            $companyName = \App\Models\Company::withoutGlobalScopes()->where('id', $initiative->owner_company_id)->value('name') ?? 'the owning Ministry';
            
            return [
                'is_valid' => false,
                'message'  => "This Tracking Code is restricted to participating offices within {$companyName}."
            ];
        }

        return ['is_valid' => true, 'message' => null];
    }

    protected function evaluateGeography(TrackingCode $trackingCode, ?int $locationGeoId): bool
    {
        $geoScope = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
        
        // If no explicit scope or set to Inherit, assume Umbrella default (Valid Anywhere)
        if (!$geoScope || $geoScope->target_type === 'Inherit') {
            return true;
        }

        if (!$locationGeoId) {
            return false; // Strict geo-fencing requires the office to be mapped to a GeoArea
        }

        if ($geoScope->target_type === 'GeoArea' && class_exists('GovStore\GeoAreas\Models\GeoArea')) {
            $allowedArea = \GovStore\GeoAreas\Models\GeoArea::find($geoScope->target_id);
            $officeArea  = \GovStore\GeoAreas\Models\GeoArea::find($locationGeoId);

            if ($allowedArea && $officeArea) {
                // Return true if the office sits anywhere INSIDE the allowed geographical tree
                return str_starts_with($officeArea->hid, $allowedArea->hid);
            }
        }

        return false;
    }

    protected function evaluateParticipants(TrackingCode $trackingCode, int $locationId, ?int $companyId, int $ownerCompanyId): bool
    {
        $partScope = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();

        // If no rule or set to Inherit, fallback to Umbrella Default (Only offices in the owning Ministry)
        if (!$partScope || $partScope->target_type === 'Inherit') {
            return $companyId === $ownerCompanyId;
        }

        // CrossTenant: Allow ANY organization, as long as they passed the Geography check above
        if ($partScope->target_type === 'CrossTenant') {
            return true;
        }

        // SpecificLocations: Explicitly selected warehouses only
        if ($partScope->target_type === 'SpecificLocations') {
            $allowedLocationIds = $trackingCode->scopes
                ->where('dimension', 'PARTICIPANTS')
                ->where('target_type', 'SpecificLocations')
                ->pluck('target_id')
                ->toArray();
                
            return in_array($locationId, $allowedLocationIds);
        }

        return false;
    }
}