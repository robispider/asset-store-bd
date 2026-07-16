<?php

use Illuminate\Support\Facades\Route;
use GovStore\Classification\Http\Controllers\CatalogDashboardController;
use GovStore\Classification\Http\Controllers\CatalogSearchController;
use GovStore\Classification\Http\Controllers\CatalogAdminController;

/*
|--------------------------------------------------------------------------
| Global Catalog Routes
|--------------------------------------------------------------------------
| 
| All routes under admin/catalog prefix.
| Requires web + auth middleware.
| Bypasses Tenant Context layer — global reference data for Superadmins.
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
    
    // Mapping editor (per-node) — aliased to gov.catalog.mapping for menu compatibility
    Route::get('/mapping', [CatalogSearchController::class, 'showMapping'])->name('gov.catalog.mapping');
    Route::get('/mapping/{id}', [CatalogSearchController::class, 'showMapping'])->name('gov.catalog.mapping.show');


    // Administrative Actions - MDM Import Wizard
    Route::get('/import', [CatalogAdminController::class, 'importForm'])->name('gov.catalog.import');
    
    Route::post('/import/execute', [CatalogAdminController::class, 'importExecute'])->name('gov.catalog.import.execute');
    
    // Other Admin routes...
    Route::get('/external', [CatalogAdminController::class, 'externalGrid'])->name('gov.catalog.external');
    Route::get('/history', [CatalogAdminController::class, 'importHistory'])->name('gov.catalog.history');

      // The Execute Route with the Performance Guard Middleware attached
 

            Route::post('/import/validate', [CatalogAdminController::class, 'importValidate'])
        ->middleware(\GovStore\Classification\Http\Middleware\ImportPerformanceGuard::class) // ADDED HERE
        ->name('gov.catalog.import.validate');
        
    Route::post('/import/execute', [CatalogAdminController::class, 'importExecute'])
        ->middleware(\GovStore\Classification\Http\Middleware\ImportPerformanceGuard::class)
        ->name('gov.catalog.import.execute');


});
