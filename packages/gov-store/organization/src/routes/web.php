<?php

use Illuminate\Support\Facades\Route;
use GovStore\Organization\Http\Controllers\ProvisioningController;
use GovStore\Organization\Http\Controllers\OfficeHubController;
use GovStore\Organization\Http\Controllers\ConfigurationController;
use GovStore\Organization\Http\Controllers\OnboardLocationController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/admin/organization'], function () {
    
    // 1. Office Registry Dashboard & Focused Creator
    Route::get('/', [ProvisioningController::class, 'index'])->name('gov.org.provisioning.index');
    Route::get('/create', [ProvisioningController::class, 'create'])->name('gov.org.provisioning.create');
    Route::post('/store', [ProvisioningController::class, 'provision'])->name('gov.org.provisioning.store');
    
    // 2. Select2 AJAX Geo Search & Pre-check Duplicates API
    Route::get('/geo-search', [ProvisioningController::class, 'geoSearch'])->name('gov.org.provisioning.geo-search');
    Route::get('/check-duplicate', [ProvisioningController::class, 'checkDuplicate'])->name('gov.org.provisioning.check-duplicate');

    // 3. Admin Settings: ICT Officer Jurisdictions (The Setup Tag)
    Route::get('/jurisdictions', [ProvisioningController::class, 'jurisdictionsIndex'])->name('gov.org.jurisdictions.index');
    Route::post('/jurisdictions/store', [ProvisioningController::class, 'jurisdictionsStore'])->name('gov.org.jurisdictions.store');
    Route::post('/jurisdictions/delete/{id}', [ProvisioningController::class, 'jurisdictionsDestroy'])->name('gov.org.jurisdictions.destroy');

    // 4. Onboard Existing unprovisioned Location routes (MAPPING CORE BUILDINGS)
    Route::get('/onboard', [OnboardLocationController::class, 'create'])->name('gov.org.provisioning.onboard');
    Route::post('/onboard/store', [OnboardLocationController::class, 'store'])->name('gov.org.provisioning.onboard.store');

    // 5. The Centralized Office Hub Dashboard Core
    Route::get('/{id}/hub', [OfficeHubController::class, 'show'])->name('gov.org.hub.show');
    Route::post('/{id}/update', [OfficeHubController::class, 'update'])->name('gov.org.hub.update');
    Route::post('/{id}/save-roles', [OfficeHubController::class, 'saveRoles'])->name('gov.org.hub.save-roles');
    Route::post('/{id}/verify-geo', [OfficeHubController::class, 'verifyGeo'])->name('gov.org.hub.verify-geo');
    
});

// 6. Scoped Local Office Admin Activation checklist endpoints (Bypasses admin prefix)
Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/office'], function () {
    Route::get('/', [ConfigurationController::class, 'index'])->name('gov.org.config.index');
    Route::post('/save', [ConfigurationController::class, 'save'])->name('gov.org.config.save');
});