<?php

return [
    // Boundary set the map draws. GeoAreaIds are stable across versions, so deep links survive an upgrade;
    // the previous version stays published for one release (geo-view plan §13).
    'boundary_version' => env('GEO_BOUNDARY_VERSION', 'bd-boundaries-2026-10-22.1'),

    // Built boundary sets inside the package, and where geo-areas:publish-boundaries copies them (plan D7:
    // static, open-licence files the web server can cache).
    'boundary_source' => dirname(__DIR__).'/database/data/boundaries',
    'boundary_publish_path' => public_path('vendor/geo-areas/boundaries'),
    'boundary_url' => 'vendor/geo-areas/boundaries',
];
