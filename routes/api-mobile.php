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
    // Version endpoint
    Route::get('/version', function () {
        return response()->json([
            'version' => '1.0.0',
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'api' => 'mobile'
        ]);
    });

    // Public authentication routes
    Route::prefix('users')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'register']);
        Route::post('/login', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'login']);
        Route::post('/auth/google', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'googleLogin']);
        Route::post('/auth/google/link', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'linkGoogle'])->middleware('auth:sanctum');
        Route::post('/auth/google/unlink', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'unlinkGoogle'])->middleware('auth:sanctum');
    });

    // Public general routes
    Route::get('/countries', [App\Http\Controllers\Api\V1\Mobile\MobileCountryController::class, 'index']);
    Route::get('/currencies', [App\Http\Controllers\Api\V1\Mobile\MobileCurrencyController::class, 'index']);
    Route::get('/timezones', [App\Http\Controllers\Api\V1\Mobile\TimezoneController::class, 'index']);

    // Public tenant-scoped routes (no authentication required for browsing)
    Route::prefix('tenants/{tenant_id}')->group(function () {
        // Court Types
        Route::get('/court-types/modalities', [App\Http\Controllers\Api\V1\Mobile\MobileCourtTypeController::class, 'types']);
        Route::get('/court-types', [App\Http\Controllers\Api\V1\Mobile\MobileCourtTypeController::class, 'index']);
        Route::get('/court-types/{court_type_id}', [App\Http\Controllers\Api\V1\Mobile\MobileCourtTypeController::class, 'show']);

        // Courts
        Route::get('/courts', [App\Http\Controllers\Api\V1\Mobile\MobileCourtController::class, 'index']);
        Route::get('/courts/{court_id}', [App\Http\Controllers\Api\V1\Mobile\MobileCourtController::class, 'show']);

        // Court Availability
        Route::get('/courts/{court_id}/availability/dates', [App\Http\Controllers\Api\V1\Mobile\MobileCourtAvailabilityController::class, 'getDates']);
        Route::get('/courts/{court_id}/availability/{date}/slots', [App\Http\Controllers\Api\V1\Mobile\MobileCourtAvailabilityController::class, 'getSlots']);
    });

    // Password Reset Routes (public)
    Route::prefix('password')->group(function () {
        Route::post('/forgot', [App\Http\Controllers\Api\V1\Mobile\PasswordResetController::class, 'requestCode']);
        Route::post('/verify', [App\Http\Controllers\Api\V1\Mobile\PasswordResetController::class, 'verifyCode']);
        Route::get('/verify/{code}', [App\Http\Controllers\Api\V1\Mobile\PasswordResetController::class, 'checkCode']);
    });

    // Protected routes - requires authentication
    Route::middleware(['auth:sanctum', 'timezone'])->group(function () {
        // User authentication routes (NO verification required to access these)
        Route::prefix('users')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'logout']);
            Route::get('/me', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'me']);
            
            // Verification Routes (accessible without email verification)
            Route::prefix('verification')->group(function () {
                Route::post('/verify', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'verifyEmail']);
                Route::post('/resend', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'resendVerificationCode']);
                Route::get('/status', [App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController::class, 'checkVerificationStatus']);
            });
        });

        // User Profile routes (NO email verification required for basic profile settings)
        Route::prefix('profile')->group(function () {
            Route::patch('/language', [App\Http\Controllers\Api\V1\Mobile\UserProfileController::class, 'updateLanguage']);
            Route::put('/timezone', [App\Http\Controllers\Api\V1\Mobile\UserProfileController::class, 'updateTimezone']);
            Route::put('/', [App\Http\Controllers\Api\V1\Mobile\UserProfileController::class, 'updateProfile']);
        });

        // Routes requiring Email Verification - ONLY booking operations
        Route::middleware('verified.api')->group(function () {
            // Notifications (requires email verification)
            Route::prefix('notifications')->group(function () {
                Route::get('/recent', [App\Http\Controllers\Api\V1\Mobile\NotificationController::class, 'recent']);
                Route::get('/', [App\Http\Controllers\Api\V1\Mobile\NotificationController::class, 'index']);
                Route::patch('/{notification_id}/read', [App\Http\Controllers\Api\V1\Mobile\NotificationController::class, 'markAsRead']);
            });
            // Bookings (user-specific, not tenant-scoped in URL)
            Route::prefix('bookings')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'store']);
                Route::get('/stats', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'getStats']);
                Route::get('/recent', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'getRecentBookings']);
                Route::get('/next', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'getNextBooking']);
                Route::get('/{booking_id}', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'show']);
                Route::put('/{booking_id}', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'update']);
                Route::delete('/{booking_id}', [App\Http\Controllers\Api\V1\Mobile\MobileBookingController::class, 'destroy']);
            });
        });
    });
});

