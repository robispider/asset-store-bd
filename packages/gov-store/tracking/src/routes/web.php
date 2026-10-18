<?php

use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;
use GovStore\Tracking\Http\Controllers\FundingTypeController;
use GovStore\Tracking\Http\Controllers\InitiativeController;
use GovStore\Tracking\Http\Controllers\OperationUnitController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use GovStore\Tracking\Http\Controllers\TrackingDocumentController;
use GovStore\Tracking\Http\Controllers\TrackingOfficeController;
use GovStore\Tracking\Http\Controllers\TrackingRetrospectiveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tracking Web Routes (Admin UI)
|--------------------------------------------------------------------------
*/

// 1. System Configuration
Route::resource('funding-types', FundingTypeController::class)->only(['index', 'store', 'destroy'])->middleware('gov.can:tracking.settings.manage');

// 2. The Umbrella Initiatives
Route::get('initiatives/{initiative}/manage', [InitiativeController::class, 'manage'])->name('initiatives.manage')->middleware('gov.can:tracking.programmes.view');
Route::resource('initiatives', InitiativeController::class)
    ->middlewareFor(['index', 'show'], 'gov.can:tracking.programmes.view')
    ->middlewareFor(['create', 'store'], 'gov.can:tracking.programmes.create')
    ->middlewareFor(['edit', 'update', 'destroy'], 'gov.can:tracking.programmes.manage');
Route::get('initiatives/{initiative}/report', [InitiativeController::class, 'report'])->name('initiatives.report')->middleware('gov.can:tracking.programmes.view');

// 3. Operation Unit Management (The Human Context)
Route::get('initiatives/operation-unit/search-users', [OperationUnitController::class, 'searchUsers'])->name('operation-unit.search-users')->middleware('gov.can:tracking.team.manage');
Route::resource('initiatives.operation-unit', OperationUnitController::class)->only(['index', 'store', 'destroy'])->middlewareFor('index', 'gov.can:tracking.team.view')->middlewareFor(['store', 'destroy'], 'gov.can:tracking.team.manage');

// =========================================================================
// 4. Tracking Codes / Tasks
// =========================================================================

// FIXED: Static custom routes MUST be declared BEFORE the resource route,
// otherwise Laravel mistakes 'check-uniqueness' for a dynamic ID parameter!
Route::get('tracking-codes/check-uniqueness', [TrackingEvaluationController::class, 'checkUniqueness'])
    ->name('tracking-codes.check-uniqueness')->middleware('gov.can:tracking.codes.manage');
Route::get('tracking-codes/{trackingCode}/download', [TrackingCodeController::class, 'downloadPdf'])
    ->name('tracking-codes.download')->middleware('gov.can:tracking.codes.sheet');

// Nested Resource Route
Route::resource('initiatives.tracking-codes', TrackingCodeController::class)->except(['index'])->middlewareFor('show', 'gov.can:tracking.codes.view')->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'gov.can:tracking.codes.manage');

// State Transitions
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/activate', [TrackingCodeController::class, 'activate'])->name('initiatives.tracking-codes.activate')->middleware('gov.can:tracking.codes.activate');
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/archive', [TrackingCodeController::class, 'archive'])->name('initiatives.tracking-codes.archive')->middleware('gov.can:tracking.codes.archive');

// =========================================================================
// 5. Retrospective Tagging Console
// =========================================================================
Route::get('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'index'])->name('initiatives.retrospective.index')->middleware('gov.can:tracking.retrospective.view');
Route::post('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'associate'])->name('initiatives.retrospective.associate')->middleware('gov.can:tracking.retrospective.manage');

Route::get('tracking-codes/{trackingCode}/view-task', [TrackingCodeController::class, 'viewTaskComponent'])
    ->name('tracking-codes.view-task')->middleware('gov.can:tracking.codes.sheet');
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/documents', [TrackingDocumentController::class, 'store'])->name('documents.store')->middleware('gov.can:tracking.documents.manage');
Route::get('initiatives/{initiative}/tracking-codes/{trackingCode}/documents/{document}', [TrackingDocumentController::class, 'download'])->name('documents.download')->middleware('gov.can:tracking.documents.view');
Route::delete('initiatives/{initiative}/tracking-codes/{trackingCode}/documents/{document}', [TrackingDocumentController::class, 'destroy'])->name('documents.destroy')->middleware('gov.can:tracking.documents.manage');

// Monitoring office routes keep programme and office visibility independent.
Route::get('offices', [TrackingOfficeController::class, 'index'])->name('offices.index')->middleware('gov.can:tracking.offices.view');
Route::get('offices/{location}', [TrackingOfficeController::class, 'all'])->whereNumber('location')->name('offices.show')->middleware('gov.can:tracking.offices.view');
Route::get('initiatives/{initiative}/offices/{location}', [TrackingOfficeController::class, 'programme'])->whereNumber('location')->name('initiatives.offices.show')->middleware('gov.can:tracking.programmes.view');
Route::get('initiatives/{initiative}/tracking-codes/{trackingCode}/offices/{location}', [TrackingOfficeController::class, 'code'])->whereNumber('location')->name('initiatives.tracking-codes.offices.show')->middleware('gov.can:tracking.programmes.view');
