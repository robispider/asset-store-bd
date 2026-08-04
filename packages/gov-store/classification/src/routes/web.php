<?php

use Illuminate\Support\Facades\Route;
use GovStore\Classification\Http\Controllers\CatalogDashboardController;
use GovStore\Classification\Http\Controllers\CatalogSearchController;
use GovStore\Classification\Http\Controllers\CatalogAdminController;
use GovStore\Classification\Http\Controllers\CategoryAdoptionController;
use GovStore\Classification\Http\Controllers\CategoryGovernanceController;
use GovStore\Classification\Http\Controllers\MyCatalogController;
use GovStore\Classification\Http\Controllers\CollectionBuilderController;
use GovStore\Classification\Http\Controllers\CollectionDiscoveryController;
use GovStore\Classification\Http\Controllers\CatalogExplorerController;
use GovStore\Classification\Http\Controllers\OfficeCopyController;
use GovStore\Classification\Http\Controllers\BulkAdoptionController;

/*
|--------------------------------------------------------------------------
| 1. GLOBAL MASTER CATALOG ROUTE GROUP (Superadmin Only)
|--------------------------------------------------------------------------
| Handles system-wide master data curation, UNSPSC imports, and global
| taxonomy governance. Bypasses local tenant scoping rules.
*/
Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'admin/catalog'], function () {
    
    // Core Dashboard & Explorer Search
    Route::get('/', [CatalogDashboardController::class, 'index'])->name('gov.catalog.dashboard');
    Route::get('/search', [CatalogSearchController::class, 'index'])->name('gov.catalog.search');

    // AJAX Reference Search Endpoints
    Route::get('/search/ajax', [CatalogSearchController::class, 'searchAjax'])->name('gov.catalog.search.ajax');
    Route::get('/browse/ajax', [CatalogSearchController::class, 'browseAjax'])->name('gov.catalog.browse.ajax');
    Route::get('/ancestors/ajax', [CatalogSearchController::class, 'ancestorsAjax'])->name('gov.catalog.ancestors.ajax');
    Route::get('/context/ajax', [CatalogSearchController::class, 'contextAjax'])->name('gov.catalog.context.ajax');
    
    // Mapping Action Endpoints
    Route::get('/snipe-categories/ajax', [CatalogSearchController::class, 'searchSnipeCategories'])->name('gov.catalog.snipe-categories.ajax');
    Route::post('/mapping/save', [CatalogSearchController::class, 'saveMapping'])->name('gov.catalog.mapping.save');
    Route::get('/mapping', [CatalogSearchController::class, 'showMapping'])->name('gov.catalog.mapping');
    Route::get('/mapping/{id}', [CatalogSearchController::class, 'showMapping'])->name('gov.catalog.mapping.show');

    // Ingestion Wizard & History
    Route::get('/import', [CatalogAdminController::class, 'importForm'])->name('gov.catalog.import');
    Route::get('/external', [CatalogAdminController::class, 'externalGrid'])->name('gov.catalog.external');
    Route::get('/history', [CatalogAdminController::class, 'importHistory'])->name('gov.catalog.history');

    Route::post('/import/validate', [CatalogAdminController::class, 'importValidate'])
        ->middleware(\GovStore\Classification\Http\Middleware\ImportPerformanceGuard::class)
        ->name('gov.catalog.import.validate');
        
    Route::post('/import/execute', [CatalogAdminController::class, 'importExecute'])
        ->middleware(\GovStore\Classification\Http\Middleware\ImportPerformanceGuard::class)
        ->name('gov.catalog.import.execute');

    // Single-item Adoption Actions
    Route::post('/adoption/adopt', [CategoryAdoptionController::class, 'adopt'])->name('gov.catalog.adoption.adopt');
    Route::post('/adoption/abandon', [CategoryAdoptionController::class, 'abandon'])->name('gov.catalog.adoption.abandon');
    Route::post('/adoption/provision', [CategoryAdoptionController::class, 'provision'])->name('gov.catalog.adoption.provision');

    // Global Governance Registry
    Route::get('/governance', [CategoryGovernanceController::class, 'index'])->name('gov.catalog.governance.index');
    Route::get('/governance/{id}', [CategoryGovernanceController::class, 'show'])->name('gov.catalog.governance.show');

    // SuperAdmin Collection Library Builder
    Route::get('/collections', [CollectionBuilderController::class, 'index'])->name('gov.catalog.collections.index');
    Route::post('/collections', [CollectionBuilderController::class, 'store'])->name('gov.catalog.collections.store');
    Route::get('/collections/{id}/edit', [CollectionBuilderController::class, 'edit'])->name('gov.catalog.collections.edit');
    Route::post('/collections/{id}/attach', [CollectionBuilderController::class, 'attachNode'])->name('gov.catalog.collections.attach');
    Route::post('/collections/{id}/detach', [CollectionBuilderController::class, 'detachNode'])->name('gov.catalog.collections.detach');
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
    'middleware' => ['web', 'auth', \GovStore\TenantScope\Http\Middleware\InitializeTenantContext::class]
], function () {
    
    // User Operational Workspace (My Organization Catalog)
    Route::get('/', [MyCatalogController::class, 'index'])->name('gov.catalog.my_catalog.index');
    Route::get('/{id}', [MyCatalogController::class, 'show'])->name('gov.catalog.my_catalog.show');
    Route::post('/archive', [MyCatalogController::class, 'archive'])->name('gov.catalog.my_catalog.archive');
    Route::post('/restore', [MyCatalogController::class, 'restore'])->name('gov.catalog.my_catalog.restore');

    // Discover Mode: Windows-style Explorer
    Route::get('/discover/explorer', [CatalogExplorerController::class, 'index'])->name('gov.catalog.discover.explorer');

    // Discover Mode: Curated Collections
    Route::get('/discover/collections', [CollectionDiscoveryController::class, 'index'])->name('gov.catalog.discover.collections');
    Route::get('/discover/collections/{id}', [CollectionDiscoveryController::class, 'show'])->name('gov.catalog.discover.collections.show');

    // Onboarding Mode: Office Copy
    Route::get('/adopt/copy', [OfficeCopyController::class, 'index'])->name('gov.catalog.adopt.copy');
    Route::post('/adopt/copy/fetch', [OfficeCopyController::class, 'fetchSourceCodes'])->name('gov.catalog.adopt.copy.fetch');

    // Shared Bulk Adoption Engine
    Route::post('/bulk/preview', [BulkAdoptionController::class, 'preview'])->name('gov.catalog.bulk.preview');
    Route::post('/bulk/execute', [BulkAdoptionController::class, 'execute'])->name('gov.catalog.bulk.execute');

    // Universal Search API Endpoint
    Route::get('/search/universal/ajax', [CatalogSearchController::class, 'searchUniversalAjax'])->name('gov.catalog.search.universal.ajax');

    // Collection Membership API Endpoints
    Route::get('/discover/collections-api/list', [\GovStore\Classification\Http\Controllers\CollectionDiscoveryController::class, 'listActive'])->name('gov.catalog.discover.collections.api.list');
    Route::post('/discover/collections-api/add-nodes', [\GovStore\Classification\Http\Controllers\CollectionDiscoveryController::class, 'addNodes'])->name('gov.catalog.discover.collections.api.add-nodes');
    
});