<?php

use GovStore\Classification\Http\Controllers\BulkAdoptionController;
use GovStore\Classification\Http\Controllers\CatalogAdminController;
use GovStore\Classification\Http\Controllers\CatalogDashboardController;
use GovStore\Classification\Http\Controllers\CatalogExplorerController;
use GovStore\Classification\Http\Controllers\CatalogSearchController;
use GovStore\Classification\Http\Controllers\CategoryAdoptionController;
use GovStore\Classification\Http\Controllers\CategoryGovernanceController;
use GovStore\Classification\Http\Controllers\CollectionBuilderController;
use GovStore\Classification\Http\Controllers\CollectionDiscoveryController;
use GovStore\Classification\Http\Controllers\MyCatalogController;
use GovStore\Classification\Http\Controllers\OfficeCopyController;
use GovStore\Classification\Http\Middleware\ImportPerformanceGuard;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 1. GLOBAL MASTER CATALOG ROUTE GROUP (Superadmin Only)
|--------------------------------------------------------------------------
| Handles system-wide master data curation, UNSPSC imports, and global
| taxonomy governance. Bypasses local tenant scoping rules.
*/
Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'admin/catalog'], function () {

    // Core Dashboard & Explorer Search
    Route::get('/', [CatalogDashboardController::class, 'index'])->middleware('gov.can:catalog.view')->name('gov.catalog.dashboard');
    Route::get('/search', [CatalogSearchController::class, 'index'])->middleware('gov.can:catalog.view')->name('gov.catalog.search');

    // AJAX Reference Search Endpoints
    Route::get('/search/ajax', [CatalogSearchController::class, 'searchAjax'])->middleware('gov.can:catalog.view')->name('gov.catalog.search.ajax');
    Route::get('/browse/ajax', [CatalogSearchController::class, 'browseAjax'])->middleware('gov.can:catalog.view')->name('gov.catalog.browse.ajax');
    Route::get('/ancestors/ajax', [CatalogSearchController::class, 'ancestorsAjax'])->middleware('gov.can:catalog.view')->name('gov.catalog.ancestors.ajax');
    Route::get('/context/ajax', [CatalogSearchController::class, 'contextAjax'])->middleware('gov.can:catalog.view')->name('gov.catalog.context.ajax');

    // Mapping Action Endpoints
    Route::get('/snipe-categories/ajax', [CatalogSearchController::class, 'searchSnipeCategories'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.snipe-categories.ajax');
    Route::post('/mapping/save', [CatalogSearchController::class, 'saveMapping'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.mapping.save');
    Route::get('/mapping', [CatalogSearchController::class, 'showMapping'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.mapping');
    Route::get('/mapping/{id}', [CatalogSearchController::class, 'showMapping'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.mapping.show');

    // Ingestion Wizard & History
    Route::get('/import', [CatalogAdminController::class, 'importForm'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.import');
    Route::get('/external', [CatalogAdminController::class, 'externalGrid'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.external');
    Route::get('/history', [CatalogAdminController::class, 'importHistory'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.history');

    Route::post('/import/validate', [CatalogAdminController::class, 'importValidate'])
        ->middleware(ImportPerformanceGuard::class)
        ->middleware('gov.can:catalog.master.manage')->name('gov.catalog.import.validate');

    Route::post('/import/execute', [CatalogAdminController::class, 'importExecute'])
        ->middleware(ImportPerformanceGuard::class)
        ->middleware('gov.can:catalog.master.manage')->name('gov.catalog.import.execute');

    // Single-item Adoption Actions
    Route::post('/adoption/adopt', [CategoryAdoptionController::class, 'adopt'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.adoption.adopt');
    Route::post('/adoption/abandon', [CategoryAdoptionController::class, 'abandon'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.adoption.abandon');
    Route::post('/adoption/provision', [CategoryAdoptionController::class, 'provision'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.adoption.provision');

    // Global Governance Registry
    Route::get('/governance', [CategoryGovernanceController::class, 'index'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.governance.index');
    Route::get('/governance/{id}', [CategoryGovernanceController::class, 'show'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.governance.show');

    // SuperAdmin Collection Library Builder
    Route::get('/collections', [CollectionBuilderController::class, 'index'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.collections.index');
    Route::post('/collections', [CollectionBuilderController::class, 'store'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.collections.store');
    Route::get('/collections/{id}/edit', [CollectionBuilderController::class, 'edit'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.collections.edit');
    Route::post('/collections/{id}/attach', [CollectionBuilderController::class, 'attachNode'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.collections.attach');
    Route::post('/collections/{id}/detach', [CollectionBuilderController::class, 'detachNode'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.collections.detach');
});

/*
|--------------------------------------------------------------------------
| 2. OPERATIONAL ORGANIZATION CATALOG ROUTE GROUP (Tenant Scoped)
|--------------------------------------------------------------------------
| Confines visibility strictly to the active user's Ministry (Company)
| or physical Office (Location) context.
*/
Route::group([
    'prefix' => 'gov-store/operations/catalog',
    'middleware' => ['web', 'auth', InitializeTenantContext::class],
], function () {

    // User Operational Workspace (My Organization Catalog)
    Route::get('/', [MyCatalogController::class, 'index'])->middleware('gov.can:catalog.view')->name('gov.catalog.my_catalog.index');
    Route::get('/{id}', [MyCatalogController::class, 'show'])->middleware('gov.can:catalog.view')->name('gov.catalog.my_catalog.show');
    Route::post('/archive', [MyCatalogController::class, 'archive'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.my_catalog.archive');
    Route::post('/restore', [MyCatalogController::class, 'restore'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.my_catalog.restore');

    // Discover Mode: Windows-style Explorer
    Route::get('/discover/explorer', [CatalogExplorerController::class, 'index'])->middleware('gov.can:catalog.view')->name('gov.catalog.discover.explorer');

    // Discover Mode: Curated Collections
    Route::get('/discover/collections', [CollectionDiscoveryController::class, 'index'])->middleware('gov.can:catalog.view')->name('gov.catalog.discover.collections');
    Route::get('/discover/collections/{id}', [CollectionDiscoveryController::class, 'show'])->middleware('gov.can:catalog.view')->name('gov.catalog.discover.collections.show');

    // Onboarding Mode: Office Copy
    Route::get('/adopt/copy', [OfficeCopyController::class, 'index'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.adopt.copy');
    Route::post('/adopt/copy/fetch', [OfficeCopyController::class, 'fetchSourceCodes'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.adopt.copy.fetch');

    // Shared Bulk Adoption Engine
    Route::post('/bulk/preview', [BulkAdoptionController::class, 'preview'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.bulk.preview');
    Route::post('/bulk/execute', [BulkAdoptionController::class, 'execute'])->middleware('gov.can:catalog.office.adopt')->name('gov.catalog.bulk.execute');

    // Universal Search API Endpoint
    Route::get('/search/universal/ajax', [CatalogSearchController::class, 'searchUniversalAjax'])->middleware('gov.can:catalog.view')->name('gov.catalog.search.universal.ajax');

    // Collection Membership API Endpoints
    Route::get('/discover/collections-api/list', [CollectionDiscoveryController::class, 'listActive'])->middleware('gov.can:catalog.view')->name('gov.catalog.discover.collections.api.list');
    Route::post('/discover/collections-api/add-nodes', [CollectionDiscoveryController::class, 'addNodes'])->middleware('gov.can:catalog.master.manage')->name('gov.catalog.discover.collections.api.add-nodes');

});
