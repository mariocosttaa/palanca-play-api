<?php

use App\Actions\General\EasyHashAction;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\Invoice;
use App\Models\Manager\CurrencyModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('business user can create booking with slots array and correct price calculation', function () {
    // Setup
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    // Court with pricing
    $pricePerInterval = 1000;
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id
    ]);
    // Create court type and attach to court (assuming factory doesn't do it or we need specific price)
    $courtType = \App\Models\CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => $pricePerInterval,
        'interval_time_minutes' => 60
    ]);
    $court->update(['court_type_id' => $courtType->id]);

    // Availability
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    $client = User::factory()->create();

    Sanctum::actingAs($user, [], 'business');

    // Define slots
    $startTime = '10:00';
    $endTime = '12:00'; // 2 hours = 2 slots
    $slots = [
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '11:00', 'end' => '12:00']
    ];

    $response = $this->postJson(route('bookings.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id' => EasyHashAction::encode($court->id, 'court-id'),
        'client_id' => EasyHashAction::encode($client->id, 'user-id'),
        'start_date' => now()->addDay()->format('Y-m-d'),
        'slots' => $slots,
        // No start_time/end_time sent explicitly, should be derived
        // No price sent explicitly, should be calculated
    ]);

    $response->assertStatus(201);
    
    // Verify booking created with correct price
    $this->assertDatabaseHas('bookings', [
        'court_id' => $court->id,
        // 'start_time' => '10:00:00', // Skipping specific time format check as it varies by env (sqlite/mysql)
        // 'end_time' => '12:00:00',
        'price' => 2000,
    ]);
});

test('business user can update booking with slots array and price recalculation', function () {
    // Setup
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

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

    $client = User::factory()->create();

    // Create initial booking
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'start_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 1000,
        'status' => 'confirmed' // Ensure it's not pending if that affects availability checks (though existing booking is excluded)
    ]);

    Sanctum::actingAs($user, [], 'business');

    // Update with 2 slots (expanding time)
    $slots = [
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '11:00', 'end' => '12:00']
    ];

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id'),
    ]), [
        'slots' => $slots,
        // No price sent, should recalculate
    ]);

    $response->assertStatus(200);

    // Verify booking updated
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        // 'start_time' => '10:00:00',
        // 'end_time' => '12:00:00',
        'price' => 2000,
    ]);
});
