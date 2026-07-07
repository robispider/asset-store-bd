<?php

use Illuminate\Support\Facades\Route;
use GovStore\Organization\Http\Controllers\ProvisioningController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/admin/organization'], function () {
    
    // ICT Officer Office Management Endpoints
    Route::get('/', [ProvisioningController::class, 'index'])->name('gov.org.provisioning.index');
    Route::post('/provision', [ProvisioningController::class, 'provision'])->name('gov.org.provisioning.store');
    Route::post('/assign-admin', [ProvisioningController::class, 'assignAdmin'])->name('gov.org.provisioning.assign-admin');

 
    // Office Admin Role Configuration Routes
    Route::get('/office', [GovStore\Organization\Http\Controllers\ConfigurationController::class, 'index'])->name('gov.org.config.index');
    Route::post('/office/save', [GovStore\Organization\Http\Controllers\ConfigurationController::class, 'save'])->name('gov.org.config.save');

        // ICT Officer Office Management Endpoints
    Route::get('/', [ProvisioningController::class, 'index'])->name('gov.org.provisioning.index');
    Route::get('/geo-search', [ProvisioningController::class, 'geoSearch'])->name('gov.org.provisioning.geo-search'); // NEW
    Route::post('/provision', [ProvisioningController::class, 'provision'])->name('gov.org.provisioning.store');
    Route::post('/assign-admin', [ProvisioningController::class, 'assignAdmin'])->name('gov.org.provisioning.assign-admin');

    // Admin Settings: ICT Officer Jurisdictions (The ICTOfficerGeo Tag Setup)
    Route::get('/jurisdictions', [ProvisioningController::class, 'jurisdictionsIndex'])->name('gov.org.jurisdictions.index');
    Route::post('/jurisdictions/store', [ProvisioningController::class, 'jurisdictionsStore'])->name('gov.org.jurisdictions.store');
    Route::post('/jurisdictions/delete/{id}', [ProvisioningController::class, 'jurisdictionsDestroy'])->name('gov.org.jurisdictions.destroy');
});