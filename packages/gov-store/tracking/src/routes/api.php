<?php

use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use Illuminate\Support\Facades\Route; // Added Import

/*
|--------------------------------------------------------------------------
| Tracking API Routes (Headless Handshake Contracts)
|--------------------------------------------------------------------------
*/

// Handshake A1: Header-Level Verification
Route::get('/verify-code', [TrackingEvaluationController::class, 'verifyCode'])
    ->name('verify-code')->middleware('gov.can:tracking.execution.verify');

// Handshake A2: Line-Item-Level Evaluation
Route::get('/evaluate', [TrackingEvaluationController::class, 'evaluate'])
    ->name('evaluate')->middleware('gov.can:tracking.execution.verify');

// Handshake A3 (New): Dynamic Geographic & Organizational Office Search
Route::get('/search-offices', [TrackingCodeController::class, 'searchOffices'])
    ->name('search-offices')->middleware('gov.can:tracking.codes.manage');
