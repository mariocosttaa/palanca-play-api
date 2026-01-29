<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\Invoice;
use App\Models\Manager\CurrencyModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('mobile user can update booking slots and price is recalculated without sending court_id', function () {
    // Setup
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    
    // Valid invoice for tenant
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $pricePerInterval = 1000;
    $courtType = \App\Models\CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => $pricePerInterval,
        'interval_time_minutes' => 60
    ]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id
    ]);

    // Availability
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    $user = User::factory()->create();

    // Create initial booking (1 hour, price 1000)
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->addDay()->format('Y-m-d'),
        'end_date' => now()->addDay()->format('Y-m-d'), // Ensure single day booking
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 1000,
        'status' => 'confirmed'
    ]);

    Sanctum::actingAs($user, [], 'web');

    // Update with 2 slots (expanding time)
    $slots = [
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '11:00', 'end' => '12:00']
    ];

    // URL for Mobile Booking Update: /api/v1/bookings/{id}
    $url = '/api/v1/bookings/' . EasyHashAction::encode($booking->id, 'booking-id');

    $response = $this->putJson($url, [
        'slots' => $slots,
    ]);

    $response->assertStatus(200);

    // Verify booking updated
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'price' => 2000, // Should be recalculated for 2 hours
    ]);
});

test('mobile user updating booking with non-contiguous slots creates two separate bookings', function () {
    // Setup
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $pricePerInterval = 1000;
    $courtType = \App\Models\CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => $pricePerInterval,
        'interval_time_minutes' => 60
    ]);
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id
    ]);

    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '22:00', // Extended hours
        'is_available' => true,
    ]);

    $user = User::factory()->create();

    // Create initial booking (10:00-11:00)
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->addDay()->format('Y-m-d'),
        'end_date' => now()->addDay()->format('Y-m-d'), // Ensure single day booking
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 1000,
        'status' => 'confirmed'
    ]);

    Sanctum::actingAs($user, [], 'web');

    // Update with non-contiguous slots: 10:00-11:00 (original) AND 13:00-14:00 (new)
    $slots = [
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '13:00', 'end' => '14:00']
    ];

    $url = '/api/v1/bookings/' . EasyHashAction::encode($booking->id, 'booking-id');

    $response = $this->putJson($url, [
        'slots' => $slots,
    ]);

    $response->assertStatus(200);

    // 1. Verify original booking is updated (or kept) at 10:00-11:00
    $booking->refresh();
    expect($booking->start_time->format('H:i'))->toBe('10:00')
        ->and($booking->end_time->format('H:i'))->toBe('11:00')
        ->and($booking->price)->toBe(1000);

    // 2. Verify NEW booking is created at 13:00-14:00
    $newBooking = Booking::where('user_id', $user->id)
        ->where('id', '!=', $booking->id)
        ->first();

    expect($newBooking)->not->toBeNull()
        ->and($newBooking->start_time->format('H:i'))->toBe('13:00')
        ->and($newBooking->end_time->format('H:i'))->toBe('14:00')
        ->and($newBooking->price)->toBe(1000);
    
    // Ensure we have 2 bookings total for this user
    expect(Booking::where('user_id', $user->id)->count())->toBe(2);
});
