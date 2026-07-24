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
    
     // 4. File Attachment Endpoints (Phase 5 - ADDED BELOW)
    Route::post('/documents/{type}/{id}/attachments', [DocumentWorkspaceController::class, 'uploadAttachment'])->name('storeops.documents.attachments.upload');
    Route::delete('/documents/{type}/{id}/attachments/{attachmentId}', [DocumentWorkspaceController::class, 'deleteAttachment'])->name('storeops.documents.attachments.delete');
    
    // Unified Product Search API (Powers the Select2 Spreadsheet Grid)
    Route::get('/api/products/search', [DocumentWorkspaceController::class, 'searchProducts'])->name('storeops.api.products.search');

    // =========================================================================
    // 3. Administrative Policy Studio (Product Rules Configuration)
    // =========================================================================
    Route::get('/settings/product-rules', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'index'])->name('storeops.admin.rules.index');
    Route::get('/settings/product-rules/inspector', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'inspector'])->name('storeops.admin.rules.inspector');
    Route::post('/settings/product-rules/assign', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'assignPolicy'])->name('storeops.admin.rules.assign');


    Route::get('/settings/product-rules/policies/{id}/edit', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'editPolicy'])->name('storeops.admin.rules.policies.edit');
    Route::post('/settings/product-rules/policies/{id}/draft', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'saveDraftPolicy'])->name('storeops.admin.rules.policies.draft');

    Route::get('/settings/product-rules/simulator', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'simulator'])->name('storeops.admin.rules.simulator');
    Route::get('/settings/product-rules/simulator/run', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'runSimulation'])->name('storeops.admin.rules.simulator.run');

    Route::get('/settings/product-rules/policies/{id}/impact', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'getImpactAnalysis'])->name('storeops.admin.rules.policies.impact');
    Route::post('/settings/product-rules/policies/{id}/publish', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'publishPolicy'])->name('storeops.admin.rules.policies.publish');
Route::post('/settings/product-rules/assign-gpo', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'assignPolicy'])->name('storeops.admin.rules.assign');
Route::post('/settings/product-rules/assignments/{id}/unassign', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'unassignPolicy'])->name('storeops.admin.rules.unassign');

Route::get('/settings/product-rules/search-api', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'searchApi'])->name('storeops.admin.rules.search_api');

Route::get('/settings/product-rules/policies/create/{template}', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'createRule'])->name('storeops.admin.rules.policies.create');
Route::post('/settings/product-rules/policies/store', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'storeRule'])->name('storeops.admin.rules.policies.store');
Route::get('/settings/product-rules/policies/{id}/confirmation', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'confirmationHub'])->name('storeops.admin.rules.policies.confirmation');
Route::post('/settings/product-rules/policies/{id}/duplicate', [\GovStore\StoreOperations\Http\Controllers\ProfileAdminController::class, 'duplicateRule'])->name('storeops.admin.rules.policies.duplicate');


});