<?php

// Presets owned by geo-view itself.
return [
    // The map as a form input: choose an area and write its GeoAreaId to a hidden input (plan §4.3, §5.2).
    'geo.pick' => [
        'intent' => 'pick',
        'source' => ['key' => 'geo.areas', 'params' => []],
        'layers' => ['default' => ['areas'], 'allow' => ['areas']],
        'levels' => ['start' => 'root', 'min' => 1, 'max' => 4, 'urban' => 'colour'],
        'toolbar' => ['search' => true],
        'panels' => ['labels' => true],
        'url_state' => false,
        'locked' => ['source.key'],
    ],
];
