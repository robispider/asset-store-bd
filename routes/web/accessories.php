<?php

use App\Http\Controllers\Accessories;
use App\Http\Controllers\BulkAccessoriesController;
use Illuminate\Support\Facades\Route;

/*
* Accessories
 */
Route::group(['prefix' => 'accessories', 'middleware' => ['auth']], function () {
    Route::get(
        '{accessory}/checkout',
        [Accessories\AccessoryCheckoutController::class, 'create']
    )->name('accessories.checkout.show');

    Route::post(
        '{accessory}/checkout',
        [Accessories\AccessoryCheckoutController::class, 'store']
    )->name('accessories.checkout.store');

    Route::get(
        '{accessoryID}/checkin/{backto?}',
        [Accessories\AccessoryCheckinController::class, 'create']
    )->name('accessories.checkin.show');

    Route::post(
        '{accessoryID}/checkin/{backto?}',
        [Accessories\AccessoryCheckinController::class, 'store']
    )->name('accessories.checkin.store');

    Route::get('{accessory}/clone',
        [Accessories\AccessoriesController::class, 'getClone']
    )->name('clone/accessories');

    Route::post('{accessory}/clone',
        [Accessories\AccessoriesController::class, 'postCreate']
    );

});

Route::resource('accessories', Accessories\AccessoriesController::class, [
    'middleware' => ['auth'],
]);

Route::post('accessories/bulk/delete', [BulkAccessoriesController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('accessories.bulk.delete');
