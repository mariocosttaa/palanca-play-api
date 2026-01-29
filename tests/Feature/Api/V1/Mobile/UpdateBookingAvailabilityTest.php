<?php

use App\Actions\General\EasyHashAction;
use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Manager\CurrencyModel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('it verifies a booking can be updated by excluding itself from availability checks', function () {
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

    $bufferMinutes = 10;
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => 5000,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => $bufferMinutes,
    ]);

    $court = Court::factory()->create([
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
    ]);

    $date = now()->addDays(20)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability: 14:00 - 18:00
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '14:00:00',
        'end_time' => '18:00:00',
        'is_available' => true,
    ]);

    // 2. Create a Booking (14:00 - 15:00)
    $response = $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '14:00', 'end' => '15:00']],
    ]);
    $response->assertStatus(201);
    $bookingId = $response->json('data.id'); // This is HashID
    
    // 3. Verify slot is taken (without excluding booking_id)
    $response = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    $slots = $response->json('data.slots');
    $hasSlot = collect($slots)->contains('start', '14:00');
    expect($hasSlot)->toBeFalse();

    // 4. Verify slot IS available when excluding booking_id
    $response = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots?booking_id={$bookingId}");
    $response->assertStatus(200);
    $slots = $response->json('data.slots');
    $hasSlot = collect($slots)->contains('start', '14:00');
    expect($hasSlot)->toBeTrue('Expected 14:00 slot to be available when excluding the current booking');

    // 5. Update the booking (keeping same time 14:00 - 15:00)
    // This verifies validation logic excludes the booking ID
    $updateResponse = $this->putJson("/api/v1/bookings/{$bookingId}", [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '14:00', 'end' => '15:00']],
    ]);
    $updateResponse->assertStatus(200);
    
    // 6. Update the booking to a new time (15:00 - 16:00)
    // This verifies we can move it
    $updateResponse2 = $this->putJson("/api/v1/bookings/{$bookingId}", [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '15:00', 'end' => '16:00']],
    ]);
    $updateResponse2->assertStatus(200);
    
    // 7. Verify consecutive booking (User B tries to book 15:00-16:00 now occupied by updated booking)
    $userB = User::factory()->create();
    Sanctum::actingAs($userB, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '15:00', 'end' => '16:00']],
    ])->assertStatus(422); // Should fail
});
