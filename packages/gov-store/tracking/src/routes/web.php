<?php

use Illuminate\Support\Facades\Route;
use GovStore\Tracking\Http\Controllers\FundingTypeController;
use GovStore\Tracking\Http\Controllers\InitiativeController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use GovStore\Tracking\Http\Controllers\TrackingRetrospectiveController;
use GovStore\Tracking\Http\Controllers\OperationUnitController; // Added
use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;

/*
|--------------------------------------------------------------------------
| Tracking Web Routes (Admin UI)
|--------------------------------------------------------------------------
*/

// 1. System Configuration
Route::resource('funding-types', FundingTypeController::class)->only(['index', 'store', 'destroy']);

// 2. The Umbrella Initiatives
Route::resource('initiatives', InitiativeController::class);
Route::get('initiatives/{initiative}/report', [InitiativeController::class, 'report'])->name('initiatives.report');

// 3. Operation Unit Management (The Human Context)
Route::get('initiatives/operation-unit/search-users', [OperationUnitController::class, 'searchUsers'])->name('operation-unit.search-users');
Route::resource('initiatives.operation-unit', OperationUnitController::class)->only(['index', 'store', 'destroy']);

// 4. Tracking Codes / Tasks (Nested under an Initiative)
Route::resource('initiatives.tracking-codes', TrackingCodeController::class)->except(['index', 'show']);
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/activate', [TrackingCodeController::class, 'activate'])->name('initiatives.tracking-codes.activate');
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/archive', [TrackingCodeController::class, 'archive'])->name('initiatives.tracking-codes.archive');
Route::get('tracking-codes/{trackingCode}/download', [TrackingCodeController::class, 'downloadPdf'])->name('tracking-codes.download');
Route::get('tracking-codes/check-uniqueness', [TrackingEvaluationController::class, 'checkUniqueness'])->name('tracking-codes.check-uniqueness');

// 5. Retrospective Tagging Console
Route::get('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'index'])->name('initiatives.retrospective.index');
Route::post('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'associate'])->name('initiatives.retrospective.associate');

