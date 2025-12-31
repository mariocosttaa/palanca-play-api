<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Client;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\Timezone;
use App\Models\User;
use Database\Seeders\Default\TimezoneSeeder;
use Laravel\Sanctum\Sanctum;

use App\Models\Invoice;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TimezoneSeeder::class);
});

function createValidSubscription($tenant) {
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addMonth(),
        'max_courts' => 10,
    ]);
}

test('booking created in NY timezone is saved in UTC and returned in NY time', function () {
    $nyTimezone = Timezone::where('name', 'America/New_York')->first();
    $user = BusinessUser::factory()->create(['timezone_id' => $nyTimezone->id]);
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant);
    createValidSubscription($tenant);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    Sanctum::actingAs($user, [], 'business');

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');

    // Input: Tomorrow 12:00 NY -> Tomorrow 17:00 UTC
    $tomorrow = now()->addDay()->format('Y-m-d');
    $payload = [
        'court_id' => $courtHashId,
        'client_id' => $clientHashId,
        'start_date' => $tomorrow,
        'start_time' => '12:00',
        'end_time' => '13:00',
        'price' => 1000,
    ];

    $response = $this->postJson(route('bookings.store', ['tenant_id' => $tenantHashId]), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.start_date', $tomorrow)
        ->assertJsonPath('data.start_time', '12:00')
        ->assertJsonPath('data.end_time', '13:00');

    // Verify DB is in UTC
    $booking = Booking::latest()->first();
    expect($booking->start_date->format('Y-m-d'))->toBe($tomorrow)
        ->and($booking->start_time->format('H:i'))->toBe('17:00')
        ->and($booking->end_time->format('H:i'))->toBe('18:00');
});

test('booking created in Tokyo timezone with date shift is saved in UTC and returned in Tokyo time', function () {
    $tokyoTimezone = Timezone::where('name', 'Asia/Tokyo')->first();
    $user = BusinessUser::factory()->create(['timezone_id' => $tokyoTimezone->id]);
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant);
    createValidSubscription($tenant);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    Sanctum::actingAs($user, [], 'business');

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');

    // Input: Day after tomorrow 01:00 Tokyo -> Tomorrow (UTC day before) 16:00 UTC
    $dayAfterTomorrow = now()->addDays(2)->format('Y-m-d');
    $tomorrowUtc = now()->addDay()->format('Y-m-d');
    $payload = [
        'court_id' => $courtHashId,
        'client_id' => $clientHashId,
        'start_date' => $dayAfterTomorrow,
        'start_time' => '01:00',
        'end_time' => '02:00',
        'price' => 1000,
    ];

    $response = $this->postJson(route('bookings.store', ['tenant_id' => $tenantHashId]), $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.start_date', $dayAfterTomorrow)
        ->assertJsonPath('data.start_time', '01:00')
        ->assertJsonPath('data.end_time', '02:00');

    // Verify DB is in UTC
    $booking = Booking::latest()->first();
    expect($booking->start_date->format('Y-m-d'))->toBe($tomorrowUtc)
        ->and($booking->start_time->format('H:i'))->toBe('16:00')
        ->and($booking->end_time->format('H:i'))->toBe('17:00');
});

test('booking update handles timezone conversion correctly', function () {
    $nyTimezone = Timezone::where('name', 'America/New_York')->first();
    $user = BusinessUser::factory()->create(['timezone_id' => $nyTimezone->id]);
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant);
    createValidSubscription($tenant);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();

    // Create initial booking in UTC: Tomorrow 17:00 (12:00 NY)
    $tomorrow = now()->addDay()->format('Y-m-d');
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'start_date' => $tomorrow,
        'end_date' => $tomorrow,
        'start_time' => '17:00:00',
        'end_time' => '18:00:00',
    ]);

    Sanctum::actingAs($user, [], 'business');

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $bookingHashId = EasyHashAction::encode($booking->id, 'booking-id');

    // Update start time to 13:00 NY (should be 18:00 UTC)
    $payload = [
        'start_time' => '13:00',
        'end_time' => '14:00',
    ];

    $response = $this->putJson(route('bookings.update', ['tenant_id' => $tenantHashId, 'booking_id' => $bookingHashId]), $payload);

    $response->assertStatus(200)
        ->assertJsonPath('data.start_time', '13:00')
        ->assertJsonPath('data.end_time', '14:00');

    // Verify DB
    $booking->refresh();
    expect($booking->start_time->format('H:i'))->toBe('18:00')
        ->and($booking->end_time->format('H:i'))->toBe('19:00');
});
