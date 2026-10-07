<?php

use GovStore\Theming\Http\Controllers\AppearanceController;
use GovStore\Theming\Http\Controllers\AssignmentController;
use GovStore\Theming\Http\Controllers\DevAssetController;
use GovStore\Theming\Http\Controllers\ThemeLabController;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

// Compiled-on-request assets for local / staging (guests need them on the login page).
Route::get('gs-theme-dev/{file}', DevAssetController::class)
    ->where('file', '[A-Za-z0-9._/-]+')
    ->name('gs-theme.dev');

Route::middleware(['web', 'auth'])->prefix('gov')->group(function () {
    Route::get('appearance', [AppearanceController::class, 'index'])
        ->name('gs-theme.appearance')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')
            ->push(trans('gs-theme::appearance.title'), route('gs-theme.appearance')));
    Route::put('appearance', [AppearanceController::class, 'update'])->name('gs-theme.appearance.update');
    Route::post('appearance/mode', [AppearanceController::class, 'mode'])->name('gs-theme.appearance.mode');

    Route::get('appearance/assignments', [AssignmentController::class, 'index'])
        ->name('gs-theme.assignments')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('gs-theme.appearance')
            ->push(trans('gs-theme::appearance.assignments_title'), route('gs-theme.assignments')));
    Route::put('appearance/assignments/{scope}/{id?}', [AssignmentController::class, 'update'])
        ->where(['scope' => 'organization|company|office', 'id' => '[0-9]+'])
        ->name('gs-theme.assignments.update');

    Route::get('theme-lab', [ThemeLabController::class, 'index'])
        ->name('gs-theme.lab')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')
            ->push(trans('gs-theme::appearance.lab_title'), route('gs-theme.lab')));
    Route::get('theme-lab/{theme}', [ThemeLabController::class, 'focus'])
        ->where('theme', '[a-z0-9-]+')
        ->name('gs-theme.lab.focus')
        ->breadcrumbs(fn (Trail $trail, string $theme) => $trail->parent('gs-theme.lab')
            ->push($theme, route('gs-theme.lab.focus', $theme)));
});
