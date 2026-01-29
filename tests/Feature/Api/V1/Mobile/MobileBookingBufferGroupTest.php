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

test('sequential slots with buffer are grouped into a single booking', function () {
    // Setup
    $currency = CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $bufferMinutes = 10;
    $courtType = \App\Models\CourtType::factory()->create([
        'tenant_id' => $tenant->id,
        'price_per_interval' => 1000,
        'interval_time_minutes' => 60,
        'buffer_time_minutes' => $bufferMinutes
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
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'web');

    // Sequential slots: 10:00-11:00 and 11:10-12:10 (gap of 10 min buffer)
    $slots = [
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '11:10', 'end' => '12:10']
    ];

    $response = $this->postJson('/api/v1/bookings', [
        'court_id' => EasyHashAction::encode($court->id, 'court-id'),
        'start_date' => now()->addDay()->format('Y-m-d'),
        'slots' => $slots,
    ]);

    $response->assertStatus(201);

    // Verify ONLY 1 booking was created (grouped)
    $bookings = Booking::where('user_id', $user->id)->get();
    
    expect($bookings)->toHaveCount(1);
    expect($bookings[0]->start_time->format('H:i'))->toBe('10:00')
        ->and($bookings[0]->end_time->format('H:i'))->toBe('12:10')
        ->and($bookings[0]->price)->toBe(2000);
});
