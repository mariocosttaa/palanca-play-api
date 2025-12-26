<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use App\Enums\BookingStatusEnum;

test('reproduce slot generation with buffer', function () {
    $tenant = Tenant::factory()->create([
        'booking_interval_minutes' => 60,
        'buffer_between_bookings_minutes' => 0
    ]);
    
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 10,
    ]);
    
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    
    // Availability 08:00 - 20:00
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'court_type_id' => $courtType->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
        'is_available' => true,
    ]);
    
    // Booking 09:00 - 10:00
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status' => BookingStatusEnum::CONFIRMED,
    ]);

    // Buffer ends at 10:10.
    // Fixed slots:
    // 08:00-09:00 (Available)
    // 09:00-10:00 (Booked)
    // 10:00-11:00 (Overlap with 10:10 buffer) -> Blocked?
    // 11:00-12:00 (Available)
    
    $slots = $court->getAvailableSlots(now()->format('Y-m-d'));
    
    // New expectation (Dynamic slots)
    // 09:00-10:00 Booked. Buffer 10m -> 10:10.
    // Next slot should start at 10:10.
    
    $foundDynamicSlot = $slots->contains(function ($slot) {
        return $slot['start'] === '10:10';
    });
    
    expect($foundDynamicSlot)->toBeTrue();
    
    // Check next slot after 10:10-11:10
    // Should be 11:10-12:10
    $foundNextDynamicSlot = $slots->contains(function ($slot) {
        return $slot['start'] === '11:10';
    });
    
    expect($foundNextDynamicSlot)->toBeTrue();
});
