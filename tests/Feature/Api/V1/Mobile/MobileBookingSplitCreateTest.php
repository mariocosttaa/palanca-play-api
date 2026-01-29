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

test('mobile user can create multiple bookings when sending non-contiguous slots', function () {
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
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'web');

    // Create with non-contiguous slots: 10:00-11:00 AND 12:00-13:00 (1 hour gap)
    $slots = [
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '12:00', 'end' => '13:00']
    ];

    $response = $this->postJson('/api/v1/bookings', [
        'court_id' => EasyHashAction::encode($court->id, 'court-id'),
        'start_date' => now()->addDay()->format('Y-m-d'),
        'slots' => $slots,
    ]);

    $response->assertStatus(201); // Created

    // Verify 2 bookings were created
    $bookings = Booking::where('user_id', $user->id)->orderBy('start_time')->get();
    
    expect($bookings)->toHaveCount(2);
    
    expect($bookings[0]->start_time->format('H:i'))->toBe('10:00')
        ->and($bookings[0]->end_time->format('H:i'))->toBe('11:00')
        ->and($bookings[0]->price)->toBe(1000);
        
    expect($bookings[1]->start_time->format('H:i'))->toBe('12:00')
        ->and($bookings[1]->end_time->format('H:i'))->toBe('13:00')
        ->and($bookings[1]->price)->toBe(1000);
});
