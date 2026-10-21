<?php

namespace GovStore\GeoView\Config;

/**
 * The page's purpose stated once (plan §5.2); each intent fills sensible defaults (resolution layer 2).
 */
final class Intents
{
    public const ALL = ['progress', 'coverage', 'distribution', 'compare', 'locate', 'pick'];

    public static function defaults(string $intent): array
    {
        return match ($intent) {
            // How far along is each area against its target?
            'progress' => [
                'scale' => ['type' => 'sequential', 'steps' => 5, 'domain' => [0, 1]],
                'interaction' => ['click' => 'drill', 'leaf_click' => 'details'],
                'panels' => ['legend' => true, 'details' => 'side', 'table' => 'toggle'],
            ],
            // Where do we have X, and where don't we?
            'coverage' => [
                'scale' => ['type' => 'categorical', 'domain' => [0, 1]],
                'interaction' => ['click' => 'drill', 'leaf_click' => 'details'],
                'panels' => ['legend' => true, 'details' => 'side', 'table' => 'toggle'],
            ],
            // How much X is where?
            'distribution' => [
                'scale' => ['type' => 'quantile', 'steps' => 5, 'domain' => 'auto'],
                'interaction' => ['click' => 'drill', 'leaf_click' => 'details'],
                'panels' => ['legend' => true, 'table' => 'toggle'],
            ],
            // Which areas are above or below a reference?
            'compare' => [
                'scale' => ['type' => 'diverging', 'steps' => 6, 'reference' => 'national'],
                'interaction' => ['click' => 'select', 'leaf_click' => 'select', 'select' => 'multi'],
                'panels' => ['legend' => true, 'details' => 'side'],
            ],
            // Where are these offices?
            'locate' => [
                'scale' => ['type' => 'quantile', 'steps' => 4, 'domain' => 'auto'],
                'interaction' => ['click' => 'drill', 'leaf_click' => 'details'],
                'panels' => ['markers' => true, 'details' => 'side'],
            ],
            // Choose an area (the map as a form input).
            'pick' => [
                'scale' => ['type' => 'none'],
                'interaction' => ['click' => 'select', 'leaf_click' => 'select', 'select' => 'single'],
                'toolbar' => ['layers' => false, 'filters' => false, 'table' => false],
                'panels' => ['legend' => false, 'details' => 'below', 'table' => 'none', 'labels' => true],
            ],
            default => [],
        };
    }
}
