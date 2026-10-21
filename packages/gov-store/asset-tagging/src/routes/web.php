<?php

use GovStore\AssetTagging\Http\Controllers\CompanyCodeListController;
use GovStore\AssetTagging\Http\Controllers\EnablementController;
use GovStore\AssetTagging\Http\Controllers\IssuedTagsExportController;
use GovStore\AssetTagging\Http\Controllers\OfficeTagSettingsController;
use GovStore\AssetTagging\Http\Controllers\TagLookupController;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::group(['prefix' => 'gov-store/asset-tags', 'middleware' => ['web', 'auth', InitializeTenantContext::class]], function () {
    // S5 · office tag settings (office admin) and company code lists (company admin).
    Route::get('/settings', [OfficeTagSettingsController::class, 'show'])->middleware('gov.can:asset-tags.office.configure')->name('asset-tags.office.show')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(trans('asset-tagging::tagging.nav.settings'), route('asset-tags.office.show')));
    Route::post('/settings', [OfficeTagSettingsController::class, 'update'])->middleware('gov.can:asset-tags.office.configure')->name('asset-tags.office.update');
    Route::get('/codes', [CompanyCodeListController::class, 'show'])->middleware('gov.can:asset-tags.company.codelists')->name('asset-tags.codes.show')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(trans('asset-tagging::tagging.nav.codes'), route('asset-tags.codes.show')));
    Route::post('/codes', [CompanyCodeListController::class, 'update'])->middleware('gov.can:asset-tags.company.codelists')->name('asset-tags.codes.update');

    // S7 · tag lookup (object-checked; missing and forbidden look the same).
    Route::get('/lookup', TagLookupController::class)->middleware('gov.can:asset-tags.lookup')->name('asset-tags.lookup')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(trans('asset-tagging::tagging.nav.lookup'), route('asset-tags.lookup')));

    // S8 · enablement console (deployment operator; POSTs pass the national change review).
    Route::get('/enablement', [EnablementController::class, 'index'])->middleware('gov.can:asset-tags.scope.enable')->name('asset-tags.enablement.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(trans('asset-tagging::tagging.nav.enablement'), route('asset-tags.enablement.index')));
    Route::post('/enablement/{office}/enable', [EnablementController::class, 'enable'])->whereNumber('office')->middleware('gov.can:asset-tags.scope.enable')->name('asset-tags.enablement.enable');
    Route::post('/enablement/{office}/disable', [EnablementController::class, 'disable'])->whereNumber('office')->middleware('gov.can:asset-tags.scope.enable')->name('asset-tags.enablement.disable');

    // S3 · posted GRN: issued tags as CSV.
    Route::get('/documents/{document}/issued.csv', IssuedTagsExportController::class)->whereUuid('document')->middleware('gov.can:storeops.documents.view')->name('asset-tags.grn.csv');
});
