<?php

use GovStore\TenantScope\Http\Controllers\AccessController;
use GovStore\TenantScope\Services\NationalChangeReview;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('gov-store/access')->name('gov.access.')->group(function () {
    Route::get('/', [AccessController::class, 'index'])->middleware('gov.can:access.view')->name('index');
    Route::post('/requests', [AccessController::class, 'store'])->middleware('gov.can:access.view')->name('requests.store');
    Route::get('/requests', [AccessController::class, 'inbox'])->middleware('gov.can:access.manage')->name('requests.index');
    Route::post('/requests/{id}', [AccessController::class, 'review'])->middleware('gov.can:access.manage')->name('requests.review');
    Route::get('/matrix', [AccessController::class, 'matrix'])->middleware('gov.can:access.matrix')->name('matrix');
    Route::get('/audit', [AccessController::class, 'audit'])->middleware('gov.can:access.audit')->name('audit');
    Route::get('/shadow', [AccessController::class, 'shadow'])->middleware('gov.can:access.shadow')->name('shadow');
    Route::get('/national-review/{token}', [NationalChangeReview::class, 'show'])->middleware('gov.can:access.audit')->name('national-review');
});
