<?php

use App\Actions\General\EasyHashAction;
use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, [], 'sanctum');

    $this->tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $this->courtType = CourtType::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->court = Court::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_type_id' => $this->courtType->id,
    ]);
});

test('user can filter bookings by upcoming status', function () {
    // Upcoming booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // Past booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $response = $this->getJson('/api/v1/bookings?status=upcoming');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('user can filter bookings by past status', function () {
    // Upcoming booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // Past booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $response = $this->getJson('/api/v1/bookings?status=past');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('user can filter bookings by completed status (same as past)', function () {
    // Past booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $response = $this->getJson('/api/v1/bookings?status=completed');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('user can filter bookings by cancelled status', function () {
    // Cancelled booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => BookingStatusEnum::CANCELLED,
    ]);

    // Confirmed booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $response = $this->getJson('/api/v1/bookings?status=cancelled');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('user can filter bookings by confirmed status', function () {
    // Confirmed booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // Pending booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => BookingStatusEnum::PENDING,
    ]);

    $response = $this->getJson('/api/v1/bookings?status=confirmed');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('user can filter bookings by pending status', function () {
    // Pending booking
    Booking::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => BookingStatusEnum::PENDING,
    ]);

    $response = $this->getJson('/api/v1/bookings?status=pending');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('user gets 422 for invalid status', function () {
    $response = $this->getJson('/api/v1/bookings?status=invalid_status');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
});
