<?php

namespace Tests\Feature\Api\Mobile;

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Timezone;
use App\Models\User;
use Database\Seeders\Default\TimezoneSeeder;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TimezoneSeeder::class);
});

function createValidSubscription($tenant) {
    // Create currency using factory
    \App\Models\Manager\CurrencyModel::factory()->create([
        'code' => $tenant->currency ?? 'eur'
    ]);
    
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addMonth(),
        'max_courts' => 10,
    ]);
}

function createCourtAvailability($court, $tenant) {
    // Create availability for all days of the week (09:00 - 21:00)
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    foreach ($daysOfWeek as $day) {
        \App\Models\CourtAvailability::factory()->create([
            'tenant_id' => $tenant->id,
            'court_id' => $court->id,
            'day_of_week_recurring' => $day,
            'start_time' => '09:00:00',
            'end_time' => '21:00:00',
            'is_available' => true,
        ]);
    }
}

test('mobile booking created in Tokyo timezone is saved in UTC and returned in Tokyo time', function () {
    $tokyoTimezone = Timezone::where('name', 'Asia/Tokyo')->first();
    $user = User::factory()->create(['timezone_id' => $tokyoTimezone->id]);
    
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Tokyo']);
    createValidSubscription($tenant);
    
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    createCourtAvailability($court, $tenant);

    Sanctum::actingAs($user, [], 'web');

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    // Input: Day after tomorrow 10:00 Tokyo -> Tomorrow (UTC day before) 01:00 UTC
    // Tokyo is UTC+9. 10:00 Tokyo = 01:00 UTC same day.
    // Let's pick a time that crosses day boundary if possible, or just standard shift.
    // 09:00 Tokyo = 00:00 UTC.
    // 10:00 Tokyo = 01:00 UTC.
    
    $dayAfterTomorrow = now()->addDays(2)->format('Y-m-d');
    
    $payload = [
        'court_id' => $courtHashId,
        'start_date' => $dayAfterTomorrow,
        'slots' => [
            ['start' => '10:00', 'end' => '11:00']
        ]
    ];

    $response = $this->postJson('/api/v1/bookings', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.start_date', $dayAfterTomorrow)
        ->assertJsonPath('data.start_time', '10:00')
        ->assertJsonPath('data.end_time', '11:00');

    // Verify DB is in UTC
    $booking = Booking::latest()->first();
    
    // 10:00 Tokyo = 01:00 UTC
    expect($booking->start_date->format('Y-m-d'))->toBe($dayAfterTomorrow)
        ->and($booking->start_time->format('H:i'))->toBe('01:00')
        ->and($booking->end_time->format('H:i'))->toBe('02:00');
});

test('mobile booking update handles timezone conversion correctly', function () {
    $tokyoTimezone = Timezone::where('name', 'Asia/Tokyo')->first();
    $user = User::factory()->create(['timezone_id' => $tokyoTimezone->id]);
    
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Tokyo']);
    createValidSubscription($tenant);
    
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    createCourtAvailability($court, $tenant);

    Sanctum::actingAs($user, [], 'web');

    // Create initial booking in UTC: Tomorrow 17:00 UTC
    // 17:00 UTC = 02:00 Tokyo (Next Day)
    // Let's use a time that is easy.
    // 01:00 UTC = 10:00 Tokyo.
    
    $tomorrow = now()->addDay()->format('Y-m-d');
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => $tomorrow,
        'end_date' => $tomorrow,
        'start_time' => '01:00:00', // 10:00 Tokyo
        'end_time' => '02:00:00',   // 11:00 Tokyo
    ]);

    $bookingHashId = EasyHashAction::encode($booking->id, 'booking-id');

    // Update to 11:00 Tokyo -> 02:00 UTC
    // Same day in Tokyo (and UTC)
    
    $payload = [
        'start_date' => $tomorrow, // This date might be tricky if day shifts, but here 10:00 Tokyo is same date as 01:00 UTC
        'slots' => [
            ['start' => '11:00', 'end' => '12:00']
        ]
    ];

    $response = $this->putJson("/api/v1/bookings/{$bookingHashId}", $payload);

    $response->assertStatus(200)
        ->assertJsonPath('data.start_time', '11:00')
        ->assertJsonPath('data.end_time', '12:00');

    // Verify DB is in UTC
    $booking->refresh();
    expect($booking->start_time->format('H:i'))->toBe('02:00')
        ->and($booking->end_time->format('H:i'))->toBe('03:00');
});
