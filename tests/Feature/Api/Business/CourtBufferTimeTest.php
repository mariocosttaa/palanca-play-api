<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;

uses(RefreshDatabase::class);

test('court uses court type interval time for slot generation', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    // Create tenant with 60 minute intervals
    $tenant = Tenant::factory()->create([
        'currency' => 'eur',
        'booking_interval_minutes' => 60,
        'buffer_between_bookings_minutes' => 0
    ]);
    
    // Create court type with 90 minute intervals (different from tenant)
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 90,
        'buffer_time_minutes' => 15,
    ]);
    
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    
    // Create availability for today
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'court_type_id' => $courtType->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'is_available' => true,
    ]);
    
    $slots = $court->getAvailableSlots(now()->format('Y-m-d'));
    
    // With 90 minute intervals from 9:00 to 18:00 we should get:
    // 09:00-10:30, 10:30-12:00, 12:00-13:30, 13:30-15:00, 15:00-16:30
    // (16:30-18:00 slot won't fit as it would end at 18:00)
    expect($slots)->toHaveCount(6);
    expect($slots->first()['start'])->toBe('09:00');
    expect($slots->first()['end'])->toBe('10:30');
});

test('court uses court type buffer time between bookings', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    // Create tenant with 0 buffer time
    $tenant = Tenant::factory()->create([
        'currency' => 'eur',
        'booking_interval_minutes' => 60,
        'buffer_between_bookings_minutes' => 0
    ]);
    
    // Create court type with 15 minute buffer (different from tenant)
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 15,
    ]);
    
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    
    $client = User::factory()->create();
    
    // Create availability for today
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'court_type_id' => $courtType->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
        'is_available' => true,
    ]);
    
    // Create a booking from 10:00 to 11:00
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'currency_id' => $currency->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 1000,
        'price' => 1000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    
    // Check availability at 11:00-12:00 (immediately after booking)
    // This should NOT be available because of 15 minute buffer
    $result = $court->checkAvailability(now()->format('Y-m-d'), '11:00', '12:00');
    expect($result)->not->toBeNull(); // Should return error message
    expect($result)->toContain('reservado');
    
    // Check availability at 11:15-12:15 (after buffer time)
    // This SHOULD be available
    $result = $court->checkAvailability(now()->format('Y-m-d'), '11:15', '12:15');
    expect($result)->toBeNull(); // Null means available
});

test('buffer time is enforced between sequential bookings', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    $tenant = Tenant::factory()->create([
        'currency' => 'eur',
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
    
    $client = User::factory()->create();
    
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'court_type_id' => $courtType->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
        'is_available' => true,
    ]);
    
    // Create first booking: 10:00-11:00
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'currency_id' => $currency->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 1000,
        'price' => 1000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    
    // Try to book sequential slot 11:00-12:00
    // Same client: buffer should be ignored for sequential slot
    $result = $court->checkAvailability(now()->format('Y-m-d'), '11:00', '12:00', $client->id);
    expect($result)->toBeNull(); // Should be available for same client

    // Same client: Try to book with a gap that is INSIDE the buffer (e.g. 5 min gap)
    // Booking 10:00-11:00. Buffer 10 mins.
    // Try 11:05-12:05.
    // 11:05 is NOT equal to 11:00, so buffer logic applies.
    // 11:05 < 11:10 (buffer end) -> Overlap -> Blocked.
    $resultGapInside = $court->checkAvailability(now()->format('Y-m-d'), '11:05', '12:05', $client->id);
    expect($resultGapInside)->not->toBeNull();
    expect($resultGapInside)->toContain('reservado');

    // Same client: Try to book with a gap OUTSIDE the buffer
    // Try 11:15-12:15.
    // 11:15 > 11:10. No overlap -> Allowed.
    $resultGapOutside = $court->checkAvailability(now()->format('Y-m-d'), '11:15', '12:15', $client->id);
    expect($resultGapOutside)->toBeNull();
    
    // Different client: buffer should still apply for sequential
    $otherClient = User::factory()->create();
    $resultOther = $court->checkAvailability(now()->format('Y-m-d'), '11:00', '12:00', $otherClient->id);
    expect($resultOther)->not->toBeNull(); // Should fail due to buffer
    expect($resultOther)->toContain('reservado');

    // After buffer (11:10-12:10) should work for anyone
    $result = $court->checkAvailability(now()->format('Y-m-d'), '11:10', '12:10');
    expect($result)->toBeNull(); // Should be available
});

