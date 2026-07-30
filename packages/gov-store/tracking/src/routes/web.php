<?php

use Illuminate\Support\Facades\Route;
use GovStore\Tracking\Http\Controllers\FundingTypeController;
use GovStore\Tracking\Http\Controllers\InitiativeController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use GovStore\Tracking\Http\Controllers\TrackingRetrospectiveController;

/*
|--------------------------------------------------------------------------
| Tracking Web Routes (Admin UI)
|--------------------------------------------------------------------------
*/

// 1. System Configuration (Dictionaries)
Route::resource('funding-types', FundingTypeController::class)->only(['index', 'store', 'destroy']);

// 2. The Umbrella Initiatives
Route::resource('initiatives', InitiativeController::class);

// 3. Tracking Codes / Tasks (Nested under an Initiative)
Route::get('initiatives/{initiative}/tracking-codes/create', [TrackingCodeController::class, 'create'])
    ->name('initiatives.tracking-codes.create');
    
Route::post('initiatives/{initiative}/tracking-codes', [TrackingCodeController::class, 'store'])
    ->name('initiatives.tracking-codes.store');
    
Route::get('tracking-codes/{trackingCode}/download', [TrackingCodeController::class, 'downloadPdf'])
    ->name('tracking-codes.download');

// 4. Retrospective Tagging Console (Nested under an Initiative)
Route::get('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'index'])
    ->name('initiatives.retrospective.index');
    
Route::post('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'associate'])
    ->name('initiatives.retrospective.associate');