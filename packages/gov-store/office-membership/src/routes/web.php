<?php

use Illuminate\Support\Facades\Route;
use GovStore\OfficeMembership\Http\Controllers\MembershipController;
use GovStore\OfficeMembership\Http\Controllers\RoleHandshakeController;
use GovStore\OfficeMembership\Http\Controllers\MembershipAdminController;

Route::group(['middleware' => ['web', 'auth'], 'prefix' => 'gov-store/my-memberships'], function () {
    // 1. Employee-Facing Clearance
    Route::get('/', [MembershipController::class, 'index'])->name('gov.membership.index');
    Route::post('/{id}/request-release', [MembershipController::class, 'requestRelease'])->name('gov.membership.request-release');
    Route::post('/switch', [MembershipController::class, 'switchContext'])->name('gov.membership.switch');

    // 2. Peer-to-Peer Handshakes
    Route::post('/handshake/propose', [RoleHandshakeController::class, 'propose'])->name('gov.membership.handshake.propose');
    Route::post('/handshake/{id}/accept', [RoleHandshakeController::class, 'accept'])->name('gov.membership.handshake.accept');
    Route::post('/handshake/{id}/reject', [RoleHandshakeController::class, 'reject'])->name('gov.membership.handshake.reject');
    Route::post('/handshake/{id}/cancel', [RoleHandshakeController::class, 'cancel'])->name('gov.membership.handshake.cancel');

    // 3. Office Admin Onboarding & Claims
    Route::post('/claim/{locationId}', [MembershipAdminController::class, 'claimEmployee'])->name('gov.membership.claim');

    // 4. Superadmin Emergency Compliance Overrides
    Route::get('/override/console', [MembershipAdminController::class, 'overrideConsole'])->name('gov.membership.override.console');
    Route::post('/override/force', [MembershipAdminController::class, 'forceOverride'])->name('gov.membership.override');

    // 5. Onboard Existing unprovisioned Location routes
    Route::get('/onboard', [\GovStore\Organization\Http\Controllers\OnboardLocationController::class, 'create'])->name('gov.org.provisioning.onboard');
    Route::post('/onboard', [\GovStore\Organization\Http\Controllers\OnboardLocationController::class, 'store'])->name('gov.org.provisioning.onboard.store');
    
});