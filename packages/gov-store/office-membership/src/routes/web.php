<?php

use GovStore\OfficeMembership\Http\Controllers\MembershipAdminController;
use GovStore\OfficeMembership\Http\Controllers\MembershipController;
use GovStore\OfficeMembership\Http\Controllers\RoleHandshakeController;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::group(['middleware' => ['web', 'auth']], function () {

    // =========================================================================
    // 1. EMPLOYEE SELF-SERVICE DASHBOARD
    // =========================================================================
    Route::group(['prefix' => 'gov-store/my-memberships', 'middleware' => 'gov.can:membership.self'], function () {
        Route::get('/', [MembershipController::class, 'index'])->name('gov.membership.index')->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(__('office_membership::member.user_page_title'), route('gov.membership.index')));
        Route::post('/{id}/request-release', [MembershipController::class, 'requestRelease'])->name('gov.membership.request-release');
        Route::post('/switch', [MembershipController::class, 'switchContext'])->name('gov.membership.switch');

        // Token Generation & Joining
        Route::post('/token/generate', [MembershipController::class, 'generateVerificationToken'])->name('gov.membership.token.generate');
        Route::post('/join', [MembershipController::class, 'joinByCode'])->name('gov.membership.join');

        // Peer-to-Peer Handshakes
        Route::post('/handshake/propose', [RoleHandshakeController::class, 'propose'])->name('gov.membership.handshake.propose');
        Route::post('/handshake/{id}/accept', [RoleHandshakeController::class, 'accept'])->name('gov.membership.handshake.accept');
        Route::post('/handshake/{id}/reject', [RoleHandshakeController::class, 'reject'])->name('gov.membership.handshake.reject');
        Route::post('/handshake/{id}/cancel', [RoleHandshakeController::class, 'cancel'])->name('gov.membership.handshake.cancel');
    });

    // =========================================================================
    // 2. DEDICATED STAFF MANAGEMENT HUB (For Office Admins)
    // =========================================================================
    Route::group(['prefix' => 'gov-store/office/staff', 'middleware' => 'gov.can:membership.manage'], function () {
        Route::get('/', [MembershipAdminController::class, 'index'])->name('gov.membership.admin.index')->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(__('office_membership::member.user_page_title'), route('gov.membership.index'))->push(__('office_membership::member.nav_staff')));
        Route::post('/add-employee', [MembershipAdminController::class, 'addEmployeeByToken'])->name('gov.membership.admin.add-employee');
        Route::post('/generate-invite-code', [MembershipAdminController::class, 'generateInviteCode'])->name('gov.membership.admin.generate-invite-code');
        Route::post('/approve/{membershipId}', [MembershipAdminController::class, 'approveMembership'])->name('gov.membership.admin.approve');
        Route::post('/reject/{membershipId}', [MembershipAdminController::class, 'rejectMembership'])->name('gov.membership.admin.reject');
        Route::post('/release/{membershipId}', [MembershipAdminController::class, 'approveRelease'])->name('gov.membership.admin.release');
        Route::post('/claim', [MembershipAdminController::class, 'claimEmployee'])->name('gov.membership.claim');
    });

    // =========================================================================
    // 3. SUPERADMIN OVERRIDES
    // =========================================================================
    Route::group(['prefix' => 'gov-store/admin/memberships', 'middleware' => 'gov.can:membership.override'], function () {
        Route::get('/override/console', [MembershipAdminController::class, 'overrideConsole'])->name('gov.membership.override.console')->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(__('office_membership::member.user_page_title'), route('gov.membership.index'))->push(__('office_membership::member.override_console_title')));
        Route::post('/override/force', [MembershipAdminController::class, 'forceOverride'])->name('gov.membership.override');
    });
});
