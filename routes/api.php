<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvitationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::post('/invitations/owner', [InvitationController::class, 'createOwner']);

    Route::post('/invitations/tenant', [InvitationController::class, 'createTenant']);
});

Route::get('/invitations/{token}', [InvitationController::class, 'show']);
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);