<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\BookingStatusEnum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(SubscriptionService::class, function ($mock) {
        $mock->shouldReceive('getValidInvoice')->andReturn(['id' => 1]);
    });
});

test('get slots includes current booking slot when booking_id is provided', function () {
    $tenant = Tenant::factory()->create();
    $owner = \App\Models\BusinessUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    $owner->tenants()->attach($tenant);
    
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 0,
    ]);
    
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    $user = User::factory()->create();
    
    // Create availability 09:00 - 18:00
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_available' => true,
    ]);

    // Create a booking 10:00 - 11:00
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $bookingHash = EasyHashAction::encode($booking->id, 'booking-id');
    $courtHash = EasyHashAction::encode($court->id, 'court-id');
    $tenantHash = EasyHashAction::encode($tenant->id, 'tenant-id');

    // 1. Check slots WITHOUT booking_id -> 10:00 should be missing
    $response = $this->actingAs($owner, 'business')
        ->getJson(route('courts.availability.slots', [
            'tenant_id' => $tenantHash,
            'court_id' => $courtHash,
            'date' => now()->format('Y-m-d')
        ]));

    $response->assertOk();
    $slots = collect($response->json('data'));
    
    // Should NOT find a slot starting at 10:00
    $slotAt10 = $slots->first(fn($slot) => $slot['start'] === '10:00');
    expect($slotAt10)->toBeNull();

    // 2. Check slots WITH booking_id -> 10:00 should be present
    $response = $this->actingAs($owner, 'business')
        ->getJson(route('courts.availability.slots', [
            'tenant_id' => $tenantHash,
            'court_id' => $courtHash,
            'date' => now()->format('Y-m-d'),
            'booking_id' => $bookingHash
        ]));

    $response->assertOk();
    $slots = collect($response->json('data'));
    
    // Should find a slot starting at 10:00
    $slotAt10 = $slots->first(fn($slot) => $slot['start'] === '10:00');
    expect($slotAt10)->not->toBeNull();
});

test('get dates includes fully booked date when booking_id is provided', function () {
    $tenant = Tenant::factory()->create();
    $owner = \App\Models\BusinessUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    $owner->tenants()->attach($tenant);

    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 0,
    ]);

    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    $user = User::factory()->create();
    
    // Create availability only 10:00 - 11:00 for simplicity
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Create a booking that fills the entire availability
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $bookingHash = EasyHashAction::encode($booking->id, 'booking-id');
    $courtHash = EasyHashAction::encode($court->id, 'court-id');
    $tenantHash = EasyHashAction::encode($tenant->id, 'tenant-id');

    // 1. Check dates WITHOUT booking_id -> Date should be missing (fully booked)
    $response = $this->actingAs($owner, 'business')
        ->getJson(route('courts.availability.dates', [
            'tenant_id' => $tenantHash,
            'court_id' => $courtHash,
            'month' => now()->month,
            'year' => now()->year,
        ]));

    $response->assertOk();
    $dates = collect($response->json('data'));
    expect($dates->contains(now()->format('Y-m-d')))->toBeFalse();

    // 2. Check dates WITH booking_id -> Date should be present
    $response = $this->actingAs($owner, 'business')
        ->getJson(route('courts.availability.dates', [
            'tenant_id' => $tenantHash,
            'court_id' => $courtHash,
            'month' => now()->month,
            'year' => now()->year,
            'booking_id' => $bookingHash
        ]));

    $response->assertOk();
    $dates = collect($response->json('data'));
    expect($dates->contains(now()->format('Y-m-d')))->toBeTrue();
});

test('update booking allows keeping same time', function () {
    $tenant = Tenant::factory()->create();
    $owner = \App\Models\BusinessUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    $owner->tenants()->attach($tenant);

    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 0,
    ]);

    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    $user = User::factory()->create();
    
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_available' => true,
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $bookingHash = EasyHashAction::encode($booking->id, 'booking-id');
    $tenantHash = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Update with SAME time
    $response = $this->actingAs($owner, 'business')
        ->putJson(route('bookings.update', [
            'tenant_id' => $tenantHash,
            'booking_id' => $bookingHash
        ]), [
            'start_date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

    $response->assertOk();
});

test('update booking fails if moving to occupied slot', function () {
    $tenant = Tenant::factory()->create();
    $owner = \App\Models\BusinessUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    $owner->tenants()->attach($tenant);

    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 0,
    ]);

    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    $user = User::factory()->create();
    
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_available' => true,
    ]);

    // Booking 1: 10:00 - 11:00 (The one we are updating)
    $booking1 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // Booking 2: 12:00 - 13:00 (Occupied slot)
    $booking2 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '12:00',
        'end_time' => '13:00',
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $booking1Hash = EasyHashAction::encode($booking1->id, 'booking-id');
    $tenantHash = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Try to move Booking 1 to Booking 2's time (12:00 - 13:00)
    $response = $this->actingAs($owner, 'business')
        ->putJson(route('bookings.update', [
            'tenant_id' => $tenantHash,
            'booking_id' => $booking1Hash
        ]), [
            'start_date' => now()->format('Y-m-d'),
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

    $response->assertStatus(400);
    $response->assertJsonFragment(['message' => 'Este horário já está reservado (12:00 - 13:00).']);
});

test('update booking succeeds if moving to available slot', function () {
    $tenant = Tenant::factory()->create();
    $owner = \App\Models\BusinessUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    $owner->tenants()->attach($tenant);

    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 0,
    ]);

    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    $user = User::factory()->create();
    
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_available' => true,
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $user->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    $bookingHash = EasyHashAction::encode($booking->id, 'booking-id');
    $tenantHash = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Move to 14:00 - 15:00
    $response = $this->actingAs($owner, 'business')
        ->putJson(route('bookings.update', [
            'tenant_id' => $tenantHash,
            'booking_id' => $bookingHash
        ]), [
            'start_date' => now()->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]);


    $response->assertOk();
    
    $booking->refresh();
    expect($booking->start_time->format('H:i'))->toBe('14:00');
    expect($booking->end_time->format('H:i'))->toBe('15:00');
});
