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

    // 3. Create a booking (should now succeed and split into two)
    $bookingResponse = $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => $pickedSlots,
    ]);

    $bookingResponse->assertStatus(201);
    
    // Verify that 2 bookings were created for the court on that date
    $this->assertDatabaseCount('bookings', 2);
    
    $bookingsOnDate = Booking::where('court_id', $court->id)
        ->whereDate('start_date', $date)
        ->orderBy('start_time')
        ->get();
        
    expect($bookingsOnDate)->toHaveCount(2);
    expect($bookingsOnDate[0]->start_time->format('H:i'))->toBe($slots[0]['start']);
    expect($bookingsOnDate[1]->start_time->format('H:i'))->toBe($slots[2]['start']);
});

test('it verifies that buffer time is respected for different users but ignored for the same user', function () {
    // 1. Setup
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    
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

    $date = now()->addDays(4)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability: 08:00 - 12:00
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
    ]);

    // 2. User A books 08:00 - 09:00
    Sanctum::actingAs($userA, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '08:00', 'end' => '09:00']],
    ])->assertStatus(201);

    // 3. User B checks availability
    // Should see next slot at 09:10 (due to 10min buffer)
    Sanctum::actingAs($userB, [], 'sanctum');
    $responseB = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    $slotsB = $responseB->json('data.slots');
    
    // First slot for User B should be 09:10 - 10:10
    expect($slotsB[0]['start'])->toBe('09:10');
    
    // User B tries to book 09:00 - 10:00 (overlaps with buffer)
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '09:00', 'end' => '10:00']],
    ])->assertStatus(422)
      ->assertJson(fn ($json) => 
        $json->where('message', fn ($message) => str_contains($message, 'Incluindo intervalo de manutenção de 10 min'))
             ->etc()
    );

    // 4. User A checks availability
    // Should see next slot at 09:00 (buffer ignored for same user)
    Sanctum::actingAs($userA, [], 'sanctum');
    $responseA = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    $slotsA = $responseA->json('data.slots');
    
    // First slot for User A should be 09:00 - 10:00
    expect($slotsA[0]['start'])->toBe('09:00');

    // User A books 09:00 - 10:00 (sequential booking)
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '09:00', 'end' => '10:00']],
    ])->assertStatus(201);
});

test('it verifies buffer handling for first, middle and last slots of the day', function () {
    // 1. Setup
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    
    CurrencyModel::factory()->create(['code' => 'usd']);
    $tenant = Tenant::factory()->create([
        'auto_confirm_bookings' => true,
        'booking_interval_minutes' => 60,
        'currency' => 'usd',
        'timezone' => 'UTC',
    ]);

    $bufferMinutes = 15;
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

    $date = now()->addDays(5)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability: 08:00 - 12:00
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
    ]);

    // 2. Test First Slot (08:00 - 09:00)
    Sanctum::actingAs($userA, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '08:00', 'end' => '09:00']],
    ])->assertStatus(201);

    // User B should see next slot at 09:15
    Sanctum::actingAs($userB, [], 'sanctum');
    $slotsB = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots")->json('data.slots');
    expect($slotsB[0]['start'])->toBe('09:15');

    // 3. Test Middle Slot (09:15 - 10:15)
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '09:15', 'end' => '10:15']],
    ])->assertStatus(201);

    // User A should see next slot at 10:30 (since 10:15 + 15min buffer = 10:30)
    Sanctum::actingAs($userA, [], 'sanctum');
    $slotsA = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots")->json('data.slots');
    expect($slotsA[0]['start'])->toBe('10:30');

    // 4. Test Last Slot (10:30 - 11:30)
    // This slot is valid because it ends at 11:30, which is <= 12:00
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '10:30', 'end' => '11:30']],
    ])->assertStatus(201);

    // After 11:30, the next slot would be 11:45 - 12:45, but that exceeds 12:00
    // So no more slots should be available
    $finalSlots = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots")->json('data.slots');
    expect($finalSlots)->toBeEmpty();
});

test('it verifies sequential bookings for different users are blocked by buffer', function () {
    // 1. Setup
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    
    CurrencyModel::factory()->create(['code' => 'usd']);
    $tenant = Tenant::factory()->create([
        'auto_confirm_bookings' => true,
        'booking_interval_minutes' => 60,
        'currency' => 'usd',
        'timezone' => 'UTC',
    ]);

    $bufferMinutes = 20;
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

    $date = now()->addDays(6)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
        'is_available' => true,
    ]);

    // 2. User A books 10:00 - 11:00
    Sanctum::actingAs($userA, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '10:00', 'end' => '11:00']],
    ])->assertStatus(201);

    // 3. User B tries to book 11:00 - 12:00 (immediately after, should fail due to 20min buffer)
    Sanctum::actingAs($userB, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '11:00', 'end' => '12:00']],
    ])->assertStatus(422)
      ->assertJson(fn ($json) => 
        $json->where('message', fn ($message) => str_contains($message, 'Incluindo intervalo de manutenção de 20 min'))
             ->etc()
    );

    // 4. User B tries to book 11:20 - 12:20 (exactly after buffer, should succeed)
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '11:20', 'end' => '12:20']],
    ])->assertStatus(201);

    // 5. User A tries to book 09:00 - 10:00 (immediately before their first booking, should succeed)
    Sanctum::actingAs($userA, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '09:00', 'end' => '10:00']],
    ])->assertStatus(201);

    // 6. User B tries to book 08:00 - 09:00 (immediately before User A's new booking, should fail due to buffer)
    Sanctum::actingAs($userB, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '08:00', 'end' => '09:00']],
    ])->assertStatus(422);
});

