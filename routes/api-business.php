<?php

use App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController as AuthBusinessUserAuthController;
use App\Http\Controllers\Api\V1\Business\BookingController;
use App\Http\Controllers\Api\V1\Business\BookingVerificationController;
use App\Http\Controllers\Api\V1\Business\ClientController;
use App\Http\Controllers\Api\V1\Business\CourtAvailabilityController;
use App\Http\Controllers\Api\V1\Business\CourtController;
use App\Http\Controllers\Api\V1\Business\CourtImageController;
use App\Http\Controllers\Api\V1\Business\CourtTypeController;
use App\Http\Controllers\Api\V1\Business\FinancialController;
use App\Http\Controllers\Api\V1\Business\NotificationController;
use App\Http\Controllers\Api\V1\Business\SubscriptionController;
use App\Http\Controllers\Api\V1\Business\TenantController;
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
    // Version endpoint
    Route::get('/version', function () {
        return response()->json([
            'version' => '1.0.0',
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'api' => 'business'
        ]);
    });

    // Public authentication routes
    Route::prefix('business-users')->group(function () {
        Route::post('/register', [AuthBusinessUserAuthController::class, 'register']);
        Route::post('/login', [AuthBusinessUserAuthController::class, 'login']);
        Route::post('/auth/google', [AuthBusinessUserAuthController::class, 'googleLogin']);
        Route::post('/auth/google/link', [AuthBusinessUserAuthController::class, 'linkGoogle'])->middleware('auth:business');
        Route::post('/auth/google/unlink', [AuthBusinessUserAuthController::class, 'unlinkGoogle'])->middleware('auth:business');

        // Password Reset Routes (public)
        Route::prefix('password')->group(function () {
            Route::post('/forgot', [App\Http\Controllers\Api\V1\Business\Auth\BusinessPasswordResetController::class, 'requestCode']);
            Route::post('/verify', [App\Http\Controllers\Api\V1\Business\Auth\BusinessPasswordResetController::class, 'verifyCode']);
            Route::get('/verify/{code}', [App\Http\Controllers\Api\V1\Business\Auth\BusinessPasswordResetController::class, 'checkCode']);
        });
    });

    // Protected routes - requires authentication with business guard
    Route::middleware(['auth:business', 'timezone'])->group(function () {
        // Business User authentication routes (no tenant required, NO email verification required)
        Route::prefix('business-users')->group(function () {
            Route::post('/logout', [AuthBusinessUserAuthController::class, 'logout']);
            Route::get('/me', [AuthBusinessUserAuthController::class, 'me']);

            // Verification Routes (accessible without email verification)
            Route::prefix('verification')->group(function () {
                Route::post('/verify', [AuthBusinessUserAuthController::class, 'verifyEmail']);
                Route::post('/resend', [AuthBusinessUserAuthController::class, 'resendVerificationCode']);
                Route::get('/status', [AuthBusinessUserAuthController::class, 'checkVerificationStatus']);
            });
        });

        // Notifications (user-specific, not tenant-scoped, NO email verification required)
        Route::prefix('notifications')->group(function () {
            Route::get('/recent', [NotificationController::class, 'recent'])->name('business.notifications.recent');
            Route::get('/', [NotificationController::class, 'index'])->name('business.notifications.index');
            Route::patch('/{notification_id}/read', [NotificationController::class, 'markAsRead'])->name('business.notifications.read');
        });

        // Business User Profile routes (NO email verification required for basic profile settings)
        Route::prefix('profile')->group(function () {
            Route::patch('/language', [App\Http\Controllers\Api\V1\Business\BusinessUserProfileController::class, 'updateLanguage']);
            Route::put('/timezone', [App\Http\Controllers\Api\V1\Business\BusinessUserProfileController::class, 'updateTimezone']);
            Route::put('/', [App\Http\Controllers\Api\V1\Business\BusinessUserProfileController::class, 'updateProfile']);
        });

        // Routes requiring Email Verification - ONLY business operations
        Route::middleware('verified.api')->group(function () {
            // Tenant routes
            Route::prefix('tenants')->group(function () {
                Route::get('/', [TenantController::class, 'index'])->name('tenant.index');
                Route::put('/{tenant_id}', [TenantController::class, 'update'])->name('tenant.update');

            });

            // Countries and Currencies (no auth required on business side since these are reference data)
            Route::get('/countries', [App\Http\Controllers\Api\V1\Business\CountryController::class, 'index'])->name('countries.index');
            Route::get('/currencies', [App\Http\Controllers\Api\V1\Business\CurrencyController::class, 'index'])->name('currencies.index');
            Route::get('/timezones', [App\Http\Controllers\Api\V1\Business\TimezoneController::class, 'index'])->name('timezones.index');

            // Tenant-scoped routes - requires tenant access middleware
            Route::middleware(['tenant.show', \App\Http\Middleware\CheckTenantSubscription::class, \App\Http\Middleware\BlockSubscriptionCrud::class])->group(function () {
                Route::prefix('business/{tenant_id}')->group(function () {

                    // Tenant details
                    Route::get('/', [TenantController::class, 'show'])->name('tenant.show');

                    // Dashboard
                    Route::get('/dashboard', [App\Http\Controllers\Api\V1\Business\DashboardController::class, 'index'])->name('dashboard.index');

                    // Tenant Logo
                    Route::post('/logo', [TenantController::class, 'uploadLogo'])->name('tenant.logo.upload');
                    Route::delete('/logo', [TenantController::class, 'deleteLogo'])->name('tenant.logo.delete');

                    // Court types routes
                    Route::prefix('court-types')->group(function () {
                        // Court Types
                        Route::get('/modalities', [CourtTypeController::class, 'types'])->name('court-types.modalities');
                        Route::get('/', [CourtTypeController::class, 'index'])->name('court-types.index');
                        Route::post('/', [CourtTypeController::class, 'create'])->name('court-types.create');
                        Route::put('/{court_type_id}', [CourtTypeController::class, 'update'])->name('court-types.update');
                        Route::get('/{court_type_id}', [CourtTypeController::class, 'show'])->name('court-types.show');
                        Route::delete('/{court_type_id}', [CourtTypeController::class, 'destroy'])->name('court-types.destroy');

                        // Court Type Availabilities routes
                        Route::prefix('{court_type_id}/availabilities')->group(function () {
                            Route::get('/', [App\Http\Controllers\Api\V1\Business\CourtTypeAvailabilityController::class, 'index'])->name('court-types.availabilities.index');
                            Route::post('/', [App\Http\Controllers\Api\V1\Business\CourtTypeAvailabilityController::class, 'store'])->name('court-types.availabilities.store');
                            Route::put('/{availability_id}', [App\Http\Controllers\Api\V1\Business\CourtTypeAvailabilityController::class, 'update'])->name('court-types.availabilities.update');
                            Route::delete('/{availability_id}', [App\Http\Controllers\Api\V1\Business\CourtTypeAvailabilityController::class, 'destroy'])->name('court-types.availabilities.destroy');
                        });
                    });

                    // Courts routes
                    Route::prefix('courts')->group(function () {
                        Route::get('/', [CourtController::class, 'index'])->name('courts.index');
                        Route::post('/', [CourtController::class, 'create'])->name('courts.create');
                        Route::put('/{court_id}', [CourtController::class, 'update'])->name('courts.update');
                        Route::get('/{court_id}', [CourtController::class, 'show'])->name('courts.show');
                        Route::get('/{court_id}', [CourtController::class, 'show'])->name('courts.show');
                        Route::delete('/{court_id}', [CourtController::class, 'destroy'])->name('courts.destroy');

                        // Court Images routes
                        Route::prefix('{court_id}/images')->group(function () {
                            Route::post('/', [CourtImageController::class, 'store'])->name('courts.images.store');
                            Route::patch('/{image_id}/primary', [CourtImageController::class, 'setPrimary'])->name('courts.images.set-primary');
                            Route::delete('/{image_id}', [CourtImageController::class, 'destroy'])->name('courts.images.destroy');
                        });
                    });

                    // Court Availabilities routes
                    // Court Availabilities routes
                    Route::prefix('courts/{court_id}')->group(function () {
                        // Availability CRUD
                        Route::prefix('availabilities')->group(function () {
                            Route::get('/', [CourtAvailabilityController::class, 'index'])->name('courts.availabilities.index');
                            Route::post('/', [CourtAvailabilityController::class, 'store'])->name('courts.availabilities.store');
                            Route::put('/{availability_id}', [CourtAvailabilityController::class, 'update'])->name('courts.availabilities.update');
                            Route::delete('/{availability_id}', [CourtAvailabilityController::class, 'destroy'])->name('courts.availabilities.destroy');
                        });

                        // Availability Checks
                        Route::prefix('availability')->group(function () {
                            Route::get('/dates', [CourtAvailabilityController::class, 'getDates'])->name('courts.availability.dates');
                            Route::get('/{date}/slots', [CourtAvailabilityController::class, 'getSlots'])->name('courts.availability.slots');
                        });
                    });

                    // Subscription routes
                    Route::prefix('subscriptions')->group(function () {
                        Route::get('/invoices', [SubscriptionController::class, 'indexInvoices'])->name('subscriptions.invoices');
                        Route::get('/current', [SubscriptionController::class, 'current'])->name('subscriptions.current');
                    });

                    // Booking routes
                    Route::prefix('bookings')->group(function () {
                        Route::get('/', [BookingController::class, 'index'])->name('bookings.index');
                        Route::get('/presence', [BookingController::class, 'presence'])->name('bookings.presence');
                        Route::post('/', [BookingController::class, 'store'])->name('bookings.store');
                        Route::get('/history', [App\Http\Controllers\Api\V1\Business\BookingHistoryController::class, 'index'])->name('bookings.history');
                        Route::get('/stats', [App\Http\Controllers\Api\V1\Business\BookingStatsController::class, 'index'])->name('bookings.stats');
                        Route::get('/{booking_id}', [BookingController::class, 'show'])->name('bookings.show');
                        Route::put('/{booking_id}', [BookingController::class, 'update'])->name('bookings.update');
                        Route::put('/{booking_id}/presence', [BookingController::class, 'confirmPresence'])->name('bookings.confirm-presence');
                        Route::delete('/{booking_id}', [BookingController::class, 'destroy'])->name('bookings.destroy');

                        // QR Code Verification - Upload QR image to verify booking
                        Route::post('/verify-qr', [BookingVerificationController::class, 'verify'])->name('bookings.verify-qr');
                    });

                    // Client routes
                    Route::prefix('clients')->group(function () {
                        Route::get('/', [ClientController::class, 'index'])->name('clients.index');
                        Route::post('/', [ClientController::class, 'store'])->name('clients.store');
                        Route::get('/{client_id}', [ClientController::class, 'show'])->name('clients.show');
                        Route::put('/{client_id}', [ClientController::class, 'update'])->name('clients.update');
                        Route::get('/{client_id}/stats', [ClientController::class, 'stats'])->name('clients.stats');
                        Route::get('/{client_id}/bookings', [ClientController::class, 'bookings'])->name('clients.bookings');
                    });

                    // Financial routes
                    Route::prefix('financials')->group(function () {
                        Route::get('/current', [FinancialController::class, 'currentMonth'])->name('financials.current');
                        Route::get('/{year}/{month}/stats', [FinancialController::class, 'monthlyStats'])
                            ->where(['year' => '[0-9]+', 'month' => '[0-9]+'])
                            ->name('financials.monthly-stats');
                        Route::get('/{year}/{month}', [FinancialController::class, 'monthlyReport'])
                            ->where(['year' => '[0-9]+', 'month' => '[0-9]+'])
                            ->name('financials.monthly-report');
                        Route::get('/{year}/stats', [FinancialController::class, 'yearlyStats'])
                            ->where(['year' => '[0-9]+'])
                            ->name('financials.yearly-stats');
                    });

                });
            });
        });
    });
});
