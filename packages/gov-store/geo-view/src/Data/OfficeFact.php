<?php

namespace GovStore\GeoView\Data;

/**
 * One office's values (grain=office). Ratio layers carry [numerator, denominator] so roll-ups are
 * Σnum/Σden, never an average of percentages (plan §6.3).
 */
final class OfficeFact
{
    /**
     * @param  array<string, int|float|array{0:int|float,1:int|float}|null>  $values
     * @param  int|null  $areaId  the office's GeoAreaId when the source knows it; otherwise the geo view looks it up
     */
    public function __construct(
        public readonly int $locationId,
        public readonly array $values,
        public readonly ?string $name = null,
        public readonly ?string $link = null,
        public readonly ?int $areaId = null,
    ) {}

    public function value(string $layer): int|float|array|null
    {
        return $this->values[$layer] ?? null;
    }
}
