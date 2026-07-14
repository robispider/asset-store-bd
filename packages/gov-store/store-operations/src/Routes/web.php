<?php

use Illuminate\Support\Facades\Route;
use GovStore\StoreOperations\Http\Controllers\GoodsReceiptController;
use GovStore\StoreOperations\Http\Controllers\GoodsIssueController;
use GovStore\StoreOperations\Http\Controllers\StockRegisterController;

Route::group([
    'prefix' => 'gov-store/operations', 
    'middleware' => ['web', 'auth', \GovStore\TenantScope\Http\Middleware\InitializeTenantContext::class]
], function () {
    
    // Goods Receipt Workflows
    Route::get('/receipts/create', [GoodsReceiptController::class, 'create'])->name('storeops.receipts.create');
    Route::post('/receipts/store', [GoodsReceiptController::class, 'store'])->name('storeops.receipts.store');
    Route::post('/receipts/{id}/submit', [GoodsReceiptController::class, 'submit'])->name('storeops.receipts.submit');

    // Stock Register & Kardex (Audit Trail)
    Route::get('/register', [StockRegisterController::class, 'index'])->name('storeops.register.index');
    Route::get('/kardex/{type}/{id}', [StockRegisterController::class, 'kardex'])->name('storeops.register.kardex');

    // Goods Issue Workflows
    Route::get('/issues/create', [GoodsIssueController::class, 'create'])->name('storeops.issues.create');
    Route::post('/issues/store', [GoodsIssueController::class, 'store'])->name('storeops.issues.store');

});
