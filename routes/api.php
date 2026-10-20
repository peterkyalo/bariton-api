<?php

use App\Http\Controllers\Api\v1\Admin\ActivityLogController;
use App\Http\Controllers\Api\v1\Admin\NotificationLogController;
use App\Http\Controllers\Api\v1\Admin\RoleController;
use App\Http\Controllers\Api\v1\Admin\SettingController;
use App\Http\Controllers\Api\v1\Admin\UserController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\ProfileController;
use App\Http\Controllers\Api\v1\PublicSettingController;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bariton API v1 Routes
|--------------------------------------------------------------------------
*/

// Public and Guest Endpoints
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:5,1');
    Route::post('token', [AuthController::class, 'token'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');

    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});

Route::post('guardian/confirm', [AuthController::class, 'confirmGuardian'])->middleware('throttle:10,1');
Route::get('settings/public', [PublicSettingController::class, 'index'])->middleware('throttle:60,1');

// Authenticated Endpoints (Sanctum cookie or token)
Route::middleware(['auth:sanctum', EnsureUserIsActive::class, EnsureTwoFactorEnabled::class])->group(function () {
    // Auth Session / Token
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::delete('auth/token', [AuthController::class, 'revokeToken']);
    Route::post('auth/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1');

    // Profile & Security
    Route::get('me', [ProfileController::class, 'me']);
    Route::put('me', [ProfileController::class, 'update']);
    Route::put('me/password', [ProfileController::class, 'updatePassword']);

    // Two-Factor Authentication
    Route::post('me/two-factor-authentication', [ProfileController::class, 'enableTwoFactor']);
    Route::get('me/two-factor-qr-code', [ProfileController::class, 'twoFactorQrCode']);
    Route::get('me/two-factor-secret-key', [ProfileController::class, 'twoFactorSecret']);
    Route::post('me/confirmed-two-factor-authentication', [ProfileController::class, 'confirmTwoFactor']);
    Route::get('me/two-factor-recovery-codes', [ProfileController::class, 'recoveryCodes']);
    Route::delete('me/two-factor-authentication', [ProfileController::class, 'disableTwoFactor']);

    // GDPR / Kenya Data Protection Act Portability & Deletion
    Route::get('me/data-exports', [ProfileController::class, 'dataExports']);
    Route::post('me/data-exports', [ProfileController::class, 'requestDataExport'])->middleware('throttle:3,1');
    Route::get('me/data-exports/{id}/download', [ProfileController::class, 'downloadDataExport']);
    Route::post('me/deletion-request', [ProfileController::class, 'requestDeletion']);
    Route::delete('me/deletion-request', [ProfileController::class, 'cancelDeletion']);

    // Admin Endpoints
    Route::prefix('admin')->group(function () {
        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::put('users/{user}/roles', [UserController::class, 'updateRoles']);
        Route::put('users/{user}/status', [UserController::class, 'updateStatus']);

        // Roles & Permissions
        Route::get('roles', [RoleController::class, 'index']);

        // Settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);

        // Audit Trail
        Route::get('activity-log', [ActivityLogController::class, 'index']);
        Route::get('notification-logs', [NotificationLogController::class, 'index']);
        Route::post('test-sms', [NotificationLogController::class, 'testSms']);
    });
});
