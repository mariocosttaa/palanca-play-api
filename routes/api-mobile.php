<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes (Normal Users)
|--------------------------------------------------------------------------
|
| These routes are for mobile application access (iOS, Android, etc.)
| Used by regular users to access the booking API.
|
*/

Route::prefix('v1')->group(function () {
    // Public authentication routes
    Route::prefix('users')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'login']);
    });

    // Protected routes - requires authentication
    Route::middleware('auth:sanctum')->group(function () {
        // User authentication routes
        Route::prefix('users')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'logout']);
            Route::get('/me', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'me']);
        });

        // TODO: Add other mobile user routes here
        // Example:
        // Route::prefix('bookings')->group(function () {
        //     Route::get('/', [BookingController::class, 'index']);
        //     Route::post('/', [BookingController::class, 'store']);
        // });
    });
});

