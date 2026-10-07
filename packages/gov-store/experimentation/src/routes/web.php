<?php

use GovStore\Experimentation\Http\Controllers\ExperimentController;
use GovStore\Experimentation\Http\Middleware\RequireExperimentAccess;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::middleware(['web', 'auth', RequireExperimentAccess::class, 'can:experiments.manage'])->prefix('gov-store/admin/experiments')->name('gov.experiments.')->group(function () {
    Route::get('/', [ExperimentController::class, 'index'])->name('index')->breadcrumbs(fn (Trail $trail) => $trail->push(__('experiments::ui.title'), route('gov.experiments.index')));
    Route::post('/', [ExperimentController::class, 'populate'])->name('populate');
    Route::get('/{run}', [ExperimentController::class, 'show'])->name('show')->breadcrumbs(fn (Trail $trail, $run) => $trail->parent('gov.experiments.index')->push(__('experiments::ui.dataset'), route('gov.experiments.show', $run)));
    Route::get('/{run}/status', [ExperimentController::class, 'status'])->name('status');
    Route::get('/{run}/accounts', [ExperimentController::class, 'accounts'])->name('accounts');
    Route::post('/{run}/resume', [ExperimentController::class, 'resume'])->name('resume');
    Route::post('/{run}/recover', [ExperimentController::class, 'recover'])->name('recover');
    Route::post('/{run}/cleanup', [ExperimentController::class, 'cleanup'])->name('cleanup');
    Route::get('/{run}/wipe-preview', [ExperimentController::class, 'preview'])->name('preview')->breadcrumbs(fn (Trail $trail, $run) => $trail->parent('gov.experiments.show', $run)->push(__('experiments::ui.wipe')));
    Route::post('/{run}/wipe', [ExperimentController::class, 'wipe'])->name('wipe');
});
