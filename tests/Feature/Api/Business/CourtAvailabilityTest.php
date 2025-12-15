<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('can get available dates', function () {
    $tenant = Tenant::factory()->create(['booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create availability for Mondays
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => 'monday',
        'start_time' => '08:00',
        'end_time' => '12:00',
        'is_available' => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    // Find next Monday
    $nextMonday = now()->next('Monday');
    
    $response = $this->getJson(route('courts.availability.dates', [
        'tenant_id' => $tenantHashId,
        'court_id' => $courtHashId,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->addWeeks(2)->format('Y-m-d'),
    ]));

    $response->assertStatus(200);
    $this->assertContains($nextMonday->format('Y-m-d'), $response->json('data'));
});

test('can get available slots', function () {
    $tenant = Tenant::factory()->create(['booking_interval_minutes' => 60]);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Availability: 08:00 - 10:00 (2 slots: 08-09, 09-10)
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'is_available' => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('courts.availability.slots', [
        'tenant_id' => $tenantHashId,
        'court_id' => $courtHashId,
        'date' => now()->format('Y-m-d'),
    ]));

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['start' => '08:00', 'end' => '09:00'])
        ->assertJsonFragment(['start' => '09:00', 'end' => '10:00']);
});

test('slots respect bookings', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    
    $tenant = Tenant::factory()->create(['booking_interval_minutes' => 60, 'buffer_between_bookings_minutes' => 0, 'currency' => 'eur']);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $client = \App\Models\User::factory()->create();
    
    // Availability: 08:00 - 11:00 (3 slots)
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '11:00',
        'is_available' => true,
    ]);

    // Booking: 09:00 - 10:00
    Booking::create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'user_id' => $client->id,
        'currency_id' => $currency->id,
        'start_date' => now()->format('Y-m-d'),
        'end_date' => now()->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'price' => 1000,
        'is_pending' => false,
        'is_cancelled' => false,
        'is_paid' => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('courts.availability.slots', [
        'tenant_id' => $tenantHashId,
        'court_id' => $courtHashId,
        'date' => now()->format('Y-m-d'),
    ]));

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data') // 08-09 and 10-11 should be available
        ->assertJsonFragment(['start' => '08:00', 'end' => '09:00'])
        ->assertJsonFragment(['start' => '10:00', 'end' => '11:00'])
        ->assertJsonMissing(['start' => '09:00', 'end' => '10:00']);
});

test('booking fails if slot unavailable', function () {
    $currency = \App\Models\Manager\CurrencyModel::factory()->create(['code' => 'eur']);
    $tenant = Tenant::factory()->create(['booking_interval_minutes' => 60, 'currency' => 'eur']);
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Availability: 08:00 - 09:00
    CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'specific_date' => now()->format('Y-m-d'),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'is_available' => true,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    // Try to book 09:00 - 10:00 (Unavailable)
    $response = $this->postJson(route('bookings.store', ['tenant_id' => $tenantHashId]), [
        'court_id' => $courtHashId,
        'start_date' => now()->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'client' => ['name' => 'Test Client'],
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['message' => 'Horário indisponível.']);
});

test('can create availability', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('courts.availabilities.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
        'day_of_week_recurring' => 'monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'is_available' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['day_of_week_recurring' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00']);

    $this->assertDatabaseHas('courts_availabilities', [
        'court_id' => $court->id,
        'day_of_week_recurring' => 'monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);
});

test('can update availability', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $availability = CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => 'monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('courts.availabilities.update', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId, 'availability_id' => $availability->id]), [
        'end_time' => '11:00',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['end_time' => '11:00']);

    $this->assertDatabaseHas('courts_availabilities', [
        'id' => $availability->id,
        'end_time' => '11:00',
    ]);
});

test('can delete availability', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    $availability = CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->deleteJson(route('courts.availabilities.destroy', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId, 'availability_id' => $availability->id]));

    $response->assertStatus(200);

    $this->assertDatabaseMissing('courts_availabilities', ['id' => $availability->id]);
});

test('can list availabilities', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    CourtAvailability::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
    ]);

    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('courts.availabilities.index', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]));

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});
