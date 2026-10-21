<?php

use GovStore\GeoView\Http\Controllers\GeoDataController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'throttle:geo-view'])->prefix('gov-store/api/geo-view')->group(function () {
    Route::get('sources/{source}/data', [GeoDataController::class, 'data'])
        ->where('source', '[a-z0-9_.]+')
        ->name('gov.geo-view.data');
    Route::get('sources/{source}/details', [GeoDataController::class, 'details'])
        ->where('source', '[a-z0-9_.]+')
        ->name('gov.geo-view.details');
});
