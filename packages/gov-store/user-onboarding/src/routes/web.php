<?php

use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\UserOnboarding\Http\Controllers\UserOnboardingController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'gov-store/admin/onboard',
    'middleware' => ['web', 'auth', InitializeTenantContext::class],
], function () {

    Route::get('/', [UserOnboardingController::class, 'index'])->middleware('gov.can:onboarding.manage')->name('gov.onboard.index')->breadcrumbs(fn ($b) => $b->parent('home')->push(__('govonboard::onboard.title'), route('gov.onboard.index')));
    Route::get('/mine', [UserOnboardingController::class, 'mine'])->middleware('gov.can:onboarding.self')->name('gov.onboard.mine')->breadcrumbs(fn ($b) => $b->parent('home')->push(__('govonboard::onboard.my_title'), route('gov.onboard.mine')));
    Route::post('/assign', [UserOnboardingController::class, 'assign'])->middleware('gov.can:onboarding.manage')->name('gov.onboard.assign');
    Route::post('/decide', [UserOnboardingController::class, 'decide'])->middleware('gov.can:onboarding.manage')->name('gov.onboard.decide');

});
