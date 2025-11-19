<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business API Routes (Web/Managers)
|--------------------------------------------------------------------------
|
| These routes are for web application access (management dashboard)
| Used by business users/managers to manage courts, bookings, etc.
|
*/

Route::prefix('v1')->group(function () {
    // Public authentication routes
    Route::prefix('business-users')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController::class, 'login']);
    });

    // Protected routes - requires authentication with business guard
    Route::middleware('auth:business')->group(function () {
        // Business User authentication routes
        Route::prefix('business-users')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController::class, 'logout']);
            Route::get('/me', [App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController::class, 'me']);
        });

        // TODO: Add other business user routes here
        // Example:
        // Route::prefix('courts')->group(function () {
        //     Route::get('/', [CourtController::class, 'index']);
        //     Route::post('/', [CourtController::class, 'store']);
        // });
        // Route::prefix('bookings')->group(function () {
        //     Route::get('/', [BookingController::class, 'index']);
        //     Route::post('/{booking}/confirm', [BookingController::class, 'confirm']);
        // });
    });
});