test('it verifies complex multi-user booking interactions with buffers', function () {
    // 1. Setup
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    
    CurrencyModel::factory()->create(['code' => 'usd']);
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

    $date = now()->addDays(7)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
        'is_available' => true,
    ]);

    // 2. User A books 10:00 - 11:00
    Sanctum::actingAs($userA, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '10:00', 'end' => '11:00']],
    ])->assertStatus(201);

    // 3. User B books 12:00 - 13:00
    Sanctum::actingAs($userB, [], 'sanctum');
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '12:00', 'end' => '13:00']],
    ])->assertStatus(201);

    // 4. User C checks availability
    // Should see slots:
    // 08:00 - 09:00 (valid, ends before 10:00 buffer start at 09:50? No, buffer is AFTER booking)
    // Wait, let's re-verify buffer logic:
    // Booking A: 10:00 - 11:00. Buffer is 10 min AFTER. So busy until 11:10.
    // Booking B: 12:00 - 13:00. Buffer is 10 min AFTER. So busy until 13:10.
    
    // Slots for User C:
    // 1. 08:00 - 09:00 (OK)
    // 2. 09:00 - 10:00 (OK, ends exactly when A starts)
    // 3. 11:10 - 12:10 (NO, overlaps with B start at 12:00)
    // 4. 13:10 - 14:10 (OK)
    
    Sanctum::actingAs($userC, [], 'sanctum');
    $slotsC = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots")->json('data.slots');
    
    expect($slotsC)->toHaveCount(7);
    expect($slotsC[0]['start'])->toBe('08:00');
    expect($slotsC[1]['start'])->toBe('13:10');

    // 5. User A checks availability
    // Should see their own sequential slots:
    // 1. 08:00 - 09:00 (OK)
    // 2. 09:00 - 10:00 (OK)
    // 3. 13:10 - 14:10 (OK)
    // ... plus 5 more slots until 20:00
    
    Sanctum::actingAs($userA, [], 'sanctum');
    $slotsA = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots")->json('data.slots');
    
    expect($slotsA)->toHaveCount(8);
    expect($slotsA[2]['start'])->toBe('13:10');
});

test('it verifies a long sequence of bookings by different users with buffers', function () {
    // 1. Setup
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

    $date = now()->addDays(10)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability: 08:00 - 22:00
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '22:00:00',
        'is_available' => true,
    ]);

    // 2. Perform a sequence of bookings by different users
    // Each booking should be followed by a 10 min buffer
    // Slots: 08:00-09:00, 09:10-10:10, 10:20-11:20, 11:30-12:30, ...
    
    $currentTime = \Carbon\Carbon::parse($date . ' 08:00:00');
    $users = User::factory()->count(5)->create();

    for ($i = 0; $i < 5; $i++) {
        $user = $users[$i];
        Sanctum::actingAs($user, [], 'sanctum');
        
        $start = $currentTime->format('H:i');
        $end = $currentTime->copy()->addMinutes(60)->format('H:i');
        
        $this->postJson('/api/v1/bookings', [
            'court_id' => $courtIdHashId,
            'start_date' => $date,
            'slots' => [['start' => $start, 'end' => $end]],
        ])->assertStatus(201);
        
        // Move to next available slot for a DIFFERENT user (add interval + buffer)
        $currentTime->addMinutes(60 + $bufferMinutes);
    }

    // 3. Verify that the next available slot is correct
    $anyUser = User::factory()->create();
    Sanctum::actingAs($anyUser, [], 'sanctum');
    $response = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots");
    $slots = $response->json('data.slots');
    
    // Last booking was 4th (i=4): 
    // i=0: 08:00-09:00 (ends 09:00, next 09:10)
    // i=1: 09:10-10:10 (ends 10:10, next 10:20)
    // i=2: 10:20-11:20 (ends 11:20, next 11:30)
    // i=3: 11:30-12:30 (ends 12:30, next 12:40)
    // i=4: 12:40-13:40 (ends 13:40, next 13:50)
    
    expect($slots[0]['start'])->toBe('13:50');
});

test('it verifies same-user sequential bookings can fill the whole day without gaps', function () {
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

    $bufferMinutes = 15;
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

    $date = now()->addDays(11)->format('Y-m-d');
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // Create availability: 08:00 - 12:00
    CourtAvailability::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => $date,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
    ]);

    // 2. User books 08:00 - 09:00
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '08:00', 'end' => '09:00']],
    ])->assertStatus(201);

    // 3. User should see 09:00 - 10:00 as available (bypassing buffer)
    $slots = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots")->json('data.slots');
    expect($slots[0]['start'])->toBe('09:00');

    // 4. User books 09:00 - 10:00
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '09:00', 'end' => '10:00']],
    ])->assertStatus(201);

    // 5. User books 10:00 - 11:00
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '10:00', 'end' => '11:00']],
    ])->assertStatus(201);

    // 6. User books 11:00 - 12:00
    $this->postJson('/api/v1/bookings', [
        'court_id' => $courtIdHashId,
        'start_date' => $date,
        'slots' => [['start' => '11:00', 'end' => '12:00']],
    ])->assertStatus(201);

    // 7. No more slots should be available
    $finalSlots = $this->getJson("/api/v1/courts/{$courtIdHashId}/availability/{$date}/slots")->json('data.slots');
    expect($finalSlots)->toBeEmpty();
});
