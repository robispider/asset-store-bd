<?php

return [
    // Resolution layer 1: engine defaults (plan §5.1). Intents (layer 2) and presets (layer 3) override them.
    'defaults' => [
        'intent' => 'distribution',
        'source' => ['key' => null, 'mode' => 'remote', 'params' => [], 'param_keys' => []],
        'layers' => ['default' => [], 'allow' => '*'],
        'levels' => ['start' => 'auto', 'root' => null, 'min' => 1, 'max' => 4, 'urban' => 'colour', 'flat' => null],
        'interaction' => ['click' => 'drill', 'leaf_click' => 'details', 'wheel' => 'modifier', 'select' => 'single', 'touch' => 'tap-select-tap-drill'],
        'toolbar' => ['zoom' => true, 'reset' => true, 'layers' => true, 'filters' => true, 'search' => false, 'table' => true,
            'export' => false, 'fullscreen' => false, 'help' => true],
        'panels' => ['breadcrumb' => true, 'legend' => true, 'details' => 'side', 'table' => 'toggle', 'labels' => false, 'markers' => false],
        'scale' => ['type' => 'quantile', 'steps' => 5, 'domain' => 'auto'],
        'size' => ['height' => 420, 'aspect' => null],
        'url_state' => true,
        'locked' => [],
        'bind_filters' => null,
        'input' => null,
    ],

    // Data API rate limit per user and minute (plan §6.5).
    'throttle' => (int) env('GEO_VIEW_THROTTLE', 120),

    // Requests slower than this (ms) are logged with the source key (plan §7.5).
    'slow_ms' => 500,
];
