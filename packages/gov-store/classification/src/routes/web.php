<?php

use Illuminate\Support\Facades\Route;
use GovStore\Classification\Http\Controllers\CatalogDashboardController;
use GovStore\Classification\Http\Controllers\CatalogSearchController;
use GovStore\Classification\Http\Controllers\CatalogAdminController;
use GovStore\Classification\Http\Controllers\CategoryAdoptionController;
use GovStore\Classification\Http\Controllers\CategoryGovernanceController;
use GovStore\Classification\Http\Controllers\MyCatalogController;

/*
|--------------------------------------------------------------------------
| 1. GLOBAL MASTER CATALOG ROUTE GROUP (Superadmin Only)
|--------------------------------------------------------------------------
| All routes under admin/catalog prefix.
| Requires web + auth middleware. Bypasses Tenant Context.
*/
Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'admin/catalog'], function () {
    
    // Dashboard (root of catalog workspace)
    Route::get('/', [CatalogDashboardController::class, 'index'])->name('gov.catalog.dashboard');

    // Search UI (human-centered)
    Route::get('/search', [CatalogSearchController::class, 'index'])->name('gov.catalog.search');

    // AJAX endpoints for autocomplete/browse
    Route::get('/search/ajax', [CatalogSearchController::class, 'searchAjax'])->name('gov.catalog.search.ajax');
    Route::get('/browse/ajax', [CatalogSearchController::class, 'browseAjax'])->name('gov.catalog.browse.ajax');
    Route::get('/ancestors/ajax', [CatalogSearchController::class, 'ancestorsAjax'])->name('gov.catalog.ancestors.ajax');
    Route::get('/context/ajax', [CatalogSearchController::class, 'contextAjax'])->name('gov.catalog.context.ajax');
    
    // Mapping Actions
    Route::get('/snipe-categories/ajax', [CatalogSearchController::class, 'searchSnipeCategories'])->name('gov.catalog.snipe-categories.ajax');
    Route::post('/mapping/save', [CatalogSearchController::class, 'saveMapping'])->name('gov.catalog.mapping.save');

    // Mapping editor (per-node)
    Route::get('/mapping', [CatalogSearchController::class, 'showMapping'])->name('gov.catalog.mapping');
    Route::get('/mapping/{id}', [CatalogSearchController::class, 'showMapping'])->name('gov.catalog.mapping.show');

    // Administrative Actions - MDM Import Wizard
    Route::get('/import', [CatalogAdminController::class, 'importForm'])->name('gov.catalog.import');
    Route::get('/external', [CatalogAdminController::class, 'externalGrid'])->name('gov.catalog.external');
    Route::get('/history', [CatalogAdminController::class, 'importHistory'])->name('gov.catalog.history');

    // Ingest Executions with Performance Guard
    Route::post('/import/validate', [CatalogAdminController::class, 'importValidate'])
        ->middleware(\GovStore\Classification\Http\Middleware\ImportPerformanceGuard::class)
        ->name('gov.catalog.import.validate');
        
    Route::post('/import/execute', [CatalogAdminController::class, 'importExecute'])
        ->middleware(\GovStore\Classification\Http\Middleware\ImportPerformanceGuard::class)
        ->name('gov.catalog.import.execute');

    // Category Adoption Actions (Adopt, Abandon, Provision)
    Route::post('/adoption/adopt', [CategoryAdoptionController::class, 'adopt'])->name('gov.catalog.adoption.adopt');
    Route::post('/adoption/abandon', [CategoryAdoptionController::class, 'abandon'])->name('gov.catalog.adoption.abandon');
    Route::post('/adoption/provision', [CategoryAdoptionController::class, 'provision'])->name('gov.catalog.adoption.provision');

    // Workspace 2: Category Governance Center (Superadmin Only)
    Route::get('/governance', [CategoryGovernanceController::class, 'index'])->name('gov.catalog.governance.index');
    Route::get('/governance/{id}', [CategoryGovernanceController::class, 'show'])->name('gov.catalog.governance.show');
});

/*
|--------------------------------------------------------------------------
| 2. OPERATIONAL ORGANIZATION CATALOG ROUTE GROUP (Staff Contexts)
|--------------------------------------------------------------------------
| Scoped strictly to the active company_id / location_id context.
*/
Route::group([
    'prefix' => 'gov-store/operations/catalog', 
    'middleware' => ['web', 'auth', \GovStore\TenantScope\Http\Middleware\InitializeTenantContext::class]
], function () {
    
    // Workspace 3 Dashboard
    Route::get('/', [MyCatalogController::class, 'index'])->name('gov.catalog.my_catalog.index');
    Route::get('/{id}', [MyCatalogController::class, 'show'])->name('gov.catalog.my_catalog.show');
    
    // Soft-Archive Lifecycle Endpoints
    Route::post('/archive', [MyCatalogController::class, 'archive'])->name('gov.catalog.my_catalog.archive');
    Route::post('/restore', [MyCatalogController::class, 'restore'])->name('gov.catalog.my_catalog.restore');
});