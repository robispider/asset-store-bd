<?php

namespace GovStore\GeoView\Sources;

use GovStore\GeoView\Contracts\AreaGrainSource;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Data\GeoScope;
use GovStore\GeoView\Data\LayerDefinition;

/**
 * Administrative areas as plain reference data, for the picker intent (plan §4.3): the map as a form input that
 * writes a GeoAreaId. Names and shapes are open data, so every signed-in user may browse every area.
 */
class GeoAreasReferenceSource implements AreaGrainSource
{
    public function key(): string
    {
        return 'geo.areas';
    }

    public function label(): string
    {
        return 'geoview::general.sources.geo_areas';
    }

    public function ability(): string
    {
        return 'geo.areas.view';
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
        return GeoScope::all();
    }

    public function grain(): string
    {
        return self::GRAIN_AREA;
    }

    public function layers(GeoRequest $request): array
    {
        return [new LayerDefinition('areas', __('geoview::general.sources.geo_areas'), 'integer', 'area', applicable: true)];
    }

    public function filters(GeoRequest $request): array
    {
        return [];
    }

    public function facts(GeoRequest $request, array $layers): iterable
    {
        return [];
    }

    public function areaValues(GeoRequest $request, array $areaIds, string $layer): array
    {
        return array_fill_keys($areaIds, 1);
    }

    public function details(GeoRequest $request, int $areaId): ?array
    {
        return [];
    }
}
