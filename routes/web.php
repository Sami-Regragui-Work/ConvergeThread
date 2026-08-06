<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ChatBrowseController;
use App\Http\Controllers\ChatCryptoController;
use App\Http\Controllers\DuoController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupMemberController;
use App\Http\Controllers\GroupRoleOverrideController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MergeSessionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Owner\OwnerController;
use App\Http\Controllers\Owner\OwnerTenantController;
use App\Http\Controllers\Owner\OwnerUserController;
use App\Http\Controllers\TenantRoleController;
use App\Http\Controllers\RoleHierarchyController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\WorkspaceSyncController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('groups.index') : redirect()->route('auth.login');
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

    Route::middleware('guest')->prefix('{token}')->group(function () {
        Route::get('', [InvitationController::class, 'show'])->name('show');
        Route::get('accept', [InvitationController::class, 'showAccept'])->name('accept');
        Route::post('accept', [InvitationController::class, 'accept'])->name('accept.store');
    });
});

Route::middleware(['auth', 'is.owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('', [OwnerController::class, 'index'])->name('index');
    Route::post('users/{user}/ban', [OwnerUserController::class, 'ban'])->name('users.ban');
    Route::delete('users/{user}/ban', [OwnerUserController::class, 'unban'])->name('users.unban');
    Route::post('tenants/{tenant}/close', [OwnerTenantController::class, 'close'])->name('tenants.close');
    Route::delete('tenants/{tenant}/close', [OwnerTenantController::class, 'reopen'])->name('tenants.reopen');
});

Route::middleware(['auth', 'ban.check', 'identify.tenant'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('workspace/members', [WorkspaceMemberController::class, 'index'])->name('workspace.members.index');
    Route::patch('workspace/members/{member}/role', [WorkspaceMemberController::class, 'updateRole'])->name('workspace.members.role');
    Route::delete('workspace/invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.manage.revoke');

    Route::get('workspace/sync', [WorkspaceSyncController::class, 'poll'])->name('workspace.sync');

    Route::prefix('hierarchies')->name('hierarchies.')->group(function () {
        Route::get('', [RoleHierarchyController::class, 'index'])->name('index');
        Route::post('', [RoleHierarchyController::class, 'store'])->name('store');
        Route::post('{hierarchy}/levels', [RoleHierarchyController::class, 'addLevel'])->name('levels.store');
        Route::patch('levels/{level}/members', [RoleHierarchyController::class, 'syncLevelMembers'])->name('levels.members');
        Route::delete('levels/{level}', [RoleHierarchyController::class, 'destroyLevel'])->name('levels.destroy');
        Route::delete('{hierarchy}', [RoleHierarchyController::class, 'destroy'])->name('destroy');
    });

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Groups
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('', [GroupController::class, 'index'])->name('index');
        Route::get('create', [GroupController::class, 'create'])->name('create');
        Route::post('', [GroupController::class, 'store'])->name('store');

        Route::post('{group}/join', [GroupController::class, 'join'])->name('join');

        Route::prefix('{group}')->group(function () {
            Route::get('edit', [GroupController::class, 'edit'])->name('edit');
            Route::patch('', [GroupController::class, 'update'])->name('update');
            Route::delete('', [GroupController::class, 'destroy'])->name('destroy');

            Route::middleware('group.member')->group(function () {
                Route::get('', [GroupController::class, 'show'])->name('show');

                // Members
                Route::prefix('members')->name('members.')->group(function () {
                    Route::get('', [GroupMemberController::class, 'index'])->name('index');
                    Route::post('', [GroupMemberController::class, 'store'])->name('store');
                    Route::patch('assign-role', [GroupMemberController::class, 'assignRole'])->name('assign-role');
                    Route::patch('assign-tenant-role', [GroupMemberController::class, 'assignTenantRole'])->name('assign-tenant-role');
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
    });

    // Tenant roles
    Route::prefix('tenant-roles')->name('tenant-roles.')->group(function () {
        Route::get('', [TenantRoleController::class, 'index'])->name('index');
        Route::get('create', [TenantRoleController::class, 'create'])->name('create');
        Route::post('', [TenantRoleController::class, 'store'])->name('store');
        Route::get('{tenantRole}/edit', [TenantRoleController::class, 'edit'])->name('edit');
        Route::patch('{tenantRole}', [TenantRoleController::class, 'update'])->name('update');
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
        Route::get('chats', [ChatBrowseController::class, 'chats'])->name('chats');
        Route::get('{message}/locate', [ChatBrowseController::class, 'locate'])->name('locate');
        Route::post('{chatType}/{chatId}/call/signal', [CallController::class, 'signal'])->name('call.signal');
        Route::get('{chatType}/{chatId}/call/active', [CallController::class, 'active'])->name('call.active');
        Route::post('crypto/public-key', [ChatCryptoController::class, 'storePublicKey'])->name('crypto.public-key');
        Route::get('{chatType}/{chatId}/crypto', [ChatCryptoController::class, 'show'])->name('crypto.show');
        Route::post('{chatType}/{chatId}/crypto/shares', [ChatCryptoController::class, 'storeShares'])->name('crypto.shares');
        Route::post('{chatType}/{chatId}/crypto/request-key', [ChatCryptoController::class, 'requestKey'])->name('crypto.request-key');
        Route::get('{chatType}/{chatId}/search-feed', [ChatBrowseController::class, 'searchFeed'])->name('search-feed');
        Route::get('{chatType}/{chatId}/media', [ChatBrowseController::class, 'media'])->name('media');
        Route::get('{chatType}/{chatId}/participants', [ChatBrowseController::class, 'participants'])->name('participants');
        Route::get('{message}/attachment', [MessageController::class, 'attachment'])->name('attachment');
        Route::get('{message}/attachments/{attachment}', [MessageController::class, 'downloadAttachment'])->name('attachments.download');
        Route::get('{chatType}/{chatId}/mentions', [MessageController::class, 'mentions'])->name('mentions');
        Route::get('{chatType}/{chatId}/poll', [MessageController::class, 'poll'])->name('poll');
        Route::post('{message}/mentions/read', [MessageController::class, 'markMentionRead'])->name('mentions.read');
        Route::post('{message}/thread/mute', [MessageController::class, 'toggleThreadMute'])->name('thread.mute');
        Route::get('{message}/thread', [MessageController::class, 'thread'])->name('thread');
        Route::post('{chatType}/{chatId}/mute', [MessageController::class, 'toggleMute'])->name('mute');
        Route::get('{chatType}/{chatId}', [MessageController::class, 'index'])->name('index');
        Route::post('{chatType}/{chatId}', [MessageController::class, 'store'])->name('store');
        Route::patch('{message}', [MessageController::class, 'update'])->name('update');
        Route::delete('{message}', [MessageController::class, 'destroy'])->name('destroy');
    });
});
