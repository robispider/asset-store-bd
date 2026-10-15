<?php

use Illuminate\Support\Facades\Route;
use GovStore\TenantScope\Http\Controllers\LocaleController;
use GovStore\TenantScope\Http\Controllers\TenantScopeController;

// Navbar language toggle: every signed-in user, no gov ability needed.
Route::post('/gov-store/locale', [LocaleController::class, 'switch'])->middleware(['web', 'auth'])->name('gov.locale.switch');

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/admin/scope'], function () {
    
    // Workspace 1: Dashboard
    Route::get('/dashboard', [TenantScopeController::class, 'dashboard'])->middleware('gov.can:tenant.scope.view')->name('gov.scope.dashboard');

    // Workspace 2: Global Configuration Rules
    Route::get('/config', [TenantScopeController::class, 'config'])->middleware('gov.can:tenant.scope.view')->name('gov.scope.config');
    Route::post('/save-strategy', [TenantScopeController::class, 'saveStrategy'])->middleware('gov.can:tenant.scope.manage')->name('gov.scope.save-strategy');

    // Workspace 3: Boundary Explorer Grid
    Route::get('/mappings', [TenantScopeController::class, 'explorer'])->middleware('gov.can:tenant.scope.view')->name('gov.scope.mappings');
    Route::post('/mappings/store', [TenantScopeController::class, 'storeMapping'])->middleware('gov.can:tenant.scope.manage')->name('gov.scope.mappings.store');
    Route::post('/mappings/delete/{id}', [TenantScopeController::class, 'destroyMapping'])->middleware('gov.can:tenant.scope.manage')->name('gov.scope.mappings.destroy');

    // Internal AJAX Form Selectors
    Route::get('/reference-search', [TenantScopeController::class, 'referenceSearch'])->middleware('gov.can:tenant.scope.view')->name('gov.scope.reference-search');
    Route::get('/tenant-search', [TenantScopeController::class, 'tenantSearch'])->middleware('gov.can:tenant.scope.view')->name('gov.scope.tenant-search');
    
});
