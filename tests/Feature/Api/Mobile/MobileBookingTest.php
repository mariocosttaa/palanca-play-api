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
    $tenant = Tenant::factory()->create(['auto_confirm_bookings' => true, 'timezone' => 'UTC']);
    
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

test('user can get bookings with pagination', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    // Create 25 bookings
    Booking::factory()->count(25)->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
    ]);

    // Get bookings (should return 20 by default)
    $response = $this->getJson('/api/v1/bookings');

    $response->assertStatus(200);
    $response->assertJsonCount(20, 'data');
    $response->assertJsonStructure([
        'data',
        'links',
        'meta'
    ]);
});

test('bookings are ordered from future to old', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    // Create a past booking
    $pastBooking = Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => now()->subDays(1)->format('Y-m-d'),
        'start_time' => '10:00:00',
    ]);

    // Create a future booking
    $futureBooking = Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'start_time' => '10:00:00',
    ]);

    // Create a further future booking
    $furtherFutureBooking = Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => now()->addDays(2)->format('Y-m-d'),
        'start_time' => '10:00:00',
    ]);

    $response = $this->getJson('/api/v1/bookings');

    $response->assertStatus(200);
    $data = $response->json('data');

    // Check ordering: further future, future, past
    expect(EasyHashAction::decode($data[0]['id'], 'booking-id'))->toBe($furtherFutureBooking->id);
    expect(EasyHashAction::decode($data[1]['id'], 'booking-id'))->toBe($futureBooking->id);
    expect(EasyHashAction::decode($data[2]['id'], 'booking-id'))->toBe($pastBooking->id);
});

test('user can filter bookings by court', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court1 = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);
    $court2 = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    Booking::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'court_id' => $court1->id]);
    Booking::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'court_id' => $court2->id]);

    $response = $this->getJson('/api/v1/bookings?court_id=' . EasyHashAction::encode($court1->id, 'court-id'));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.court_id', EasyHashAction::encode($court1->id, 'court-id'));
});

test('user can filter bookings by tenant', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant1 = Tenant::factory()->create(['timezone' => 'UTC']);
    $tenant2 = Tenant::factory()->create(['timezone' => 'UTC']);
    
    $court1 = Court::factory()->create(['tenant_id' => $tenant1->id]);
    $court2 = Court::factory()->create(['tenant_id' => $tenant2->id]);

    Booking::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant1->id, 'court_id' => $court1->id]);
    Booking::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant2->id, 'court_id' => $court2->id]);

    $response = $this->getJson('/api/v1/bookings?tenant_id=' . EasyHashAction::encode($tenant1->id, 'tenant-id'));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    // BookingResource doesn't directly return tenant_id, but we can check the court's tenant if needed
});

test('user can filter bookings by modality', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $courtType1 = CourtType::factory()->create(['tenant_id' => $tenant->id, 'type' => 'padel']);
    $courtType2 = CourtType::factory()->create(['tenant_id' => $tenant->id, 'type' => 'tennis']);
    
    $court1 = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType1->id]);
    $court2 = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType2->id]);

    Booking::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'court_id' => $court1->id]);
    Booking::factory()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'court_id' => $court2->id]);

    $response = $this->getJson('/api/v1/bookings?modality=padel');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
});

test('user can get next upcoming booking with precise time', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    // Set a fixed time for the test
    $now = \Carbon\Carbon::parse('2026-01-03 12:00:00');
    \Carbon\Carbon::setTestNow($now);

    // Past booking today
    Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => '2026-01-03',
        'start_time' => '10:00:00',
    ]);

    // Next booking today
    $nextBooking = Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => '2026-01-03',
        'start_time' => '14:00:00',
    ]);

    // Future booking tomorrow
    Booking::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => '2026-01-04',
        'start_time' => '10:00:00',
    ]);

    $response = $this->getJson('/api/v1/bookings/next');

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', EasyHashAction::encode($nextBooking->id, 'booking-id'));

    // Clean up
    \Carbon\Carbon::setTestNow();
});

test('user can get next upcoming booking', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
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
        'timezone' => 'UTC',
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
        'timezone' => 'UTC',
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
