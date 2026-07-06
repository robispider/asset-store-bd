<?php

use Illuminate\Support\Facades\Route;
use GovStore\CustomRequests\Http\Controllers\GovRequestController;
use GovStore\CustomRequests\Http\Controllers\GovApprovalController;

// We wrap our routes in the standard web and auth middleware so only logged-in Snipe-IT users can access them.
Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-requests'], function () {
    
    // User Route: Submitting a request for an item
    Route::post('/submit', [GovRequestController::class, 'store'])->name('gov.requests.store');
    
    // Admin Routes: Viewing and processing requests
    Route::get('/admin', [GovApprovalController::class, 'index'])->name('gov.requests.admin.index');
    Route::post('/admin/{request_id}/approve', [GovApprovalController::class, 'approve'])->name('gov.requests.admin.approve');
    Route::post('/admin/{request_id}/reject', [GovApprovalController::class, 'reject'])->name('gov.requests.admin.reject');

    
    // NEW: User Route: View my own requests
    Route::get('/my-requests', [GovRequestController::class, 'index'])->name('gov.requests.user.index');
});