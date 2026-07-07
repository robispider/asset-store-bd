<?php

use Illuminate\Support\Facades\Route;
use GovStore\GeoAreas\Http\Controllers\GeoAreaController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/api/geo'], function () {
    // Shared core API endpoint
    Route::get('/search', [GeoAreaController::class, 'search'])->name('gov.geo.search');
});