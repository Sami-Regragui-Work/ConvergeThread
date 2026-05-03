<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupMemberController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\MergeSessionController;
use App\Http\Controllers\Api\TenantRoleController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::prefix('invitations')->group(function () {
        Route::post('/owner', [InvitationController::class, 'createAdminInvitation']);
        Route::post('/tenant', [InvitationController::class, 'createMemberInvitation']);
    });

    Route::prefix('groups')->group(function () {
        Route::post('/', [GroupController::class, 'store']);
        Route::get('/', [GroupController::class, 'index']);
        Route::get('/{group}', [GroupController::class, 'show']);
        Route::patch('/{group}', [GroupController::class, 'update']);
        Route::delete('/{group}', [GroupController::class, 'destroy']);
    });

    Route::prefix('groups/{group}/members')->group(function () {
        Route::get('/', [GroupMemberController::class, 'index']);
        Route::post('/', [GroupMemberController::class, 'store']);
        Route::patch('/{user}/role', [GroupMemberController::class, 'assignRole']);
        Route::delete('/{user}', [GroupMemberController::class, 'destroy']);
    });

    Route::prefix('tenant-roles')->group(function () {
        Route::get('/', [TenantRoleController::class, 'index']);
        Route::post('/', [TenantRoleController::class, 'store']);
        Route::delete('/{tenantRole}', [TenantRoleController::class, 'destroy']);
    });
    Route::prefix('merge-sessions')->group(function () {
        Route::get('/', [MergeSessionController::class, 'index']);
        Route::post('/', [MergeSessionController::class, 'store']);
        Route::get('/{mergeSession}', [MergeSessionController::class, 'show']);
        Route::delete('/{mergeSession}', [MergeSessionController::class, 'destroy']);
    });
});

Route::prefix('invitations')->group(function () {
    Route::get('/{token}', [InvitationController::class, 'show']);
    Route::post('/{token}/accept', [InvitationController::class, 'accept']);
});
