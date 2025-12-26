<?php

use App\Actions\General\EasyHashAction;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Manager\CurrencyModel;
use App\Models\Country;
use App\Models\CourtAvailability;
use App\Enums\BookingStatusEnum;
use App\Models\Booking;

uses(RefreshDatabase::class);

test('user can get booking statistics', function () {
    // Create user and authenticate
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    // Create a tenant with auto-confirm enabled
    $tenant = Tenant::factory()->create(['auto_confirm_bookings' => true]);
    
    // Create court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    // Create bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->addDays(2)->format('Y-m-d'),
        'status' => BookingStatusEnum::CANCELLED,
    ]);

    // Get stats
    $response = $this->getJson('/api/v1/bookings/stats');

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'total_bookings' => 3, // Changed from 10 to 3 due to new booking creation logic
            'upcoming_bookings' => 1, // Changed from 5 to 1
            'past_bookings' => 1, // Changed from 3 to 1
            'cancelled_bookings' => 1, // Changed from 2 to 1
        ]
    ]);
});

test('user can get recent bookings with pagination', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create();
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    // Create 15 bookings
    Booking::factory()->count(15)->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
    ]);

    // Get recent bookings with pagination
    $response = $this->getJson('/api/v1/bookings/recent?per_page=5');

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
});

test('user can get next upcoming booking', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create();
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    // Create past booking
    Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
    ]);

    // Create next booking
    $nextBooking = Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'start_time' => '10:00:00',
    ]);

    // Create future booking
    Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => now()->addDays(5)->format('Y-m-d'),
    ]);

    $response = $this->getJson('/api/v1/bookings/next');

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', EasyHashAction::encode($nextBooking->id, 'booking-id'));
});

test('booking is auto-confirmed when tenant has auto_confirm_bookings enabled', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    // Create tenant with auto-confirm enabled
    $currency = CurrencyModel::factory()->create(['code' => 'usd']);
    $tenant = Tenant::factory()->create([
        'auto_confirm_bookings' => true,
        'booking_interval_minutes' => 60,
        'currency' => 'usd',
    ]);
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => 5000,
        'interval_time_minutes' => 60,
    ]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    // Create availability
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDays(1)->format('Y-m-d'),
        'start_time' => '08:00:00',
        'end_time' => '22:00:00',
        'is_available' => true,
    ]);

    $response = $this->postJson('/api/v1/bookings', [
        'court_id' => EasyHashAction::encode($court->id, 'court-id'),
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'slots' => [
            ['start' => '10:00', 'end' => '11:00'],
        ],
    ]);


    $response->assertStatus(201);
    $response->assertJsonPath('data.status', BookingStatusEnum::CONFIRMED->value); // Should be auto-confirmed
});

test('booking is pending when tenant has auto_confirm_bookings disabled', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    // Create tenant with auto-confirm disabled
    $currency = CurrencyModel::factory()->create(['code' => 'usd']);
    $tenant = Tenant::factory()->create([
        'auto_confirm_bookings' => false,
        'booking_interval_minutes' => 60,
        'currency' => 'usd',
    ]);
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => 5000,
        'interval_time_minutes' => 60,
    ]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    // Create availability
    \App\Models\CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDays(1)->format('Y-m-d'),
        'start_time' => '08:00:00',
        'end_time' => '22:00:00',
        'is_available' => true,
    ]);

    $response = $this->postJson('/api/v1/bookings', [
        'court_id' => EasyHashAction::encode($court->id, 'court-id'),
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'slots' => [
            ['start' => '10:00', 'end' => '11:00'],
        ],
    ]);


    $response->assertStatus(201);
    $response->assertJsonPath('data.status', \App\Enums\BookingStatusEnum::PENDING->value); // Should be pending
});
