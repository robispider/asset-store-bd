<?php

use Illuminate\Support\Facades\Route;
use GovStore\Metadata\Http\Controllers\MetadataHealthController;

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('gov-store/admin/metadata/health', [MetadataHealthController::class, 'show'])
         ->middleware('gov.can:metadata.national.manage')
         ->name('gov.meta.health');
         
  // POST route to safely trigger the background queue process
    Route::post('gov-store/admin/metadata/converge', [MetadataHealthController::class, 'trigger'])
         ->middleware('gov.can:metadata.national.manage')
         ->name('gov.meta.converge.trigger');

});
