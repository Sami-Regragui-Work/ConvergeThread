<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DuoController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupMemberController;
use App\Http\Controllers\GroupRoleOverrideController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MergeSessionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\TenantRoleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect('/groups') : redirect('/auth/login');
});

Route::middleware('guest')->prefix('auth')->name('auth.')->group(function () {
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.store');
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
});

Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::post('owner', [InvitationController::class, 'createAdminInvitation'])
        ->middleware('auth')
        ->name('owner.store');

    Route::post('tenant', [InvitationController::class, 'createMemberInvitation'])
        ->middleware(['auth', 'ban.check', 'identify.tenant'])
        ->name('tenant.store');

    Route::get('{token}', [InvitationController::class, 'show'])->name('show');
    Route::get('{token}/accept', [InvitationController::class, 'showAccept'])->name('accept');
    Route::post('{token}/accept', [InvitationController::class, 'accept'])->name('accept.store');
});

Route::middleware(['auth', 'ban.check', 'identify.tenant'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Groups
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('', [GroupController::class, 'index'])->name('index');
        Route::get('create', [GroupController::class, 'create'])->name('create');
        Route::post('', [GroupController::class, 'store'])->name('store');

        Route::middleware('group.member')->prefix('{group}')->group(function () {
            Route::get('', [GroupController::class, 'show'])->name('show');
            Route::get('edit', [GroupController::class, 'edit'])->name('edit');
            Route::patch('', [GroupController::class, 'update'])->name('update');
            Route::delete('', [GroupController::class, 'destroy'])->name('destroy');

            // Members
            Route::prefix('members')->name('members.')->group(function () {
                Route::get('', [GroupMemberController::class, 'index'])->name('index');
                Route::post('', [GroupMemberController::class, 'store'])->name('store');
                Route::patch('assign-role', [GroupMemberController::class, 'assignRole'])->name('assign-role');
                Route::delete('', [GroupMemberController::class, 'destroy'])->name('destroy');
            });

            // Duos
            Route::prefix('duos')->name('duos.')->group(function () {
                Route::get('', [DuoController::class, 'index'])->name('index');
                Route::post('', [DuoController::class, 'store'])->name('store');
                Route::delete('{duo}', [DuoController::class, 'destroy'])->name('destroy');
            });

            // Role overrides
            Route::prefix('role-overrides')->name('role-overrides.')->group(function () {
                Route::get('', [GroupRoleOverrideController::class, 'index'])->name('index');
                Route::post('', [GroupRoleOverrideController::class, 'store'])->name('store');
                Route::delete('{groupRoleOverride}', [GroupRoleOverrideController::class, 'destroy'])->name('destroy');
            });
        });
    });

    // Tenant roles
    Route::prefix('tenant-roles')->name('tenant-roles.')->group(function () {
        Route::get('', [TenantRoleController::class, 'index'])->name('index');
        Route::get('create', [TenantRoleController::class, 'create'])->name('create');
        Route::post('', [TenantRoleController::class, 'store'])->name('store');
        Route::delete('{tenantRole}', [TenantRoleController::class, 'destroy'])->name('destroy');
    });

    // Merge sessions
    Route::prefix('merge-sessions')->name('merge-sessions.')->group(function () {
        Route::get('', [MergeSessionController::class, 'index'])->name('index');
        Route::get('create', [MergeSessionController::class, 'create'])->name('create');
        Route::post('', [MergeSessionController::class, 'store'])->name('store');
        Route::get('{mergeSession}', [MergeSessionController::class, 'show'])->name('show');
        Route::delete('{mergeSession}', [MergeSessionController::class, 'destroy'])->name('destroy');
    });

    // Messages
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('{message}/thread', [MessageController::class, 'thread'])->name('thread');
        Route::get('{chatType}/{chatId}', [MessageController::class, 'index'])->name('index');
        Route::post('{chatType}/{chatId}', [MessageController::class, 'store'])->name('store');
        Route::patch('{message}', [MessageController::class, 'update'])->name('update');
        Route::delete('{message}', [MessageController::class, 'destroy'])->name('destroy');
    });
});
