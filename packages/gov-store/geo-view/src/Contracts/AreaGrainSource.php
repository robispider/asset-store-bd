<?php

namespace GovStore\GeoView\Contracts;

use GovStore\GeoView\Data\GeoRequest;

/** grain=area sources aggregate themselves and answer one value per area (plan §6.1). */
interface AreaGrainSource extends GeoDataSource
{
    /**
     * @param  int[]  $areaIds
     * @return array<int, float|int|null> value per area id; null = no data
     */
    public function areaValues(GeoRequest $request, array $areaIds, string $layer): array;
}
