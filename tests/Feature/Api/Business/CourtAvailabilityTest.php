<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can create court availability via endpoint', function () {
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

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    $availabilityData = [
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ];

    // Create availability
    $response = $this->postJson(
        route('courts.availabilities.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtIdHashId]),
        $availabilityData
    );

    // Assert the response is successful
    $response->assertStatus(201);

    // Assert availability was created in DB
    $this->assertDatabaseHas('courts_availabilities', [
        'tenant_id' => $tenant->id,
        'court_id' => $court->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
    ]);
});

test('user can update court availability via endpoint', function () {
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

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create initial availability
    $availability = $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court ID and availability ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');
    $availabilityIdHashId = EasyHashAction::encode($availability->id, 'court-availability-id');

    $updateData = [
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
        'is_available' => true,
    ];

    // Update availability
    $response = $this->putJson(
        route('courts.availabilities.update', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId,
            'availability_id' => $availabilityIdHashId
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

test('user can delete court availability via endpoint', function () {
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

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create initial availability
    $availability = $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    // Encode the court ID and availability ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');
    $availabilityIdHashId = EasyHashAction::encode($availability->id, 'court-availability-id');

    // Delete availability
    $response = $this->deleteJson(
        route('courts.availabilities.destroy', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId,
            'availability_id' => $availabilityIdHashId
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert DB missing
    $this->assertDatabaseMissing('courts_availabilities', [
        'id' => $availability->id,
    ]);
});

test('user can list court availabilities via endpoint', function () {
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

    // Create a court type and court
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id, 'court_type_id' => $courtType->id]);

    // Create availabilities
    $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '22:00',
        'is_available' => true,
    ]);

    $court->availabilities()->create([
        'tenant_id' => $tenant->id,
        'day_of_week_recurring' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '21:00',
        'is_available' => true,
    ]);

    // Encode the court ID
    $courtIdHashId = EasyHashAction::encode($court->id, 'court-id');

    // List availabilities
    $response = $this->getJson(
        route('courts.availabilities.index', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtIdHashId
        ])
    );

    // Assert the response is successful
    $response->assertStatus(200);
    $response->assertJsonCount(2, 'data');
});
