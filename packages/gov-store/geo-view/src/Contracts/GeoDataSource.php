<?php

namespace GovStore\GeoView\Contracts;

use GovStore\GeoView\Data\FilterDefinition;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Data\GeoScope;
use GovStore\GeoView\Data\LayerDefinition;
use GovStore\GeoView\Data\OfficeFact;

/**
 * A module's numbers for the geo view (plan §6.1). Registered by the module's provider, resolved lazily.
 * The source owns authorization and scope; the geo view masks the result again (two locks, §6.2).
 */
interface GeoDataSource
{
    public const GRAIN_OFFICE = 'office';

    public const GRAIN_AREA = 'area';

    /** Stable key, e.g. 'tracking.programme'. */
    public function key(): string;

    /** Translation key of the source's name. */
    public function label(): string;

    /** Base ability (gov.can); missing it answers 403. */
    public function ability(): string;

    /** Laravel validation rules for host params (validated before authorize()). */
    public function rules(): array;

    /** Object-level check, e.g. the programme key for this initiative; false answers 404 (never names the object). */
    public function authorize(GeoRequest $request): bool;

    /** Which offices and areas this viewer may see. */
    public function scope(GeoRequest $request): GeoScope;

    /** 'office': the geo view aggregates OfficeFacts; 'area': the source returns area values (areaValues()). */
    public function grain(): string;

    /** @return LayerDefinition[] */
    public function layers(GeoRequest $request): array;

    /** @return FilterDefinition[] */
    public function filters(GeoRequest $request): array;

    /**
     * grain=office: facts already restricted by the source's own domain rules.
     *
     * @param  string[]  $layers
     * @return iterable<OfficeFact>
     */
    public function facts(GeoRequest $request, array $layers): iterable;

    /**
     * Optional side-panel rows for one area; null lets the geo view list the area's offices from facts().
     * Rows: {id, name, area, area_name, value, display?, link?, here?}.
     */
    public function details(GeoRequest $request, int $areaId): ?array;
}
