<?php

use Illuminate\Support\Facades\Route;
use GovStore\StoreOperations\Http\Controllers\StockRegisterController;
use GovStore\StoreOperations\Http\Controllers\DocumentWorkspaceController;

Route::group([
    'prefix' => 'gov-store/operations', 
    'middleware' => ['web', 'auth', \GovStore\TenantScope\Http\Middleware\InitializeTenantContext::class]
], function () {
    
    // =========================================================================
    // 1. Stock Register & Kardex (Preserved Core Dashboards)
    // =========================================================================
    Route::get('/register', [StockRegisterController::class, 'index'])->name('storeops.register.index');
    Route::get('/kardex/{type}/{id}', [StockRegisterController::class, 'kardex'])->name('storeops.register.kardex');

    // =========================================================================
    // 2. Generic Document Engine (The Unified Workspace & Hub)
    // =========================================================================
    
    // The Operational Hub (Listings Dashboard)
    Route::get('/hub', [DocumentWorkspaceController::class, 'hub'])->name('storeops.hub');
    
    // Document Initialization (Creates empty DRAFT in database and redirects)
    Route::post('/documents/initialize', [DocumentWorkspaceController::class, 'initialize'])->name('storeops.documents.initialize');
    
    // The Unified Workspace (Renders as Editor or Viewer based on Document State)
    Route::get('/documents/{type}/{id}', [DocumentWorkspaceController::class, 'workspace'])->name('storeops.documents.workspace');
    
    // Dynamic Product Profile Endpoint (Resolves capabilities & validation requirements)
    Route::get('/products/{type}/{id}/profile', [DocumentWorkspaceController::class, 'productProfile'])->name('storeops.products.profile');

    // AJAX / Form Processing Endpoints
    Route::post('/documents/{type}/{id}/draft', [DocumentWorkspaceController::class, 'saveDraft'])->name('storeops.documents.draft');
    Route::get('/documents/{type}/{id}/preview', [DocumentWorkspaceController::class, 'preview'])->name('storeops.documents.preview');
    Route::post('/documents/{type}/{id}/post', [DocumentWorkspaceController::class, 'post'])->name('storeops.documents.post');
    Route::get('/documents/{type}/{id}/print', [DocumentWorkspaceController::class, 'print'])->name('storeops.documents.print');
    
    // Unified Product Search API (Powers the Select2 Spreadsheet Grid)
    Route::get('/api/products/search', [DocumentWorkspaceController::class, 'searchProducts'])->name('storeops.api.products.search');

});