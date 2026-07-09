<?php

use Illuminate\Support\Facades\Route;
use GovStore\OfficeMembership\Http\Controllers\MembershipController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/my-memberships'], function () {
    Route::get('/', [MembershipController::class, 'index'])->name('gov.membership.index');
    Route::post('/{id}/request-release', [MembershipController::class, 'requestRelease'])->name('gov.membership.request-release');

       // Role Handshakes
    Route::post('/role/propose', [RoleAssignmentController::class, 'propose'])->name('gov.membership.role.propose');
    Route::post('/role/{id}/accept', [RoleAssignmentController::class, 'accept'])->name('gov.membership.role.accept');
    Route::post('/role/{id}/reject', [RoleAssignmentController::class, 'reject'])->name('gov.membership.role.reject');
    Route::post('/role/{id}/cancel', [RoleAssignmentController::class, 'cancel'])->name('gov.membership.role.cancel');

    // Context Switcher
    Route::post('/switch-context', [GovStore\OfficeMembership\Http\Controllers\MembershipAdminController::class, 'switchContext'])->name('gov.membership.switch');

    // Office Admin Claiming
    Route::post('/claim/{locationId}', [GovStore\OfficeMembership\Http\Controllers\MembershipAdminController::class, 'claimEmployee'])->name('gov.membership.claim');

    // Superadmin Override Console
    Route::post('/override/force', [GovStore\OfficeMembership\Http\Controllers\MembershipAdminController::class, 'forceOverride'])->name('gov.membership.override');
    Route::get('/override/console', function() {
        if (!auth()->user()->isSuperUser()) abort(403);
        $logs = \GovStore\OfficeMembership\Models\OverrideAuditLog::with(['targetUser', 'executor'])->orderBy('created_at', 'desc')->get();
        // Fetch users requesting release for easy action
        $pendingUsers = \App\Models\User::whereHas('memberships', function($q) { $q->where('status', 'release_requested'); })->get();
        return view('govmem::admin.override_console', compact('logs', 'pendingUsers'));
    })->name('gov.membership.override.console');
    
});