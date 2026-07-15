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

    // NEW: User Route: Browse all requestable items (The Catalog)
    Route::get('/catalog', [GovRequestController::class, 'catalog'])->name('gov.requests.catalog');
    // NEW: User Route: View my own requests
    Route::get('/my-requests', [GovRequestController::class, 'index'])->name('gov.requests.user.index');
    // Basket Routes
    Route::get('/basket', [GovStore\CustomRequests\Http\Controllers\BasketController::class, 'index'])->name('gov.requests.basket.index');
    Route::post('/basket/add', [GovStore\CustomRequests\Http\Controllers\BasketController::class, 'add'])->name('gov.requests.basket.add');
    Route::post('/basket/update', [GovStore\CustomRequests\Http\Controllers\BasketController::class, 'updateQty'])->name('gov.requests.basket.update');
    Route::post('/basket/remove/{id}', [GovStore\CustomRequests\Http\Controllers\BasketController::class, 'remove'])->name('gov.requests.basket.remove');
    Route::post('/basket/submit', [GovStore\CustomRequests\Http\Controllers\BasketController::class, 'submit'])->name('gov.requests.basket.submit');

    // Admin Approval Panel Routes
    Route::get('/admin', [GovStore\CustomRequests\Http\Controllers\GovApprovalController::class, 'index'])->name('gov.requests.admin.index');
    Route::get('/admin/{id}', [GovStore\CustomRequests\Http\Controllers\GovApprovalController::class, 'show'])->name('gov.requests.admin.show');
    Route::post('/admin/{id}/process', [GovStore\CustomRequests\Http\Controllers\GovApprovalController::class, 'process'])->name('gov.requests.admin.process');

    // Fulfillment Queue Routes
    Route::get('/fulfillment', [GovStore\CustomRequests\Http\Controllers\GovFulfillmentController::class, 'index'])->name('gov.requests.fulfillment.index');
    Route::get('/fulfillment/{id}', [GovStore\CustomRequests\Http\Controllers\GovFulfillmentController::class, 'show'])->name('gov.requests.fulfillment.show');
    Route::post('/fulfillment/{id}/issue', [GovStore\CustomRequests\Http\Controllers\GovFulfillmentController::class, 'process'])->name('gov.requests.fulfillment.process');
    Route::post('/fulfillment/{id}/close', [GovStore\CustomRequests\Http\Controllers\GovFulfillmentController::class, 'close'])->name('gov.requests.fulfillment.close');

    // Ajax Catalog Search for substitutions
    Route::get('/catalog/search', [GovStore\CustomRequests\Http\Controllers\GovRequestController::class, 'search'])->name('gov.requests.catalog.search');

    // Admin Settings: Office Location Assignments
    Route::get('/admin/settings/locations', [GovStore\CustomRequests\Http\Controllers\GovApprovalController::class, 'locationsIndex'])->name('gov.requests.admin.locations.index');
    Route::post('/admin/settings/locations/store', [GovStore\CustomRequests\Http\Controllers\GovApprovalController::class, 'locationsStore'])->name('gov.requests.admin.locations.store');

    // Admin Settings: Category Policies
    Route::get('/admin/settings/policies', [GovStore\CustomRequests\Http\Controllers\GovApprovalController::class, 'policiesIndex'])->name('gov.requests.admin.policies.index');
    Route::post('/admin/settings/policies/store', [GovStore\CustomRequests\Http\Controllers\GovApprovalController::class, 'policiesStore'])->name('gov.requests.admin.policies.store');

    // Fulfillment Register (Completed service requests and their ledger documents)
    Route::get('/fulfillment-register', [GovStore\CustomRequests\Http\Controllers\FulfillmentRegisterController::class, 'index'])->name('gov.requests.fulfillment_register.index');
    Route::get('/fulfillment-register/{id}', [GovStore\CustomRequests\Http\Controllers\FulfillmentRegisterController::class, 'show'])->name('gov.requests.fulfillment_register.show');
    
});