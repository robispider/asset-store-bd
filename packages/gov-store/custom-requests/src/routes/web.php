<?php

use GovStore\CustomRequests\Http\Controllers\BasketController;
use GovStore\CustomRequests\Http\Controllers\FulfillmentRegisterController;
use GovStore\CustomRequests\Http\Controllers\GovApprovalController;
use GovStore\CustomRequests\Http\Controllers\GovFulfillmentController;
use GovStore\CustomRequests\Http\Controllers\GovRequestController;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

// Breadcrumb label for a request: its number, read only from the working office's own requests.
$number = fn ($id) => ServiceRequest::whereKey((int) $id)->value('request_number') ?? '';
$t = fn (string $key, array $replace = []) => __('requestlabels::requests.'.$key, $replace);

// We wrap our routes in the standard web and auth middleware so only logged-in Snipe-IT users can access them.
Route::group(['middleware' => ['web', 'auth', \GovStore\Organization\Http\Middleware\EnsureOfficeRequestIntake::class], 'prefix' => 'gov-requests'], function () use ($number, $t) {

    // Employee: Request items → Draft request → My requests → Request page.
    Route::get('/catalog', [GovRequestController::class, 'catalog'])->middleware('gov.can:requests.submit')->name('gov.requests.catalog')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('catalog_title'), route('gov.requests.catalog')));
    Route::get('/my-requests', [GovRequestController::class, 'index'])->middleware('gov.can:requests.submit')->name('gov.requests.user.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('my_title'), route('gov.requests.user.index')));
    // Only the requester, in the request's office (404 otherwise, as everywhere else).
    Route::get('/my-requests/{id}', [GovRequestController::class, 'show'])->whereNumber('id')->middleware('gov.can:requests.submit')->name('gov.requests.user.show')
        ->breadcrumbs(fn (Trail $trail, $id) => $trail->parent('gov.requests.user.index')->push($number($id), route('gov.requests.user.show', $id)));
    Route::post('/my-requests/{id}/withdraw', [GovRequestController::class, 'withdraw'])->whereNumber('id')->middleware('gov.can:requests.submit')->name('gov.requests.withdraw');
    Route::post('/my-requests/{id}/receive', [GovRequestController::class, 'receive'])->whereNumber('id')->middleware('gov.can:requests.submit')->name('gov.requests.receive');
    Route::post('/my-requests/{id}/return', [GovRequestController::class, 'requestReturn'])->whereNumber('id')->middleware('gov.can:requests.submit')->name('gov.requests.return');
    Route::post('/my-requests/{id}/again', [BasketController::class, 'again'])->whereNumber('id')->middleware('gov.can:requests.submit')->name('gov.requests.again');
    // Draft request (the basket)
    Route::get('/basket', [BasketController::class, 'index'])->middleware('gov.can:requests.submit')->name('gov.requests.basket.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('gov.requests.catalog')->push($t('draft_title'), route('gov.requests.basket.index')));
    // Draft changes and sending are rate-limited per person (CustomRequestServiceProvider::rateLimits).
    Route::post('/basket/add', [BasketController::class, 'add'])->middleware(['gov.can:requests.submit', 'throttle:gov-requests-draft'])->name('gov.requests.basket.add');
    Route::post('/basket/update', [BasketController::class, 'updateQty'])->middleware(['gov.can:requests.submit', 'throttle:gov-requests-draft'])->name('gov.requests.basket.update');
    Route::post('/basket/remove/{id}', [BasketController::class, 'remove'])->middleware(['gov.can:requests.submit', 'throttle:gov-requests-draft'])->name('gov.requests.basket.remove');
    Route::post('/basket/submit', [BasketController::class, 'submit'])->middleware(['gov.can:requests.submit', 'throttle:gov-requests-send'])->name('gov.requests.basket.submit');
    // Sent: one submission's requests, for their owner in that office only (404 otherwise).
    Route::get('/sent/{batch}', [BasketController::class, 'sent'])->whereUuid('batch')->middleware('gov.can:requests.submit')->name('gov.requests.sent')
        ->breadcrumbs(fn (Trail $trail, $batch) => $trail->parent('gov.requests.user.index')->push($t('sent_crumb'), route('gov.requests.sent', $batch)));

    // Approver: Approvals → Decide.
    Route::get('/admin', [GovApprovalController::class, 'index'])->middleware('gov.can:requests.approve')->name('gov.requests.admin.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('approvals_title'), route('gov.requests.admin.index')));
    Route::get('/admin/{id}', [GovApprovalController::class, 'show'])->whereNumber('id')->middleware('gov.can:requests.approve')->name('gov.requests.admin.show')
        ->breadcrumbs(fn (Trail $trail, $id) => $trail->parent('gov.requests.admin.index')->push($number($id), route('gov.requests.admin.show', $id)));
    Route::post('/admin/{id}/process', [GovApprovalController::class, 'process'])->whereNumber('id')->middleware('gov.can:requests.approve')->name('gov.requests.admin.process');

    // Storekeeper: To issue → Issue items.
    Route::get('/fulfillment', [GovFulfillmentController::class, 'index'])->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('toissue_title'), route('gov.requests.fulfillment.index')));
    Route::get('/fulfillment/{id}', [GovFulfillmentController::class, 'show'])->whereNumber('id')->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.show')
        ->breadcrumbs(fn (Trail $trail, $id) => $trail->parent('gov.requests.fulfillment.index')->push($number($id), route('gov.requests.fulfillment.show', $id)));
    Route::post('/fulfillment/{id}/issue', [GovFulfillmentController::class, 'process'])->whereNumber('id')->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.process');
    Route::post('/fulfillment/{id}/close', [GovFulfillmentController::class, 'close'])->whereNumber('id')->middleware('gov.can:requests.fulfill')->name('gov.requests.fulfillment.close');

    // Alternatives for Issue items: free count, price and whether the price rule allows it.
    Route::get('/catalog/search', [GovRequestController::class, 'search'])->middleware('gov.can:requests.submit')->name('gov.requests.catalog.search');

    // National administrator: Administration › Rules & settings › Request rules (every change is a national change).
    Route::get('/admin/settings/policies', [GovApprovalController::class, 'policiesIndex'])->middleware('gov.can:requests.configure')->name('gov.requests.admin.policies.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(__('tenantops::nav.zone_admin'))
            ->push(__('tenantops::nav.rules'))->push($t('rules_title'), route('gov.requests.admin.policies.index')));
    Route::get('/admin/settings/policies/items', [GovApprovalController::class, 'policyItems'])->middleware('gov.can:requests.configure')->name('gov.requests.admin.policies.items');
    Route::post('/admin/settings/policies/store', [GovApprovalController::class, 'policiesStore'])->middleware('gov.can:requests.configure')->name('gov.requests.admin.policies.store');
    Route::post('/admin/settings/policies/{id}/remove', [GovApprovalController::class, 'policiesDestroy'])->whereNumber('id')->middleware('gov.can:requests.configure')->name('gov.requests.admin.policies.destroy');

    // Issue register: completed requests and their goods issue documents.
    Route::get('/fulfillment-register', [FulfillmentRegisterController::class, 'index'])->middleware('gov.can:storeops.documents.view')->name('gov.requests.fulfillment_register.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push($t('register_title'), route('gov.requests.fulfillment_register.index')));
    Route::get('/fulfillment-register/{id}', [FulfillmentRegisterController::class, 'show'])->whereNumber('id')->middleware('gov.can:storeops.documents.view')->name('gov.requests.fulfillment_register.show')
        ->breadcrumbs(fn (Trail $trail, $id) => $trail->parent('gov.requests.fulfillment_register.index')->push($number($id), route('gov.requests.fulfillment_register.show', $id)));
    Route::post('/fulfillment-register/{id}/return', [FulfillmentRegisterController::class, 'draftReturn'])->whereNumber('id')->middleware('gov.can:storeops.documents.draft')->name('gov.requests.fulfillment_register.return');

});
