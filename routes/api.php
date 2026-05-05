<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DuoController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupMemberController;
use App\Http\Controllers\Api\GroupRoleOverrideController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MergeSessionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\TenantRoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

Route::prefix('invitations')->group(function () {
    Route::get('{token}', [InvitationController::class, 'show']);
    Route::post('{token}/accept', [InvitationController::class, 'accept']);

    Route::middleware('auth:api')->group(function () {
        Route::post('owner', [InvitationController::class, 'createAdminInvitation']);
    });

    Route::middleware(['auth:api', 'ban.check', 'identify.tenant'])->group(function () {
        Route::post('tenant', [InvitationController::class, 'createMemberInvitation']);
    });
});

Route::middleware(['auth:api', 'ban.check', 'identify.tenant'])->group(function () {
    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::post('/', [GroupController::class, 'store']);

        Route::middleware('group.member')->group(function () {
            Route::get('{group}', [GroupController::class, 'show']);
            Route::patch('{group}', [GroupController::class, 'update']);
            Route::delete('{group}', [GroupController::class, 'destroy']);

            Route::prefix('{group}/members')->group(function () {
                Route::get('/', [GroupMemberController::class, 'index']);
                Route::post('/', [GroupMemberController::class, 'store']);
                Route::patch('assign-role', [GroupMemberController::class, 'assignRole']);
                Route::delete('/', [GroupMemberController::class, 'destroy']);
            });

            Route::prefix('{group}/duos')->group(function () {
                Route::get('/', [DuoController::class, 'index']);
                Route::post('/', [DuoController::class, 'store']);
                Route::delete('/{duo}', [DuoController::class, 'destroy']);
            });

            Route::prefix('{group}/role-overrides')->group(function () {
                Route::get('/', [GroupRoleOverrideController::class, 'index']);
                Route::post('/', [GroupRoleOverrideController::class, 'store']);
                Route::delete('/{groupRoleOverride}', [GroupRoleOverrideController::class, 'destroy']);
            });
        });
    });

    Route::prefix('tenant-roles')->group(function () {
        Route::get('/', [TenantRoleController::class, 'index']);
        Route::post('/', [TenantRoleController::class, 'store']);
        Route::delete('{tenantRole}', [TenantRoleController::class, 'destroy']);
    });

    Route::prefix('merge-sessions')->group(function () {
        Route::get('/', [MergeSessionController::class, 'index']);
        Route::post('/', [MergeSessionController::class, 'store']);
        Route::get('{mergeSession}', [MergeSessionController::class, 'show']);
        Route::delete('{mergeSession}', [MergeSessionController::class, 'destroy']);
    });

    Route::prefix('messages')->group(function () {
        Route::get('{chatType}/{chatId}', [MessageController::class, 'index']);
        Route::post('{chatType}/{chatId}', [MessageController::class, 'store']);
        Route::patch('{message}', [MessageController::class, 'update']);
        Route::delete('{message}', [MessageController::class, 'destroy']);
        Route::get('{message}/thread', [MessageController::class, 'thread']);
    });
});
