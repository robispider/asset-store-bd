<?php

use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;
use GovStore\Tracking\Http\Controllers\FundingTypeController;
use GovStore\Tracking\Http\Controllers\InitiativeController;
use GovStore\Tracking\Http\Controllers\OperationUnitController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use GovStore\Tracking\Http\Controllers\TrackingDocumentController;
use GovStore\Tracking\Http\Controllers\TrackingOfficeController;
use GovStore\Tracking\Http\Controllers\TrackingRetrospectiveController;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\TrackingTrail;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
|--------------------------------------------------------------------------
| Tracking Web Routes (Admin UI)
|--------------------------------------------------------------------------
| Every page route has a breadcrumb, so the layout header shows the trail and the page's own
| <x-gs::page-header> is the only visible title. Programme and code names in the trail come from
| TrackingTrail (the view that rendered), never from the raw route parameters. An office crumb is
| always just "Office": office names are long and the page heading right below already gives it.
*/

$t = fn (string $key) => __('govtracking::general.crumbs.'.$key);
$names = fn () => app(TrackingTrail::class);
$programmeOf = fn ($code) => $code instanceof TrackingCode ? $code->initiative_id : TrackingCode::whereKey((int) $code)->value('initiative_id');

// 1. System Configuration
Route::get('funding-types', [FundingTypeController::class, 'index'])->name('funding-types.index')->middleware('gov.can:tracking.settings.manage')
    ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('funding_types'), route('gov.tracking.funding-types.index')));
Route::resource('funding-types', FundingTypeController::class)->only(['store', 'destroy'])->middleware('gov.can:tracking.settings.manage');

// 2. The Umbrella Initiatives
Route::get('initiatives', [InitiativeController::class, 'index'])->name('initiatives.index')->middleware('gov.can:tracking.programmes.view')
    ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('programmes'), route('gov.tracking.initiatives.index')));
Route::get('initiatives/create', [InitiativeController::class, 'create'])->name('initiatives.create')->middleware('gov.can:tracking.programmes.create')
    ->breadcrumbs(fn (Trail $trail) => $trail->parent('gov.tracking.initiatives.index')->push($t('new_programme')));
Route::get('initiatives/{initiative}', [InitiativeController::class, 'show'])->name('initiatives.show')->middleware('gov.can:tracking.programmes.view')
    ->breadcrumbs(fn (Trail $trail, $initiative) => $trail->parent('gov.tracking.initiatives.index')->push($names()->programme(), route('gov.tracking.initiatives.show', $initiative)));
Route::get('initiatives/{initiative}/edit', [InitiativeController::class, 'edit'])->name('initiatives.edit')->middleware('gov.can:tracking.programmes.manage')
    ->breadcrumbs(fn (Trail $trail, $initiative) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($t('settings')));
Route::get('initiatives/{initiative}/manage', [InitiativeController::class, 'manage'])->name('initiatives.manage')->middleware('gov.can:tracking.programmes.view')
    ->breadcrumbs(fn (Trail $trail, $initiative) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($t('manage')));
Route::get('initiatives/{initiative}/report', [InitiativeController::class, 'report'])->name('initiatives.report')->middleware('gov.can:tracking.programmes.view')
    ->breadcrumbs(fn (Trail $trail, $initiative) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($t('report')));
Route::resource('initiatives', InitiativeController::class)->only(['store', 'update', 'destroy'])
    ->middlewareFor('store', 'gov.can:tracking.programmes.create')
    ->middlewareFor(['update', 'destroy'], 'gov.can:tracking.programmes.manage');

// 3. Operation Unit Management (The Human Context)
Route::get('initiatives/operation-unit/search-users', [OperationUnitController::class, 'searchUsers'])->name('operation-unit.search-users')->middleware('gov.can:tracking.team.manage');
Route::get('initiatives/{initiative}/operation-unit', [OperationUnitController::class, 'index'])->name('initiatives.operation-unit.index')->middleware('gov.can:tracking.team.view')
    ->breadcrumbs(fn (Trail $trail, $initiative) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($t('team')));
Route::resource('initiatives.operation-unit', OperationUnitController::class)->only(['store', 'destroy'])->middleware('gov.can:tracking.team.manage');

// =========================================================================
// 4. Tracking Codes / Tasks
// =========================================================================

// FIXED: Static custom routes MUST be declared BEFORE the resource route,
// otherwise Laravel mistakes 'check-uniqueness' for a dynamic ID parameter!
Route::get('tracking-codes/check-uniqueness', [TrackingEvaluationController::class, 'checkUniqueness'])
    ->name('tracking-codes.check-uniqueness')->middleware('gov.can:tracking.codes.manage');
Route::get('tracking-codes/{trackingCode}/download', [TrackingCodeController::class, 'downloadPdf'])
    ->name('tracking-codes.download')->middleware('gov.can:tracking.codes.sheet');

