<?php

namespace Tests\Feature\GovStore\GeoView;

use GovStore\GeoView\Contracts\AreaGrainSource;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Data\GeoScope;
use GovStore\GeoView\Data\LayerDefinition;

/** Area-grain fixture scoped to the Bogura district: Rajshahi division is only partly in scope. */
class FixtureAreaSource implements AreaGrainSource
{
    public function key(): string
    {
        return 'fixture.areas';
    }

    public function label(): string
    {
        return 'Fixture areas';
    }

    public function ability(): string
    {
        return 'geo.fixture.view';
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
        return GeoScope::of(null, [1246]);
    }

    public function grain(): string
    {
        return self::GRAIN_AREA;
    }

    public function layers(GeoRequest $request): array
    {
        return [new LayerDefinition('assets', 'Assets', 'integer', 'area')];
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
        return array_fill_keys($areaIds, 7);
    }

    public function details(GeoRequest $request, int $areaId): ?array
    {
        return [['id' => 1, 'name' => 'Bogura store', 'area' => 1246, 'area_name' => 'Bogura', 'value' => 7]];
    }
}
