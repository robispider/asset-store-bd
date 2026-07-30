<?php

use Illuminate\Support\Facades\Route;
use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;

/*
|--------------------------------------------------------------------------
| Tracking API Routes (Headless Handshake Only)
|--------------------------------------------------------------------------
*/

Route::get('/evaluate', [TrackingEvaluationController::class, 'evaluate'])->name('evaluate');
