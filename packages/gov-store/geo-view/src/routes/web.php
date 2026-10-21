<?php

use GovStore\GeoView\Http\Controllers\GeoDataController;
use GovStore\GeoView\Http\Controllers\GeoMonitorController;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::middleware(['web', 'auth', 'throttle:geo-view'])->prefix('gov-store/api/geo-view')->group(function () {
    Route::get('sources/{source}/data', [GeoDataController::class, 'data'])
        ->where('source', '[a-z0-9_.]+')
        ->name('gov.geo-view.data');
    Route::get('sources/{source}/details', [GeoDataController::class, 'details'])
        ->where('source', '[a-z0-9_.]+')
        ->name('gov.geo-view.details');
});

// National geo monitor (plan phase F): a host page for every preset marked `national`.
Route::middleware(['web', 'auth', 'gov.can:geo.monitor.view'])->group(function () {
    Route::get('gov-store/geo-monitor', [GeoMonitorController::class, 'index'])
        ->name('gov.geo-monitor.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')
            ->push(trans('geoview::general.monitor.title'), route('gov.geo-monitor.index')));
});
