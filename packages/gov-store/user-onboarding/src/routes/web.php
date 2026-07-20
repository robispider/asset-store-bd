<?php

use Illuminate\Support\Facades\Route;
use GovStore\UserOnboarding\Http\Controllers\UserOnboardingController;

Route::group([
    'prefix' => 'gov-store/admin/onboard', 
    'middleware' => ['web', 'auth', \GovStore\TenantScope\Http\Middleware\InitializeTenantContext::class]
], function () {
    
    Route::get('/', [UserOnboardingController::class, 'index'])->name('gov.onboard.index');
    Route::post('/assign', [UserOnboardingController::class, 'assign'])->name('gov.onboard.assign');

});
