<?php

use App\Actions\General\EasyHashAction;
use App\Enums\BookingStatusEnum;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Manager\CurrencyModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('it verifies that available slots can be booked', function () {
    // 1. Setup
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

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

    $date = now()->addDays(1)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability for tomorrow
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '22:00:00',
        'is_available' => true,
    ]);

    // 2. Get available slots
    $response = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");

    $response->assertStatus(200);
    $slots = $response->json('data.slots');
    expect($slots)->not->toBeEmpty();

    // Pick the first slot
    $pickedSlot = $slots[0]; // Format: ['start' => 'HH:mm', 'end' => 'HH:mm']

    // 3. Create a booking
    $bookingResponse = $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [
            $pickedSlot,
        ],
    ]);

    $bookingResponse->assertStatus(201);
    $bookingResponse->assertJsonPath('data.status', BookingStatusEnum::CONFIRMED->value);

    // 4. Verify slot is no longer available
    $availabilityResponse = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    
    $availabilityResponse->assertStatus(200);
    $updatedSlots = $availabilityResponse->json('data.slots');
    
    // Check if the picked slot is still in the list
    $slotExists = false;
    foreach ($updatedSlots as $slot) {
        if ($slot['start'] === $pickedSlot['start'] && $slot['end'] === $pickedSlot['end']) {
            $slotExists = true;
            break;
        }
    }
    
    expect($slotExists)->toBeFalse();
});

test('it verifies that multiple sequential slots can be booked', function () {
    // 1. Setup
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

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

    $date = now()->addDays(2)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '22:00:00',
        'is_available' => true,
    ]);

    // 2. Get available slots
    $response = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    $slots = $response->json('data.slots');
    expect(count($slots))->toBeGreaterThanOrEqual(3);

    // Pick 3 sequential slots
    $pickedSlots = [$slots[0], $slots[1], $slots[2]];

    // 3. Create a booking
    $bookingResponse = $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => $pickedSlots,
    ]);

    $bookingResponse->assertStatus(201);
    $bookingResponse->assertJsonPath('data.status', BookingStatusEnum::CONFIRMED->value);

    // 4. Verify slots are no longer available
    $availabilityResponse = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    $updatedSlots = $availabilityResponse->json('data.slots');
    
    foreach ($pickedSlots as $pickedSlot) {
        $slotExists = false;
        foreach ($updatedSlots as $slot) {
            if ($slot['start'] === $pickedSlot['start'] && $slot['end'] === $pickedSlot['end']) {
                $slotExists = true;
                break;
            }
        }
        expect($slotExists)->toBeFalse();
    }
});

test('it fails when trying to book non-sequential slots', function () {
    // 1. Setup
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

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

    $date = now()->addDays(3)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '22:00:00',
        'is_available' => true,
    ]);

    // 2. Get available slots
    $response = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    $slots = $response->json('data.slots');
    expect(count($slots))->toBeGreaterThanOrEqual(3);

    // Pick 2 non-sequential slots (1st and 3rd)
    $pickedSlots = [$slots[0], $slots[2]];

    // 3. Create a booking (expect failure)
    $bookingResponse = $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => $pickedSlots,
    ]);

    $bookingResponse->assertStatus(422);
    $bookingResponse->assertJsonValidationErrors(['slots']);
    $bookingResponse->assertJsonFragment(['Os horários devem ser contíguos (sem intervalos entre eles)']);
});
