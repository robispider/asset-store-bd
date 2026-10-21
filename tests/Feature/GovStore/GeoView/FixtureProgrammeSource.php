<?php

namespace Tests\Feature\GovStore\GeoView;

use GovStore\GeoView\Contracts\GeoDataSource;
use GovStore\GeoView\Data\FilterDefinition;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Data\GeoScope;
use GovStore\GeoView\Data\LayerDefinition;
use GovStore\GeoView\Data\OfficeFact;

/**
 * Office-grain fixture shaped like the pilot (initiative 30): a regional office on the Dhaka division and
 * district offices in Patuakhali, Bogura and Jamalpur, 55 items each, no office targets. Office 175 is
 * outside the programme: the source "leaks" it so the engine mask (lock 2) is tested.
 */
class FixtureProgrammeSource implements GeoDataSource
{
    public const OFFICES = [171 => 2390, 172 => 3142, 173 => 1246, 174 => 4949, 175 => 2390];

    public const MEMBER = 1;

    public const OUTSIDER = 3;

    public function key(): string
    {
        return 'fixture.programme';
    }

    public function label(): string
    {
        return 'Fixture programme';
    }

    public function ability(): string
    {
        return 'geo.fixture.view';
    }

    public function rules(): array
    {
        return ['initiative' => ['required', 'integer'], 'targets' => ['nullable', 'boolean']];
    }

    public function authorize(GeoRequest $request): bool
    {
        return (int) $request->param('initiative') === 30 && (int) $request->user?->getAuthIdentifier() !== self::OUTSIDER;
    }

    public function scope(GeoRequest $request): GeoScope
    {
        return GeoScope::offices([171, 172, 173, 174]);
    }

    public function grain(): string
    {
        return self::GRAIN_OFFICE;
    }

    public function layers(GeoRequest $request): array
    {
        return [
            new LayerDefinition('delivered_share', 'Delivered against office targets', 'percent', 'ratio', notApplicableNote: 'No office targets in this programme; showing received quantity'),
            new LayerDefinition('received_qty', 'Items received', 'integer', 'sum', 'items'),
            new LayerDefinition('value', 'Value received', 'money', 'sum', display: fn ($v) => '৳'.number_format($v)),
            new LayerDefinition('waiting_offices', 'Offices waiting', 'integer', 'count', suppressBelow: $request->param('targets') ? 2 : null),
        ];
    }

    public function filters(GeoRequest $request): array
    {
        return [new FilterDefinition('code', 'Code', [['value' => 104, 'label' => 'ADP-12'], ['value' => 105, 'label' => 'ADP-13']])];
    }

    public function facts(GeoRequest $request, array $layers): iterable
    {
        $targets = (bool) $request->param('targets');
        foreach (self::OFFICES as $office => $area) {
            if ($request->filter('code') === '104' && $office !== 171) {
                continue;
            }
            yield new OfficeFact($office, [
                'delivered_share' => $targets ? [$office === 171 ? 30 : 10, 100] : [0, 0],
                'received_qty' => 55,
                'value' => 2_575_000,
                'waiting_offices' => $office === 173 ? 1 : 0,
            ], 'Office '.$office, '/gov-store/admin/tracking/initiatives/30/offices/'.$office);
        }
    }

    public function details(GeoRequest $request, int $areaId): ?array
    {
        return null;
    }
}
