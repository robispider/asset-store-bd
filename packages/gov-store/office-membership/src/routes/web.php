<?php

use GovStore\OfficeMembership\Http\Controllers\MembershipAdminController;
use GovStore\OfficeMembership\Http\Controllers\MembershipController;
use GovStore\OfficeMembership\Http\Controllers\RoleHandshakeController;
use GovStore\OfficeMembership\Models\OfficeMembership;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::group(['middleware' => ['web', 'auth']], function () {

    // =========================================================================
    // 1. MY OFFICES (every employee)
    // =========================================================================
    Route::group(['prefix' => 'gov-store/my-memberships', 'middleware' => 'gov.can:membership.self'], function () {
        Route::get('/', [MembershipController::class, 'index'])->name('gov.membership.index')
            ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(__('office_membership::member.user_page_title'), route('gov.membership.index')));
        Route::post('/switch', [MembershipController::class, 'switchContext'])->name('gov.membership.switch');
        Route::get('/offices', [MembershipController::class, 'offices'])->name('gov.membership.offices');

        // Join another office: check an office code first (throttled: it names the office), or make a personal code.
        Route::get('/join', [MembershipController::class, 'joinForm'])->middleware('throttle:30,1')->name('gov.membership.join.form')
            ->breadcrumbs(fn (Trail $trail) => $trail->parent('gov.membership.index')->push(__('office_membership::member.join_title'), route('gov.membership.join.form')));
        Route::post('/join', [MembershipController::class, 'joinByCode'])->name('gov.membership.join');
        Route::post('/{id}/cancel-join', [MembershipController::class, 'cancelJoin'])->whereNumber('id')->name('gov.membership.cancel-join');
        Route::post('/token/generate', [MembershipController::class, 'generateVerificationToken'])->name('gov.membership.token.generate');

        // Leave an office: guided checklist, request, then a transfer tracker.
        Route::get('/{id}/leave', [MembershipController::class, 'leave'])->whereNumber('id')->name('gov.membership.leave')
            ->breadcrumbs(fn (Trail $trail, $id) => $trail->parent('gov.membership.index')->push(__('office_membership::member.leave_crumb', [
                'office' => OfficeMembership::with('location')->where('user_id', auth()->id())->find((int) $id)?->location?->name ?? '',
            ]), route('gov.membership.leave', $id)));
        Route::get('/{id}/destinations', [MembershipController::class, 'destinations'])->whereNumber('id')->name('gov.membership.destinations');
        Route::post('/{id}/request-release', [MembershipController::class, 'requestRelease'])->whereNumber('id')->name('gov.membership.request-release');
        Route::post('/{id}/withdraw-release', [MembershipController::class, 'withdrawRelease'])->whereNumber('id')->name('gov.membership.withdraw-release');

        // Hand over a role: the colleague accepts or declines; the sender can cancel until then.
        Route::post('/handshake/propose', [RoleHandshakeController::class, 'propose'])->name('gov.membership.handshake.propose');
        Route::post('/handshake/{id}/accept', [RoleHandshakeController::class, 'accept'])->name('gov.membership.handshake.accept');
        Route::post('/handshake/{id}/reject', [RoleHandshakeController::class, 'reject'])->name('gov.membership.handshake.reject');
        Route::post('/handshake/{id}/cancel', [RoleHandshakeController::class, 'cancel'])->name('gov.membership.handshake.cancel');
    });

    // =========================================================================
    // 2. OFFICE STAFF (office administrators)
    // =========================================================================
    Route::group(['prefix' => 'gov-store/office/staff', 'middleware' => 'gov.can:membership.manage'], function () {
        Route::get('/', [MembershipAdminController::class, 'index'])->name('gov.membership.admin.index')
            ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(__('office_membership::member.nav_staff'), route('gov.membership.admin.index')));
        Route::post('/add-employee', [MembershipAdminController::class, 'addEmployeeByToken'])->name('gov.membership.admin.add-employee');
        Route::post('/generate-invite-code', [MembershipAdminController::class, 'generateInviteCode'])->name('gov.membership.admin.generate-invite-code');
        Route::post('/approve/{membershipId}', [MembershipAdminController::class, 'approveMembership'])->name('gov.membership.admin.approve');
        Route::post('/reject/{membershipId}', [MembershipAdminController::class, 'rejectMembership'])->name('gov.membership.admin.reject');
        Route::post('/release/{membershipId}', [MembershipAdminController::class, 'approveRelease'])->name('gov.membership.admin.release');
        Route::post('/end/{membershipId}', [MembershipAdminController::class, 'endAccess'])->name('gov.membership.admin.end');
        Route::post('/claim', [MembershipAdminController::class, 'claimEmployee'])->name('gov.membership.claim');
    });

    // =========================================================================
    // 3. EMERGENCY CHANGES (super users; every POST passes the national review)
    // =========================================================================
    Route::group(['prefix' => 'gov-store/admin/memberships', 'middleware' => 'gov.can:membership.override'], function () {
        Route::get('/override/console', [MembershipAdminController::class, 'overrideConsole'])->name('gov.membership.override.console')
            ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(__('office_membership::member.override_console_title'), route('gov.membership.override.console')));
        Route::post('/override/force', [MembershipAdminController::class, 'forceOverride'])->name('gov.membership.override');
    });
});
