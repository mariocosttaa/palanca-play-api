<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('business user can create booking with new client no email', function () {
    // Create currency
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Valid invoice
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Add availability
    \App\Models\CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('bookings.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id' => $courtHashId,
        'start_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'client' => [
            'name' => 'New Client',
            'phone' => '123456789',
        ],
        'paid_at_venue' => true,
    ]);

    $response->assertStatus(200);
    
    // Verify client created
    $this->assertDatabaseHas('users', [
        'name' => 'New Client',
        'email' => null,
    ]);

    // Verify booking created
    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $tenant->id,
        'paid_at_venue' => true,
        'is_paid' => true,
    ]);
});

test('business user can create booking with existing client', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Add availability
    \App\Models\CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '20:00',
        'is_available' => true,
    ]);

    $client = User::factory()->create();

    Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('bookings.store', ['tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id')]), [
        'court_id' => EasyHashAction::encode($court->id, 'court-id'),
        'client_id' => EasyHashAction::encode($client->id, 'user-id'),
        'start_date' => now()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'user_id' => $client->id,
    ]);
});

test('business user can update booking paid at venue', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['currency' => 'eur', 'booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addDay()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = User::factory()->create();
    
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'paid_at_venue' => false,
        'is_paid' => false,
    ]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('bookings.update', [
        'tenant_id' => EasyHashAction::encode($tenant->id, 'tenant-id'),
        'booking_id' => EasyHashAction::encode($booking->id, 'booking-id')
    ]), [
        'paid_at_venue' => true,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'paid_at_venue' => true,
        'is_paid' => true,
    ]);
});
