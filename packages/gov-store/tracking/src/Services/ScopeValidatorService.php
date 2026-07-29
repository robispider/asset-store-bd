<?php

namespace GovStore\Tracking\Services;

use App\Models\User;
use App\Models\Location;
use GovStore\Tracking\Models\TrackingReference;

class ScopeValidatorService
{
    /**
     * Verify if the user owns/belongs to the Ministry governing the reference.
     */
    public function validateOwnership(TrackingReference $reference, ?int $companyId): bool
    {
        $ownershipScopes = $reference->scopes()->where('dimension', 'OWNERSHIP')->get();

        if ($ownershipScopes->isEmpty()) {
            return true; // No explicit block
        }

        foreach ($ownershipScopes as $scope) {
            if ($scope->target_type === 'Global') {
                return true;
            }
            if ($scope->target_type === 'Company' && (int)$scope->target_id === (int)$companyId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify if the active operational location has rights to view or select this reference.
     */
    public function validateVisibility(TrackingReference $reference, ?int $locationId, ?int $companyId): bool
    {
        $visibilityScopes = $reference->scopes()->where('dimension', 'VISIBILITY')->get();

        if ($visibilityScopes->isEmpty()) {
            return true;
        }

        foreach ($visibilityScopes as $scope) {
            if ($scope->target_type === 'Global') {
                return true;
            }
            if ($scope->target_type === 'Company' && (int)$scope->target_id === (int)$companyId) {
                return true;
            }
            if ($scope->target_type === 'Location' && (int)$scope->target_id === (int)$locationId) {
                return true;
            }
            // GeoArea evaluation integration
            if ($scope->target_type === 'GeoArea' && $locationId) {
                $location = Location::find($locationId);
                if ($location && $location->profile && $this->isLocationInGeoArea($location->profile->geo_area_id, $scope->target_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate whether an item belonging to this reference can physically be deployed to the destination office.
     */
    public function validateApplicability(TrackingReference $reference, int $destinationLocationId): bool
    {
        $applicabilityScopes = $reference->scopes()->where('dimension', 'APPLICABILITY')->get();

        if ($applicabilityScopes->isEmpty()) {
            return true;
        }

        $location = Location::find($destinationLocationId);
        if (!$location) {
            return false;
        }

        foreach ($applicabilityScopes as $scope) {
            if ($scope->target_type === 'Global') {
                return true;
            }
            if ($scope->target_type === 'Location' && (int)$scope->target_id === (int)$destinationLocationId) {
                return true;
            }
            if ($scope->target_type === 'Company' && (int)$scope->target_id === (int)$location->company_id) {
                return true;
            }
            if ($scope->target_type === 'GeoArea') {
                if ($location->profile && $this->isLocationInGeoArea($location->profile->geo_area_id, $scope->target_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Helper logic integrating into geo-areas package to walk down the hierarchy tree using 'hid' paths.
     */
    protected function isLocationInGeoArea(?int $locationGeoAreaId, int $scopeGeoAreaId): bool
    {
        if (!$locationGeoAreaId) {
            return false;
        }

        if ((int)$locationGeoAreaId === (int)$scopeGeoAreaId) {
            return true;
        }

        if (class_exists('GovStore\GeoAreas\Models\GeoArea')) {
            $scopeArea = \GovStore\GeoAreas\Models\GeoArea::find($scopeGeoAreaId);
            $locationArea = \GovStore\GeoAreas\Models\GeoArea::find($locationGeoAreaId);

            if ($scopeArea && $locationArea) {
                // Hierarchical Match via materialized path
                return str_starts_with($locationArea->hid, $scopeArea->hid);
            }
        }

        return false;
    }
}
