<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bookings are ordered by start date and time descending', function () {
    $tenant = Tenant::factory()->create();
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);
    
    // Business User for Authentication
    $authUser = \App\Models\BusinessUser::factory()->create();
    $authUser->tenants()->attach($tenant);
    
    // Client User for Bookings
    $clientUser = User::factory()->create();
    \App\Models\UserTenant::create(['user_id' => $clientUser->id, 'tenant_id' => $tenant->id]);

    $currency = \App\Models\Manager\CurrencyModel::factory()->create();

    // Create bookings in random order
    $booking1 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $clientUser->id,
        'currency_id' => $currency->id,
        'start_date' => '2025-01-01',
        'start_time' => '10:00:00',
        'created_at' => now()->subDays(5),
    ]);

    $booking2 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $clientUser->id,
        'currency_id' => $currency->id,
        'start_date' => '2025-01-02',
        'start_time' => '09:00:00',
        'created_at' => now()->subDays(1),
    ]);

    $booking3 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $clientUser->id,
        'currency_id' => $currency->id,
        'start_date' => '2025-01-01',
        'start_time' => '12:00:00',
        'created_at' => now()->subDays(10),
    ]);

    // Expected Order (Desc):
    // 1. 2025-01-02 09:00 (booking2)
    // 2. 2025-01-01 12:00 (booking3)
    // 3. 2025-01-01 10:00 (booking1)

    $tenantIdHashed = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');
    $response = $this->actingAs($authUser, 'business')->getJson("/api/business/v1/business/{$tenantIdHashed}/bookings");

    $response->assertOk();
    
    $data = $response->json('data');
    
    expect($data[0]['id'])->toBe(\App\Actions\General\EasyHashAction::encode($booking2->id, 'booking-id'));
    expect($data[1]['id'])->toBe(\App\Actions\General\EasyHashAction::encode($booking3->id, 'booking-id'));
    expect($data[2]['id'])->toBe(\App\Actions\General\EasyHashAction::encode($booking1->id, 'booking-id'));
});
