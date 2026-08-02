<?php

use Illuminate\Support\Facades\Route;
use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController; // Added Import

/*
|--------------------------------------------------------------------------
| Tracking API Routes (Headless Handshake Contracts)
|--------------------------------------------------------------------------
*/

// Handshake A1: Header-Level Verification
Route::get('/verify-code', [TrackingEvaluationController::class, 'verifyCode'])
    ->name('verify-code');

// Handshake A2: Line-Item-Level Evaluation
Route::get('/evaluate', [TrackingEvaluationController::class, 'evaluate'])
    ->name('evaluate');

// Handshake A3 (New): Dynamic Geographic & Organizational Office Search
Route::get('/search-offices', [TrackingCodeController::class, 'searchOffices'])
    ->name('search-offices');