Route::get('initiatives/{initiative}/tracking-codes/create', [TrackingCodeController::class, 'create'])->name('initiatives.tracking-codes.create')->middleware('gov.can:tracking.codes.manage')
    ->breadcrumbs(fn (Trail $trail, $initiative) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($t('new_code')));
Route::get('initiatives/{initiative}/tracking-codes/{tracking_code}', [TrackingCodeController::class, 'show'])->name('initiatives.tracking-codes.show')->middleware('gov.can:tracking.codes.view')
    ->breadcrumbs(fn (Trail $trail, $initiative, $code) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($names()->code(), route('gov.tracking.initiatives.tracking-codes.show', [$initiative, $code])));
Route::get('initiatives/{initiative}/tracking-codes/{tracking_code}/edit', [TrackingCodeController::class, 'edit'])->name('initiatives.tracking-codes.edit')->middleware('gov.can:tracking.codes.manage')
    ->breadcrumbs(fn (Trail $trail, $initiative, $code) => $trail->parent('gov.tracking.initiatives.tracking-codes.show', $initiative, $code)->push($t('edit')));
Route::resource('initiatives.tracking-codes', TrackingCodeController::class)->only(['store', 'update', 'destroy'])->middleware('gov.can:tracking.codes.manage');

// State Transitions
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/activate', [TrackingCodeController::class, 'activate'])->name('initiatives.tracking-codes.activate')->middleware('gov.can:tracking.codes.activate');
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/archive', [TrackingCodeController::class, 'archive'])->name('initiatives.tracking-codes.archive')->middleware('gov.can:tracking.codes.archive');
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/order', [TrackingCodeController::class, 'attachOrder'])->name('initiatives.tracking-codes.order')->middleware('gov.can:tracking.codes.manage');

// =========================================================================
// 5. Retrospective Tagging Console
// =========================================================================
Route::get('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'index'])->name('initiatives.retrospective.index')->middleware('gov.can:tracking.retrospective.view')
    ->breadcrumbs(fn (Trail $trail, $initiative) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($t('legacy')));
Route::post('initiatives/{initiative}/retrospective', [TrackingRetrospectiveController::class, 'associate'])->name('initiatives.retrospective.associate')->middleware('gov.can:tracking.retrospective.manage');

Route::get('tracking-codes/{trackingCode}/view-task', [TrackingCodeController::class, 'viewTaskComponent'])
    ->name('tracking-codes.view-task')->middleware('gov.can:tracking.codes.sheet')
    ->breadcrumbs(fn (Trail $trail, $code) => $trail->parent('gov.tracking.initiatives.tracking-codes.show', $programmeOf($code), $code)->push($t('sheet')));
Route::post('initiatives/{initiative}/tracking-codes/{trackingCode}/documents', [TrackingDocumentController::class, 'store'])->name('documents.store')->middleware('gov.can:tracking.documents.manage');
Route::get('initiatives/{initiative}/tracking-codes/{trackingCode}/documents/{document}', [TrackingDocumentController::class, 'download'])->name('documents.download')->middleware('gov.can:tracking.documents.view');
Route::delete('initiatives/{initiative}/tracking-codes/{trackingCode}/documents/{document}', [TrackingDocumentController::class, 'destroy'])->name('documents.destroy')->middleware('gov.can:tracking.documents.manage');

// Monitoring office routes keep programme and office visibility independent.
Route::get('offices', [TrackingOfficeController::class, 'index'])->name('offices.index')->middleware('gov.can:tracking.offices.view')
    ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('offices'), route('gov.tracking.offices.index')));
Route::get('offices/{location}', [TrackingOfficeController::class, 'all'])->whereNumber('location')->name('offices.show')->middleware('gov.can:tracking.offices.view')
    ->breadcrumbs(fn (Trail $trail, $location) => $trail->parent('gov.tracking.offices.index')->push($t('office')));
Route::get('initiatives/{initiative}/offices/{location}', [TrackingOfficeController::class, 'programme'])->whereNumber('location')->name('initiatives.offices.show')->middleware('gov.can:tracking.programmes.view')
    ->breadcrumbs(fn (Trail $trail, $initiative, $location) => $trail->parent('gov.tracking.initiatives.show', $initiative)->push($t('office')));
Route::get('initiatives/{initiative}/tracking-codes/{trackingCode}/offices/{location}', [TrackingOfficeController::class, 'code'])->whereNumber('location')->name('initiatives.tracking-codes.offices.show')->middleware('gov.can:tracking.programmes.view')
    ->breadcrumbs(fn (Trail $trail, $initiative, $code, $location) => $trail->parent('gov.tracking.initiatives.tracking-codes.show', $initiative, $code)->push($t('office')));
