<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use Database\Factories\CourtTypeFactory;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can get all court types', function () {

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

    // Create court types
    CourtType::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
    ]);

    // Get the court types
    $response = $this->getJson(route('court-types.index', ['tenant_id' => $tenantHashId]));

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response contains the court types
    $response->assertJsonCount(3, 'data');
});

test('user can get a court type details and courts', function () {
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

    // Create a court type for this tenant
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Create a court for this court type
    Court::factory()->create([
        'court_type_id' => $courtType->id,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Encode the court type ID
    $courtTypeIdHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    // Get the court type
    $response = $this->getJson(route('court-types.show', ['tenant_id' => $tenantHashId, 'court_type_id' => $courtTypeIdHashId]));

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response contains the court type
    $response->assertJsonCount(1, 'data.courts');

});


test('user can update a court type', function () {
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

    // Update the court type
    $courtTypeData = CourtTypeFactory::new()->make([
        'tenant_id' => $tenant->id,
    ]);

    // Update the court type route
    $response = $this->putJson(
        route('court-types.update',
        ['tenant_id' => $tenantHashId, 'court_type_id' => $courtTypeIdHashId]),
        $courtTypeData->toArray()
    );

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response contains the updated court type
    $data = $courtTypeData->toArray();
    $data['tenant_id'] = EasyHashAction::encode($tenant->id, 'tenant-id');

    $response->assertJson([
        'data' => $data
    ]);

    // Assert the court type has been created
    // Use $data but fix tenant_id to be the raw integer in the DB
    $data['tenant_id'] = $tenant->id;
    $this->assertDatabaseHas('courts_type', $data);
});

test('user can create a court type', function () {
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
    $courtTypeData = CourtTypeFactory::new()->make([
        'tenant_id' => $tenant->id,
    ]);

    $data = $courtTypeData->toArray();
    // Create the court type route
    $response = $this->postJson(route('court-types.create', ['tenant_id' => $tenantHashId]), $data);


    // Assert the response is successful
    $response->assertStatus(201);

    // Assert the response contains the created court type
    $responseData = $courtTypeData->toArray();
    $responseData['tenant_id'] = EasyHashAction::encode($tenant->id, 'tenant-id');


    $response->assertJson([
        'data' => $responseData
    ]);

    // Assert the court type has been created
    // Use $data but fix tenant_id to be the raw integer in the DB
    $dbData = $courtTypeData->toArray();
    $dbData['tenant_id'] = $tenant->id;
    $this->assertDatabaseHas('courts_type', $dbData);
});


test('user cannot delete a court type if it has courts associated', function () {
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

    // Create a court type for this tenant
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Create a court for this court type
    Court::factory()->create([
        'court_type_id' => $courtType->id,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Encode the court type ID
    $courtTypeIdHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    // Delete the court type
    $response = $this->deleteJson(
        route('court-types.destroy',
        ['tenant_id' => $tenantHashId, 'court_type_id' => $courtTypeIdHashId])
    );

    // Assert the response is successful
    $response->assertStatus(400);
});



test('user can delete a court type if no one courts associated', function () {
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

    // Create a court type for this tenant
    $courtType = CourtType::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Encode the court type ID
    $courtTypeIdHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    // Delete the court type
    $response = $this->deleteJson(
        route('court-types.destroy',
        ['tenant_id' => $tenantHashId, 'court_type_id' => $courtTypeIdHashId])
    );

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the court type has been soft deleted
    $this->assertSoftDeleted('courts_type', [
        'id' => $courtType->id,
    ]);
});

test('court type validation fails if interval or buffer is not multiple of 5', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $data = CourtTypeFactory::new()->make([
        'tenant_id' => $tenant->id,
        'interval_time_minutes' => 7, // Invalid
        'buffer_time_minutes' => 3, // Invalid
    ])->toArray();

    $response = $this->postJson(route('court-types.create', ['tenant_id' => $tenantHashId]), $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['interval_time_minutes', 'buffer_time_minutes']);
});