test('different court types have independent buffer times', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    $tenant = Tenant::factory()->create([
        'currency' => 'eur',
        'booking_interval_minutes' => 60,
        'buffer_between_bookings_minutes' => 0
    ]);
    
    // Court type 1: No buffer
    $courtType1 = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 0,
    ]);
    
    // Court type 2: 20 minute buffer
    $courtType2 = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 20,
    ]);
    
    $court1 = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType1->id,
    ]);
    
    $court2 = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType2->id,
    ]);
    
    $client = User::factory()->create();
    
    // Create availabilities for both courts
    foreach ([$court1, $court2] as $index => $court) {
        CourtAvailability::create([
            'tenant_id' => $tenant->id,
            'court_id' => $court->id,
            'court_type_id' => $index === 0 ? $courtType1->id : $courtType2->id,
            'day_of_week_recurring' => now()->format('l'),
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'is_available' => true,
        ]);
    }
    
    // Create bookings for both courts at 10:00-11:00
    foreach ([$court1, $court2] as $court) {
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'court_id' => $court->id,
            'user_id' => $client->id,
            'currency_id' => $currency->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'price' => 1000,
            'payment_status' => PaymentStatusEnum::PAID,
            'status' => BookingStatusEnum::CONFIRMED,
        ]);
    }
    
    // Court 1 (no buffer): 11:00-12:00 should be available
    $result1 = $court1->checkAvailability(now()->format('Y-m-d'), '11:00', '12:00');
    expect($result1)->toBeNull(); // Available
    
    // Court 2 (20 min buffer): 11:00-12:00 should NOT be available
    $result2 = $court2->checkAvailability(now()->format('Y-m-d'), '11:00', '12:00');
    expect($result2)->not->toBeNull(); // Not available
    
    // Court 2: 11:20-12:20 (after buffer) should be available
    $result3 = $court2->checkAvailability(now()->format('Y-m-d'), '11:20', '12:20');
    expect($result3)->toBeNull(); // Available
});

test('slot generation excludes slots overlapping with buffered bookings', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    $tenant = Tenant::factory()->create([
        'currency' => 'eur',
        'booking_interval_minutes' => 60,
        'buffer_between_bookings_minutes' => 0
    ]);
    
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 30, // 30 minute buffer
    ]);
    
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    
    $client = User::factory()->create();
    
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'court_type_id' => $courtType->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '10:00:00',
        'end_time' => '15:00:00',
        'is_available' => true,
    ]);
    
    // Create a booking from 11:00 to 12:00
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'currency_id' => $currency->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '11:00:00',
        'end_time' => '12:00:00',
        'price' => 1000,
        'price' => 1000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    
    $slots = $court->getAvailableSlots(now()->format('Y-m-d'));
    
    // With booking from 11:00-12:00 and 30 min buffer:
    // - Booking end with buffer: 12:30
    // - Overlap check: currentSlotStart < 12:30 AND currentSlotEnd > 11:00
    //
    // 10:00-11:00: 10:00 < 12:30 ✓, 11:00 > 11:00 ✗ → NOT overlapping, AVAILABLE
    // 11:00-12:00: 11:00 < 12:30 ✓, 12:00 > 11:00 ✓ → Overlapping, BLOCKED
    // 12:00-13:00: 12:00 < 12:30 ✓, 13:00 > 11:00 ✓ → Overlapping, BLOCKED  
    // 13:00-14:00: 13:00 < 12:30 ✗ → NOT overlapping, AVAILABLE
    // 14:00-15:00: 14:00 < 12:30 ✗ → NOT overlapping, AVAILABLE
    
    // So we should get: 10:00-11:00, 12:30-13:30, 13:30-14:30
    expect($slots)->toHaveCount(3);
    expect($slots->get(0)['start'])->toBe('10:00');
    expect($slots->get(1)['start'])->toBe('12:30');
    expect($slots->get(2)['start'])->toBe('13:30');
});

test('court ignores tenant buffer time setting', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    // Create tenant with 30 minute buffer
    $tenant = Tenant::factory()->create([
        'currency' => 'eur',
        'booking_interval_minutes' => 60,
        'buffer_between_bookings_minutes' => 30
    ]);
    
    // Create court type with 0 minute buffer
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => 0,
    ]);
    
    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);
    
    $client = User::factory()->create();
    
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'court_type_id' => $courtType->id,
        'day_of_week_recurring' => now()->format('l'),
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
        'is_available' => true,
    ]);
    
    // Create booking 10:00-11:00
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'currency_id' => $currency->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => 1000,
        'price' => 1000,
        'payment_status' => PaymentStatusEnum::PAID,
        'status' => BookingStatusEnum::CONFIRMED,
    ]);
    
    // If tenant buffer (30m) was used, 11:00-12:00 would be blocked
    // But since court type has 0 buffer, it should be available
    $result = $court->checkAvailability(now()->format('Y-m-d'), '11:00', '12:00');
    expect($result)->toBeNull(); // Available
});
