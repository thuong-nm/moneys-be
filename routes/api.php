<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\TextShareController;
use App\Http\Controllers\VideoMergeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication API (public, no rate limiting for better UX)
Route::middleware(['web'])->prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// Text Share API (public with optional auth, rate limited, with session for password verification)
Route::middleware(['web', 'optional.auth', 'throttle:10,1'])->group(function () {
    Route::post('/text-share', [TextShareController::class, 'store']);
    Route::post('/text-share/{hashId}/verify', [TextShareController::class, 'verifyPassword']);
    Route::post('/text-share/history', [TextShareController::class, 'history']);
});

// Video Merge API (for n8n integration)
Route::prefix('video')->group(function () {
    Route::post('/merge', [VideoMergeController::class, 'merge']);
    Route::post('/cleanup', [VideoMergeController::class, 'cleanup']);
    Route::get('/status', [VideoMergeController::class, 'status']);
});
