<?php

use GovStore\CustomRequests\Http\Controllers\BasketController;
use GovStore\CustomRequests\Http\Controllers\FulfillmentRegisterController;
use GovStore\CustomRequests\Http\Controllers\GovApprovalController;
use GovStore\CustomRequests\Http\Controllers\GovFulfillmentController;
use GovStore\CustomRequests\Http\Controllers\GovRequestController;
use Illuminate\Support\Facades\Route;

// We wrap our routes in the standard web and auth middleware so only logged-in Snipe-IT users can access them.
Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-requests'], function () {

    // NEW: User Route: Browse all requestable items (The Catalog)
    Route::get('/catalog', [GovRequestController::class, 'catalog'])->middleware('gov.can:requests.submit')->name('gov.requests.catalog');
    // NEW: User Route: View my own requests
    Route::get('/my-requests', [GovRequestController::class, 'index'])->middleware('gov.can:requests.submit')->name('gov.requests.user.index');
    Route::post('/my-requests/{id}/withdraw', [GovRequestController::class, 'withdraw'])->middleware('gov.can:requests.submit')->name('gov.requests.withdraw');
    Route::post('/my-requests/{id}/receive', [GovRequestController::class, 'receive'])->middleware('gov.can:requests.submit')->name('gov.requests.receive');
    Route::post('/my-requests/{id}/return', [GovRequestController::class, 'requestReturn'])->middleware('gov.can:requests.submit')->name('gov.requests.return');
    // Basket Routes
    Route::get('/basket', [BasketController::class, 'index'])->middleware('gov.can:requests.submit')->name('gov.requests.basket.index');
    Route::post('/basket/add', [BasketController::class, 'add'])->middleware('gov.can:requests.submit')->name('gov.requests.basket.add');
    Route::post('/basket/update', [BasketController::class, 'updateQty'])->middleware('gov.can:requests.submit')->name('gov.requests.basket.update');
    Route::post('/basket/remove/{id}', [BasketController::class, 'remove'])->middleware('gov.can:requests.submit')->name('gov.requests.basket.remove');
    Route::post('/basket/submit', [BasketController::class, 'submit'])->middleware('gov.can:requests.submit')->name('gov.requests.basket.submit');

    // Admin Approval Panel Routes
    Route::get('/admin', [GovApprovalController::class, 'index'])->middleware('gov.can:requests.approve')->name('gov.requests.admin.index');
    Route::get('/admin/{id}', [GovApprovalController::class, 'show'])->middleware('gov.can:requests.approve')->name('gov.requests.admin.show');
    Route::post('/admin/{id}/process', [GovApprovalController::class, 'process'])->middleware('gov.can:requests.approve')->name('gov.requests.admin.process');

    // Fulfillment Queue Routes
    Route::get('/fulfillment', [GovFulfillmentController::class, 'index'])->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.index');
    Route::get('/fulfillment/{id}', [GovFulfillmentController::class, 'show'])->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.show');
    Route::post('/fulfillment/{id}/issue', [GovFulfillmentController::class, 'process'])->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.process');
    Route::post('/fulfillment/{id}/close', [GovFulfillmentController::class, 'close'])->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.close');

    // Ajax Catalog Search for substitutions
    Route::get('/catalog/search', [GovRequestController::class, 'search'])->middleware('gov.can:requests.submit')->name('gov.requests.catalog.search');

    // Admin Settings: Office Location Assignments

    // Admin Settings: Category Policies
    Route::get('/admin/settings/policies', [GovApprovalController::class, 'policiesIndex'])->middleware('gov.can:requests.configure')->name('gov.requests.admin.policies.index');
    Route::post('/admin/settings/policies/store', [GovApprovalController::class, 'policiesStore'])->middleware('gov.can:requests.configure')->name('gov.requests.admin.policies.store');

    // Fulfillment Register (Completed service requests and their ledger documents)
    Route::get('/fulfillment-register', [FulfillmentRegisterController::class, 'index'])->middleware('gov.can:storeops.documents.view')->name('gov.requests.fulfillment_register.index');
    Route::get('/fulfillment-register/{id}', [FulfillmentRegisterController::class, 'show'])->middleware('gov.can:storeops.documents.view')->name('gov.requests.fulfillment_register.show');
    Route::post('/fulfillment-register/{id}/return', [FulfillmentRegisterController::class, 'draftReturn'])->middleware('gov.can:storeops.documents.draft')->name('gov.requests.fulfillment_register.return');

});
