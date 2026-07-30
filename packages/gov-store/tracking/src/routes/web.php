<?php

use Illuminate\Support\Facades\Route;
use GovStore\Tracking\Http\Controllers\FundingTypeController;
use GovStore\Tracking\Http\Controllers\InitiativeController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use GovStore\Tracking\Http\Controllers\TrackingRetrospectiveController;
use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController; // Added Import

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
Route::resource('initiatives.tracking-codes', TrackingCodeController::class)->except(['index', 'show']);

// State Transitions
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/activate', [TrackingCodeController::class, 'activate'])->name('initiatives.tracking-codes.activate');
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/archive', [TrackingCodeController::class, 'archive'])->name('initiatives.tracking-codes.archive');

Route::get('tracking-codes/{trackingCode}/download', [TrackingCodeController::class, 'downloadPdf'])->name('tracking-codes.download');

// Real-Time Form Uniqueness Check (Session-Authenticated Web Path)
Route::get('tracking-codes/check-uniqueness', [TrackingEvaluationController::class, 'checkUniqueness'])
    ->name('tracking-codes.check-uniqueness');

// 4. Retrospective Tagging Console
Route::get('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'index'])->name('initiatives.retrospective.index');
Route::post('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'associate'])->name('initiatives.retrospective.associate');