<?php

use App\Models\BusinessUser;
use App\Models\Timezone;
use App\Models\User;
use App\Services\TimezoneService;
use Database\Seeders\Default\TimezoneSeeder;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('timezone service converts to utc correctly', function () {
    $service = new TimezoneService();
    $service->setContextTimezone('America/New_York'); // UTC-5 (Standard) or UTC-4 (DST)

    // 2025-01-01 12:00:00 in NY is 17:00:00 UTC
    $utc = $service->toUTC('2025-01-01 12:00:00');
    
    expect($utc->format('Y-m-d H:i:s'))->toBe('2025-01-01 17:00:00')
        ->and($utc->timezoneName)->toBe('UTC');
});

test('timezone service converts to user time correctly', function () {
    $service = new TimezoneService();
    $service->setContextTimezone('America/New_York');

    // 2025-01-01 17:00:00 UTC is 12:00:00 NY
    $userTime = $service->toUserTime(\Carbon\Carbon::parse('2025-01-01 17:00:00', 'UTC'));

    // ISO8601 string includes offset
    expect($userTime)->toContain('2025-01-01T12:00:00-05:00');
});

test('middleware sets context timezone for business user', function () {
    $this->seed(TimezoneSeeder::class);
    
    $timezone = Timezone::where('name', 'Europe/London')->first();
    $user = BusinessUser::factory()->create(['timezone_id' => $timezone->id]);

    Route::middleware(['auth:business', 'timezone'])->get('/test-timezone-context', function () {
        return response()->json([
            'timezone' => app(TimezoneService::class)->getContextTimezone()
        ]);
    });

    $user->load('timezone');
    $this->actingAs($user, 'business');

    $this->getJson('/test-timezone-context')
        ->assertOk()
        ->assertJson(['timezone' => 'Europe/London']);
});

test('middleware sets context timezone for mobile user', function () {
    $this->seed(TimezoneSeeder::class);
    
    $timezone = Timezone::where('name', 'Asia/Tokyo')->first();
    $user = User::factory()->create(['timezone_id' => $timezone->id]);

    Route::middleware(['auth:sanctum', 'timezone'])->get('/test-timezone-context-mobile', function () {
        return response()->json([
            'timezone' => app(TimezoneService::class)->getContextTimezone()
        ]);
    });

    $user->load('timezone');
    Sanctum::actingAs($user, [], 'web'); // Sanctum usually uses 'web' or 'sanctum' guard depending on config, but here we use actingAs with the user model. 
    // Wait, for mobile users the guard is usually 'sanctum'.
    // Let's check the route definition. It uses 'auth:sanctum'.
    
    Sanctum::actingAs($user, [], 'web'); // Default guard for User model usually

    $this->getJson('/test-timezone-context-mobile')
        ->assertOk()
        ->assertJson(['timezone' => 'Asia/Tokyo']);
});
