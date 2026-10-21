<?php

// Geo view presets (geo-view plan §5.3), registered by OrganizationServiceProvider only when geo-view is bound.
return [
    'organization.offices' => [
        'title' => 'geoview::general.sources.organization_offices',
        'national' => true,
        'intent' => 'locate',
        'source' => ['key' => 'organization.offices', 'params' => []],
        'layers' => ['default' => ['offices'], 'allow' => ['offices', 'verified_share']],
        'levels' => ['start' => 'auto', 'min' => 1, 'max' => 3, 'urban' => 'colour'],
        'panels' => ['details' => 'side', 'table' => 'toggle', 'markers' => true, 'labels' => true],
        'size' => ['height' => 520, 'aspect' => null],
        'locked' => ['source.key'],
    ],
];
