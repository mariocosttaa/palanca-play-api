<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::prefix('v1')->group(function () {
    // User authentication
    Route::prefix('users')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\V1\Auth\UserAuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\Api\V1\Auth\UserAuthController::class, 'login']);
    });

    // Business User authentication
    Route::prefix('business-users')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\V1\Auth\BusinessUserAuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\Api\V1\Auth\BusinessUserAuthController::class, 'login']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // User authenticated routes
        Route::prefix('users')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\Auth\UserAuthController::class, 'logout']);
            Route::get('/me', [App\Http\Controllers\Api\V1\Auth\UserAuthController::class, 'me']);
        });

        // Business User authenticated routes
        Route::prefix('business-users')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\Auth\BusinessUserAuthController::class, 'logout']);
            Route::get('/me', [App\Http\Controllers\Api\V1\Auth\BusinessUserAuthController::class, 'me']);
        });
    });
});

