<?php

// Geo view presets (geo-view plan §5.3), registered by TrackingServiceProvider only when geo-view is bound.
return [
    'tracking.programme' => [
        'intent' => 'progress',
        'source' => ['key' => 'tracking.programme', 'params' => ['initiative', 'code', 'status', 'plan', 'q']],
        'layers' => [
            // First layer with data wins: product-based plans have no office targets (plan §2.1).
            'default' => ['delivered_share', 'received_qty'],
            'allow' => ['delivered_share', 'received_qty', 'value', 'waiting_offices'],
        ],
        'levels' => ['start' => 'auto', 'min' => 1, 'max' => 3, 'urban' => 'colour'],
        'interaction' => ['click' => 'drill', 'leaf_click' => 'details', 'wheel' => 'modifier', 'select' => 'single', 'touch' => 'tap-select-tap-drill'],
        'toolbar' => ['zoom' => true, 'reset' => true, 'layers' => true, 'filters' => true, 'search' => false, 'export' => false, 'fullscreen' => false],
        'panels' => ['breadcrumb' => true, 'legend' => true, 'details' => 'side', 'table' => 'toggle', 'markers' => true],
        'scale' => ['type' => 'sequential', 'steps' => 5, 'domain' => [0, 1]],
        'size' => ['height' => 420, 'aspect' => null],
        'url_state' => true,
        'locked' => ['source.key', 'levels.max'],
    ],
];
