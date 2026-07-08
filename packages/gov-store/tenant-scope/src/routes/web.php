<?php

use Illuminate\Support\Facades\Route;
use GovStore\TenantScope\Http\Controllers\TenantScopeController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/admin/scope'], function () {
    
    // 1. Configurator Index & Saving Strategies
    Route::get('/', [TenantScopeController::class, 'index'])->name('gov.scope.index');
    Route::post('/save-strategy', [TenantScopeController::class, 'saveStrategy'])->name('gov.scope.save-strategy');

    // 2. High-Performance AJAX Selectors for Global references & Tenant scoping
    Route::get('/reference-search', [TenantScopeController::class, 'referenceSearch'])->name('gov.scope.reference-search');
    Route::get('/tenant-search', [TenantScopeController::class, 'tenantSearch'])->name('gov.scope.tenant-search');

    // 3. Registering Polymorphic Mappings
    Route::post('/mappings/store', [TenantScopeController::class, 'storeMapping'])->name('gov.scope.mappings.store');
    Route::post('/mappings/delete/{id}', [TenantScopeController::class, 'destroyMapping'])->name('gov.scope.mappings.destroy');
    
});