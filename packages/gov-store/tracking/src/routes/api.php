<?php

use Illuminate\Support\Facades\Route;
use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;

/*
|--------------------------------------------------------------------------
| Tracking API Routes (Headless Handshake Contracts)
|--------------------------------------------------------------------------
| These routes are consumed by the store-operations (GRN) capability plugins
| to evaluate organizational scopes and target progression ratios.
*/

// Handshake A1: Header-Level Verification (The Code Entry Phase)
Route::get('/verify-code', [TrackingEvaluationController::class, 'verifyCode'])
    ->name('verify-code');

// Handshake A2: Line-Item-Level Evaluation (The Category & Qty Phase)
Route::get('/evaluate', [TrackingEvaluationController::class, 'evaluate'])
    ->name('evaluate');