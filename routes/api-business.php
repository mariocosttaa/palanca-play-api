<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Business\TenantController;
use App\Http\Controllers\Api\V1\Business\CourtTypeController;
use App\Http\Controllers\Api\V1\Business\CourtController;
use App\Http\Controllers\Api\V1\Business\CourtImageController;
use App\Http\Controllers\Api\V1\Business\CourtAvailabilityController;
use App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController as AuthBusinessUserAuthController;


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
        Route::post('/register', [AuthBusinessUserAuthController::class, 'register']);
        Route::post('/login', [AuthBusinessUserAuthController::class, 'login']);
    });

    // Protected routes - requires authentication with business guard
    Route::middleware('auth:business')->group(function () {
        // Business User authentication routes (no tenant required)
        Route::prefix('business-users')->group(function () {
            Route::post('/logout', [AuthBusinessUserAuthController::class, 'logout']);
            Route::get('/me', [AuthBusinessUserAuthController::class, 'me']);
        });

        // Tenant routes
        Route::prefix('business')->group(function () {
            Route::get('/', [TenantController::class, 'index'])->name('tenant.index');
            Route::put('/{tenant_id}', [TenantController::class, 'update'])->name('tenant.update');
        });

        // Tenant-scoped routes - requires tenant access middleware
        Route::middleware(['tenant.show', \App\Http\Middleware\CheckTenantSubscription::class, \App\Http\Middleware\BlockSubscriptionCrud::class])->group(function () {
            Route::prefix('business/{tenant_id}')->group(function () {

                // Tenant details
                Route::get('/', [TenantController::class, 'show'])->name('tenant.show');

                // Court types routes
                Route::prefix('court-types')->group(function () {
                    Route::get('/', [CourtTypeController::class, 'index'])->name('court-types.index');
                    Route::post('/', [CourtTypeController::class, 'create'])->name('court-types.create');
                    Route::put('/{court_type_id}', [CourtTypeController::class, 'update'])->name('court-types.update');
                    Route::get('/{court_type_id}', [CourtTypeController::class, 'show'])->name('court-types.show');
                    Route::delete('/{court_type_id}', [CourtTypeController::class, 'destroy'])->name('court-types.destroy');
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
                        Route::delete('/{image_id}', [CourtImageController::class, 'destroy'])->name('courts.images.destroy');
                    });
                });

                // Court Availabilities routes
                Route::prefix('court-availabilities')->group(function () {
                    // New endpoints for checking availability
                    Route::get('/dates', [\App\Http\Controllers\Api\V1\Business\CourtAvailabilityController::class, 'getDates'])->name('court-availabilities.dates');
                    Route::get('/slots', [\App\Http\Controllers\Api\V1\Business\CourtAvailabilityController::class, 'getSlots'])->name('court-availabilities.slots');

                    // Existing CRUD routes (assuming they exist or were placeholders)
                    // Route::get('/', [CourtAvailabilityController::class, 'index'])->name('court-availabilities.index');
                    // Route::post('/', [CourtAvailabilityController::class, 'create'])->name('court-availabilities.create');
                    // Route::put('/{availability_id}', [CourtAvailabilityController::class, 'update'])->name('court-availabilities.update');
                    // Route::get('/{availability_id}', [CourtAvailabilityController::class, 'show'])->name('court-availabilities.show');
                    // Route::delete('/{availability_id}', [CourtAvailabilityController::class, 'destroy'])->name('court-availabilities.destroy');
                });

                // Subscription routes
                Route::prefix('subscriptions')->group(function () {
                    Route::get('/invoices', [\App\Http\Controllers\Api\V1\Business\SubscriptionController::class, 'indexInvoices'])->name('subscriptions.invoices');
                    Route::get('/current', [\App\Http\Controllers\Api\V1\Business\SubscriptionController::class, 'current'])->name('subscriptions.current');
                });

                // Booking routes
                Route::prefix('bookings')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\V1\Business\BookingController::class, 'index'])->name('bookings.index');
                    Route::post('/', [\App\Http\Controllers\Api\V1\Business\BookingController::class, 'store'])->name('bookings.store');
                    Route::get('/{booking_id}', [\App\Http\Controllers\Api\V1\Business\BookingController::class, 'show'])->name('bookings.show');
                    Route::put('/{booking_id}', [\App\Http\Controllers\Api\V1\Business\BookingController::class, 'update'])->name('bookings.update');
                    Route::put('/{booking_id}/presence', [\App\Http\Controllers\Api\V1\Business\BookingController::class, 'confirmPresence'])->name('bookings.confirm-presence');
                    Route::delete('/{booking_id}', [\App\Http\Controllers\Api\V1\Business\BookingController::class, 'destroy'])->name('bookings.destroy');
                });

                // TODO: Add other tenant-scoped routes here
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
    });
});

