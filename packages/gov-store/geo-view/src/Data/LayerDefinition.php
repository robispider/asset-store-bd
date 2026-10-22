<?php

namespace GovStore\GeoView\Data;

use Closure;
use InvalidArgumentException;

/** A measure a source offers (plan §6.1): key, label, unit, format and how it rolls up. */
final class LayerDefinition
{
    public const FORMATS = ['integer', 'decimal', 'percent', 'money'];

    public const AGGREGATIONS = ['sum', 'count', 'ratio', 'max', 'min', 'area'];

    /**
     * @param  Closure(int|float):string|null  $display  server-side formatter (money uses the module's own strings)
     * @param  int|null  $suppressBelow  small-number suppression: fewer offices than this → 'suppressed' (D6, opt-in)
     * @param  bool|null  $applicable  null = decided from the data (ratio with Σden = 0 is not applicable)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $format = 'integer',
        public readonly string $aggregation = 'sum',
        public readonly ?string $unit = null,
        public readonly ?Closure $display = null,
        public readonly ?int $suppressBelow = null,
        public readonly ?bool $applicable = null,
        public readonly ?string $notApplicableNote = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid layer key [{$key}].");
        }
        if (! in_array($format, self::FORMATS, true) || ! in_array($aggregation, self::AGGREGATIONS, true)) {
            throw new InvalidArgumentException("Layer [{$key}] has an unknown format or aggregation.");
        }
    }

    public function display(int|float|null $value): ?string
    {
        return $value === null || $this->display === null ? null : ($this->display)($value);
    }
}
