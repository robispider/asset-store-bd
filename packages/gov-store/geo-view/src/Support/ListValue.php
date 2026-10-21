<?php

namespace GovStore\GeoView\Support;

/** @internal Wraps a list so Arr::dot() keeps it as one config value (layers.default, scale.domain…). */
final class ListValue
{
    public function __construct(public readonly array $items) {}
}
