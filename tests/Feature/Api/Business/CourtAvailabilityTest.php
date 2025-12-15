<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Invoice;
use App\Models\CourtAvailability;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can get all court availabilities', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    CourtAvailability::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
    ]);

    $response = $this->getJson(route('court-availabilities.index', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
});

test('user can create a court availability', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $data = [
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ];

    $response = $this->postJson(route('court-availabilities.create', ['tenant_id' => $tenantHashId]), $data);

    $response->assertStatus(200);
    $this->assertDatabaseHas('courts_availabilities', [
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
    ]);
});

test('user can update a court availability', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $availability = CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
    ]);

    $availabilityHashId = EasyHashAction::encode($availability->id, 'court-availability-id');

    $data = [
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '23:00',
        'is_available' => false,
    ];

    $response = $this->putJson(route('court-availabilities.update', ['tenant_id' => $tenantHashId, 'availability_id' => $availabilityHashId]), $data);

    $response->assertStatus(200);
    $this->assertDatabaseHas('courts_availabilities', [
        'id' => $availability->id,
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '23:00',
        'is_available' => false,
    ]);
});

test('user can delete a court availability', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $availability = CourtAvailability::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $availabilityHashId = EasyHashAction::encode($availability->id, 'court-availability-id');

    $response = $this->deleteJson(route('court-availabilities.destroy', ['tenant_id' => $tenantHashId, 'availability_id' => $availabilityHashId]));

    $response->assertStatus(200);
    $this->assertDatabaseMissing('courts_availabilities', [
        'id' => $availability->id,
    ]);
});
