<?php

use Illuminate\Support\Facades\Route;
use GovStore\TenantScope\Http\Controllers\TenantScopeController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/admin/scope'], function () {
    
    // Workspace 1: Dashboard
    Route::get('/dashboard', [TenantScopeController::class, 'dashboard'])->name('gov.scope.dashboard');

    // Workspace 2: Global Configuration Rules
    Route::get('/config', [TenantScopeController::class, 'config'])->name('gov.scope.config');
    Route::post('/save-strategy', [TenantScopeController::class, 'saveStrategy'])->name('gov.scope.save-strategy');

    // Workspace 3: Boundary Explorer Grid
    Route::get('/mappings', [TenantScopeController::class, 'explorer'])->name('gov.scope.mappings');
    Route::post('/mappings/store', [TenantScopeController::class, 'storeMapping'])->name('gov.scope.mappings.store');
    Route::post('/mappings/delete/{id}', [TenantScopeController::class, 'destroyMapping'])->name('gov.scope.mappings.destroy');

    // Internal AJAX Form Selectors
    Route::get('/reference-search', [TenantScopeController::class, 'referenceSearch'])->name('gov.scope.reference-search');
    Route::get('/tenant-search', [TenantScopeController::class, 'tenantSearch'])->name('gov.scope.tenant-search');
    
});