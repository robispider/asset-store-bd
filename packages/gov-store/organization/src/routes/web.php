<?php

use Illuminate\Support\Facades\Route;
use GovStore\Organization\Http\Controllers\ProvisioningController;
use GovStore\Organization\Http\Controllers\OfficeHubController;
use GovStore\Organization\Http\Controllers\ConfigurationController;
use GovStore\Organization\Http\Controllers\OnboardLocationController;
use GovStore\Organization\Http\Controllers\MinistryDirectoryController;
use GovStore\Organization\Http\Controllers\CompanyAdminController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/admin/organization'], function () {
    
    // 1. Office Registry Dashboard & Focused Creator
    Route::get('/', [ProvisioningController::class, 'index'])->middleware('gov.can:organization.office.manage')->name('gov.org.provisioning.index');
    Route::get('/create', [ProvisioningController::class, 'create'])->middleware('gov.can:organization.office.manage')->name('gov.org.provisioning.create');
    Route::post('/store', [ProvisioningController::class, 'provision'])->middleware('gov.can:organization.office.manage')->name('gov.org.provisioning.store');
    
    // 2. Select2 AJAX Geo Search & Pre-check Duplicates API
    Route::get('/geo-search', [ProvisioningController::class, 'geoSearch'])->middleware('gov.can:organization.office.manage')->name('gov.org.provisioning.geo-search');
    Route::get('/check-duplicate', [ProvisioningController::class, 'checkDuplicate'])->middleware('gov.can:organization.office.manage')->name('gov.org.provisioning.check-duplicate');

    // 3. Admin Settings: ICT Officer Jurisdictions (The Setup Tag)
    Route::get('/jurisdictions', [ProvisioningController::class, 'jurisdictionsIndex'])->middleware('gov.can:organization.national.manage')->name('gov.org.jurisdictions.index');
    Route::post('/jurisdictions/store', [ProvisioningController::class, 'jurisdictionsStore'])->middleware('gov.can:organization.national.manage')->name('gov.org.jurisdictions.store');
    Route::post('/jurisdictions/delete/{id}', [ProvisioningController::class, 'jurisdictionsDestroy'])->middleware('gov.can:organization.national.manage')->name('gov.org.jurisdictions.destroy');

    // 4. Onboard Existing unprovisioned Location routes (MAPPING CORE BUILDINGS)
    Route::get('/onboard', [OnboardLocationController::class, 'create'])->middleware('gov.can:organization.office.manage')->name('gov.org.provisioning.onboard');
    Route::post('/onboard/store', [OnboardLocationController::class, 'store'])->middleware('gov.can:organization.office.manage')->name('gov.org.provisioning.onboard.store');

    // 5. The Centralized Office Hub Dashboard Core
    Route::get('/{id}/hub', [OfficeHubController::class, 'show'])->middleware('gov.can:organization.office.manage')->name('gov.org.hub.show');
    Route::post('/{id}/update', [OfficeHubController::class, 'update'])->middleware('gov.can:organization.office.manage')->name('gov.org.hub.update');
    Route::post('/{id}/save-roles', [OfficeHubController::class, 'saveRoles'])->middleware('gov.can:organization.office.manage')->name('gov.org.hub.save-roles');
    Route::post('/{id}/verify-geo', [OfficeHubController::class, 'verifyGeo'])->middleware('gov.can:organization.office.manage')->name('gov.org.hub.verify-geo');
    Route::post('/{id}/lifecycle', [\GovStore\Organization\Http\Controllers\OfficeLifecycleController::class, 'update'])
        ->middleware('gov.can:organization.office.manage')->name('gov.org.hub.lifecycle');
    Route::post('/{id}/retry-starter', [OfficeHubController::class, 'retryStarter'])
        ->middleware('gov.can:organization.office.manage')->name('gov.org.hub.retry-starter');
    
});

    // 6. Government Directory Importer console (Superadmin only)
Route::group(['middleware' => ['web', 'auth', 'gov.can:organization.national.manage']], function () {
    Route::get('/directory', [MinistryDirectoryController::class, 'index'])->name('gov.org.directory.index');
    Route::post('/directory/import', [MinistryDirectoryController::class, 'import'])->name('gov.org.directory.import');
});

    // 7. Scoped Local Office Admin Activation checklist endpoints (Bypasses admin prefix)
Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/office'], function () {
    Route::get('/', [ConfigurationController::class, 'index'])->middleware('gov.can:organization.office.manage')->name('gov.org.config.index');
    Route::post('/save', [ConfigurationController::class, 'save'])->middleware('gov.can:organization.office.manage')->name('gov.org.config.save');

    Route::get('/company-admins', [CompanyAdminController::class, 'index'])->middleware('gov.can:organization.national.manage')->name('gov.org.company_admins.index');
    Route::post('/company-admins/store', [CompanyAdminController::class, 'store'])->middleware('gov.can:organization.national.manage')->name('gov.org.company_admins.store');
    Route::post('/company-admins/delete/{id}', [CompanyAdminController::class, 'destroy'])->middleware('gov.can:organization.national.manage')->name('gov.org.company_admins.destroy');
});
