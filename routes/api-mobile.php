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

    // Public tenant-scoped routes (no authentication required for browsing)
    Route::prefix('tenants/{tenant_id}')->group(function () {
        // Court Types
        Route::get('/court-types', [App\Http\Controllers\Api\V1\Mobile\MobileCourtTypeController::class, 'index']);
        Route::get('/court-types/{court_type_id}', [App\Http\Controllers\Api\V1\Mobile\MobileCourtTypeController::class, 'show']);

        // Courts
        Route::get('/courts', [App\Http\Controllers\Api\V1\Mobile\MobileCourtController::class, 'index']);
        Route::get('/courts/{court_id}', [App\Http\Controllers\Api\V1\Mobile\MobileCourtController::class, 'show']);

        // Court Availability
        Route::get('/courts/{court_id}/availability/dates', [App\Http\Controllers\Api\V1\Mobile\MobileCourtAvailabilityController::class, 'getDates']);
        Route::get('/courts/{court_id}/availability/{date}/slots', [App\Http\Controllers\Api\V1\Mobile\MobileCourtAvailabilityController::class, 'getSlots']);
    });

    // Protected routes - requires authentication
    Route::middleware('auth:sanctum')->group(function () {
        // User authentication routes
        Route::prefix('users')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'logout']);
            Route::get('/me', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'me']);
        });

        // Bookings (user-specific, not tenant-scoped in URL)
        Route::prefix('bookings')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'store']);
            Route::get('/stats', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'getStats']);
            Route::get('/recent', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'getRecentBookings']);
            Route::get('/next', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'getNextBooking']);
            Route::get('/{booking_id}', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'show']);
            Route::delete('/{booking_id}', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'destroy']);
        });
    });
});

