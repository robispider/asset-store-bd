<?php

use Illuminate\Support\Facades\Route;
use GovStore\Metadata\Http\Controllers\MetadataHealthController;

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('gov-store/admin/metadata/health', [MetadataHealthController::class, 'show'])
         ->name('gov.meta.health');
});