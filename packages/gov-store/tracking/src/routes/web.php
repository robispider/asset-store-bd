<?php

use Illuminate\Support\Facades\Route;
use GovStore\Tracking\Http\Controllers\TrackingTypeController;
use GovStore\Tracking\Http\Controllers\TrackingReferenceController;
use GovStore\Tracking\Http\Controllers\TrackingDocumentController;
use GovStore\Tracking\Http\Controllers\TrackingTargetController;
use GovStore\Tracking\Http\Controllers\TrackingScopeController;
use GovStore\Tracking\Http\Controllers\TrackingDashboardController;
use GovStore\Tracking\Http\Controllers\TrackingRetrospectiveController;

Route::resource('types', TrackingTypeController::class)->except(['show']);
Route::resource('references', TrackingReferenceController::class);

Route::post('references/{reference}/documents', [TrackingDocumentController::class, 'store'])
    ->name('documents.store');
Route::get('documents/{document}/download', [TrackingDocumentController::class, 'download'])
    ->name('documents.download');
Route::delete('documents/{document}', [TrackingDocumentController::class, 'destroy'])
    ->name('documents.destroy');

Route::resource('references.targets', TrackingTargetController::class)->only(['index', 'store', 'destroy']);
Route::resource('references.scopes', TrackingScopeController::class)->only(['index', 'store', 'destroy']);

Route::get('references/{reference}/dashboard', [TrackingDashboardController::class, 'show'])
    ->name('references.dashboard');

// Retrospective Tagging Routes
Route::get('references/{reference}/retrospective', [TrackingRetrospectiveController::class, 'index'])
    ->name('references.retrospective.index');
Route::post('references/{reference}/retrospective/associate', [TrackingRetrospectiveController::class, 'associate'])
    ->name('references.retrospective.associate');