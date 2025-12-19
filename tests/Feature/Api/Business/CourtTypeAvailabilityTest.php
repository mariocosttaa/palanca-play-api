<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\Invoice;
use Database\Factories\CourtTypeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can create court type availability via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
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

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type for this tenant
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Encode the court type ID
    $courtTypeIdHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    $availabilityData = [
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ];

    // Create availability
    $response = $this->postJson(
        route('court-types.availabilities.store', ['tenant_id' => $tenantHashId, 'court_type_id' => $courtTypeIdHashId]),
        $availabilityData
    );

    // Assert the response is successful
    $response->assertStatus(201);

    // Assert availability was created in DB
    $this->assertDatabaseHas('courts_availabilities', [
        'tenant_id' => $tenant->id,
        'court_type_id' => $courtType->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
    ]);
});

test('user can update court type availability via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
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

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type for this tenant
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Create initial availability
    $availability = $courtType->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court type ID
    $courtTypeIdHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    $updateData = [
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
        'is_available' => true,
    ];

    // Update availability
    $response = $this->putJson(
        route('court-types.availabilities.update', [
            'tenant_id' => $tenantHashId,
            'court_type_id' => $courtTypeIdHashId,
            'availability_id' => $availability->id
        ]),
        $updateData
    );

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert DB updated
    $this->assertDatabaseHas('courts_availabilities', [
        'id' => $availability->id,
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
    ]);
});

test('user can delete court type availability via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
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

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type for this tenant
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Create initial availability
    $availability = $courtType->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court type ID
    $courtTypeIdHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    // Delete availability
    $response = $this->deleteJson(
        route('court-types.availabilities.destroy', [
            'tenant_id' => $tenantHashId,
            'court_type_id' => $courtTypeIdHashId,
            'availability_id' => $availability->id
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert DB missing
    $this->assertDatabaseMissing('courts_availabilities', [
        'id' => $availability->id,
    ]);
});

test('user can list court type availabilities via endpoint', function () {
    // Create a tenant and business user and attach the business user to the tenant
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

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create a court type for this tenant
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Create availabilities
    $courtType->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    $courtType->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
        'is_available' => true,
    ]);

    // Encode the court type ID
    $courtTypeIdHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    // List availabilities
    $response = $this->getJson(
        route('court-types.availabilities.index', [
            'tenant_id' => $tenantHashId,
            'court_type_id' => $courtTypeIdHashId
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});
