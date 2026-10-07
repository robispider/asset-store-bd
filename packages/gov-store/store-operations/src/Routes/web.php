<?php

use GovStore\StoreOperations\Http\Controllers\DocumentWorkspaceController;
use GovStore\StoreOperations\Http\Controllers\ProfileAdminController;
use GovStore\StoreOperations\Http\Controllers\StockRegisterController;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'gov-store/operations',
    'middleware' => ['web', 'auth', InitializeTenantContext::class],
], function () {

    // =========================================================================
    // 1. Stock Register & Kardex (Preserved Core Dashboards)
    // =========================================================================
    Route::get('/register', [StockRegisterController::class, 'index'])->middleware('gov.can:storeops.documents.view')->name('storeops.register.index');
    Route::get('/kardex/{type}/{id}', [StockRegisterController::class, 'kardex'])->middleware('gov.can:storeops.documents.view')->name('storeops.register.kardex');

    // =========================================================================
    // 2. Generic Document Engine (The Unified Workspace & Hub)
    // =========================================================================

    // The Operational Hub (Listings Dashboard)
    Route::get('/hub', [DocumentWorkspaceController::class, 'hub'])->middleware('gov.can:storeops.documents.view')->name('storeops.hub');

    // Document Initialization (Creates empty DRAFT in database and redirects)
    Route::post('/documents/initialize', [DocumentWorkspaceController::class, 'initialize'])->middleware('gov.can:storeops.documents.draft')->name('storeops.documents.initialize');

    // The Unified Workspace (Renders as Editor or Viewer based on Document State)
    Route::get('/documents/{type}/{id}', [DocumentWorkspaceController::class, 'workspace'])->middleware('gov.can:storeops.documents.view')->name('storeops.documents.workspace');

    // Dynamic Product Profile Endpoint (Resolves capabilities & validation requirements)
    Route::get('/products/{type}/{id}/profile', [DocumentWorkspaceController::class, 'productProfile'])->middleware('gov.can:storeops.documents.view')->name('storeops.products.profile');

    // AJAX / Form Processing Endpoints
    Route::post('/documents/{type}/{id}/draft', [DocumentWorkspaceController::class, 'saveDraft'])->middleware('gov.can:storeops.documents.draft')->name('storeops.documents.draft');
    Route::post('/documents/{type}/{id}/void', [DocumentWorkspaceController::class, 'voidDraft'])->middleware('gov.can:storeops.documents.draft')->name('storeops.documents.void');
    Route::get('/documents/{type}/{id}/preview', [DocumentWorkspaceController::class, 'preview'])->middleware('gov.can:storeops.documents.view')->name('storeops.documents.preview');
    Route::post('/documents/{type}/{id}/post', [DocumentWorkspaceController::class, 'post'])->middleware('gov.can:storeops.documents.post')->name('storeops.documents.post');
    Route::get('/documents/{type}/{id}/print', [DocumentWorkspaceController::class, 'print'])->middleware('gov.can:storeops.documents.view')->name('storeops.documents.print');

    // 4. File Attachment Endpoints (Phase 5 - ADDED BELOW)
    Route::post('/documents/{type}/{id}/attachments', [DocumentWorkspaceController::class, 'uploadAttachment'])->middleware('gov.can:storeops.documents.draft')->name('storeops.documents.attachments.upload');
    Route::delete('/documents/{type}/{id}/attachments/{attachmentId}', [DocumentWorkspaceController::class, 'deleteAttachment'])->middleware('gov.can:storeops.documents.draft')->name('storeops.documents.attachments.delete');
    Route::get('/documents/{type}/{id}/attachments/{attachmentId}', [DocumentWorkspaceController::class, 'downloadAttachment'])->middleware('gov.can:storeops.documents.view')->name('storeops.documents.attachments.download');
    Route::post('/documents/{type}/{id}/takeover', [DocumentWorkspaceController::class, 'takeover'])->middleware('gov.can:storeops.documents.draft')->name('storeops.documents.takeover');

    // Unified Product Search API (Powers the Select2 Spreadsheet Grid)
    Route::get('/api/products/search', [DocumentWorkspaceController::class, 'searchProducts'])->middleware('gov.can:storeops.documents.view')->name('storeops.api.products.search');

    // =========================================================================
    // 3. Administrative Policy Studio (Product Rules Configuration)
    // =========================================================================
    Route::get('/settings/product-rules', [ProfileAdminController::class, 'index'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.index');
    Route::get('/settings/product-rules/inspector', [ProfileAdminController::class, 'inspector'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.inspector');
    Route::post('/settings/product-rules/assign', [ProfileAdminController::class, 'assignPolicy'])->middleware('gov.can:storeops.rules.publish')->name('storeops.admin.rules.assign');

    Route::get('/settings/product-rules/policies/{id}/edit', [ProfileAdminController::class, 'editPolicy'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.policies.edit');
    Route::post('/settings/product-rules/policies/{id}/draft', [ProfileAdminController::class, 'saveDraftPolicy'])->middleware('gov.can:storeops.rules.publish')->name('storeops.admin.rules.policies.draft');

    Route::get('/settings/product-rules/simulator', [ProfileAdminController::class, 'simulator'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.simulator');
    Route::get('/settings/product-rules/simulator/run', [ProfileAdminController::class, 'runSimulation'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.simulator.run');

    Route::get('/settings/product-rules/policies/{id}/impact', [ProfileAdminController::class, 'getImpactAnalysis'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.policies.impact');
    Route::post('/settings/product-rules/policies/{id}/publish', [ProfileAdminController::class, 'publishPolicy'])->middleware('gov.can:storeops.rules.publish')->name('storeops.admin.rules.policies.publish');
    Route::post('/settings/product-rules/assign-gpo', [ProfileAdminController::class, 'assignPolicy'])->middleware('gov.can:storeops.rules.publish')->name('storeops.admin.rules.assign_gpo');
    Route::post('/settings/product-rules/assignments/{id}/unassign', [ProfileAdminController::class, 'unassignPolicy'])->middleware('gov.can:storeops.rules.publish')->name('storeops.admin.rules.unassign');

    Route::get('/settings/product-rules/search-api', [ProfileAdminController::class, 'searchApi'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.search_api');

    Route::get('/settings/product-rules/policies/create/{template}', [ProfileAdminController::class, 'createRule'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.policies.create');
    Route::post('/settings/product-rules/policies/store', [ProfileAdminController::class, 'storeRule'])->middleware('gov.can:storeops.rules.publish')->name('storeops.admin.rules.policies.store');
    Route::get('/settings/product-rules/policies/{id}/confirmation', [ProfileAdminController::class, 'confirmationHub'])->middleware('gov.can:storeops.rules.view')->name('storeops.admin.rules.policies.confirmation');
    Route::post('/settings/product-rules/policies/{id}/duplicate', [ProfileAdminController::class, 'duplicateRule'])->middleware('gov.can:storeops.rules.publish')->name('storeops.admin.rules.policies.duplicate');

    Route::get('/documents/render-meta', [DocumentWorkspaceController::class, 'renderMeta'])->middleware('gov.can:storeops.documents.view')->name('storeops.documents.render_meta');

});
