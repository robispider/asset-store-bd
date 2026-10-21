<?php

namespace GovStore\Organization\Geo;

use GovStore\GeoView\Contracts\GeoDataSource;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Data\GeoScope;
use GovStore\GeoView\Data\LayerDefinition;
use GovStore\GeoView\Data\OfficeFact;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * The office network on the national geo monitor (geo-monitoring §6 phase 2): offices registered and the share
 * whose location has been verified. Scope is the viewer's tenant scope (geo-monitoring §4.6): national users see
 * every office, ICT officers their jurisdiction, company and office admins their offices.
 */
class OrganizationOfficesGeoSource implements GeoDataSource
{
    public function key(): string
    {
        return 'organization.offices';
    }

    public function label(): string
    {
        return 'geoview::general.sources.organization_offices';
    }

    public function ability(): string
    {
        return 'geo.monitor.view';
    }

    public function rules(): array
    {
        return [];
    }

    public function authorize(GeoRequest $request): bool
    {
        return true;
    }

    public function scope(GeoRequest $request): GeoScope
    {
        $user = $request->user;
        if ($user && method_exists($user, 'isSuperUser') && $user->isSuperUser()) {
            return GeoScope::all();
        }

        return GeoScope::tenant(app(TenantContext::class), false, $user ? (int) $user->getAuthIdentifier() : null);
    }

    public function grain(): string
    {
        return self::GRAIN_OFFICE;
    }

    public function layers(GeoRequest $request): array
    {
        return [
            new LayerDefinition('offices', __('geoview::general.layers.offices'), 'integer', 'sum', __('geoview::general.units.offices')),
            new LayerDefinition('verified_share', __('geoview::general.layers.verified_share'), 'percent', 'ratio'),
        ];
    }

    public function filters(GeoRequest $request): array
    {
        return [];
    }

    public function facts(GeoRequest $request, array $layers): iterable
    {
        if (! Schema::hasTable('gov_location_profiles')) {
            return;
        }
        $verified = Schema::hasColumn('gov_location_profiles', 'geo_area_verified_at');
        $scope = $this->scope($request);
        $query = DB::table('locations')
            ->leftJoin('gov_location_profiles', 'gov_location_profiles.location_id', '=', 'locations.id')
            ->whereNull('locations.deleted_at')
            ->select(array_merge(['locations.id', 'locations.name', 'gov_location_profiles.geo_area_id'], $verified ? ['gov_location_profiles.geo_area_verified_at'] : []));
        if ($scope->offices !== null) {
            $query->whereIn('locations.id', $scope->offices ?: [0]);
        }
        $canOpen = $request->user && Gate::forUser($request->user)->allows('organization.office.manage');
        foreach ($query->orderBy('locations.id')->cursor() as $row) {
            $isVerified = $verified && $row->geo_area_verified_at !== null;
            yield new OfficeFact(
                (int) $row->id,
                ['offices' => 1, 'verified_share' => [$isVerified ? 1 : 0, 1]],
                (string) $row->name,
                $canOpen ? route('gov.org.hub.show', $row->id, false) : null,
                $row->geo_area_id ? (int) $row->geo_area_id : null,
            );
        }
    }

    public function details(GeoRequest $request, int $areaId): ?array
    {
        return null;
    }
}
