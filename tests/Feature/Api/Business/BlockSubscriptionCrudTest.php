<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Invoice;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('get requests allowed with expired subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create an expired invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->subDay(),
        'max_courts' => 10,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    // GET request should be allowed (200 OK)
    $response = $this->getJson(route('tenant.show', ['tenant_id' => $tenantHashId]));
    $response->assertStatus(200);
});

test('post requests blocked with expired subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create an expired invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->subDay(),
        'max_courts' => 10,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    // POST request should be blocked (403 Forbidden)
    // Using court creation as an example
    $response = $this->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
        'name' => 'Court 1',
        'number' => 1,
        // other required fields...
    ]);

    $response->assertStatus(403)
        ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
});

test('put requests blocked with expired subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create an expired invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->subDay(),
        'max_courts' => 10,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    
    // Create a court to try updating
    $court = \App\Models\Court::factory()->create(['tenant_id' => $tenant->id]);
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    Sanctum::actingAs($user, [], 'business');

    // PUT request should be blocked (403 Forbidden)
    $response = $this->putJson(route('courts.update', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
        'name' => 'Updated Court',
    ]);

    $response->assertStatus(403)
        ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
});

test('delete requests blocked with expired subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create an expired invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->subDay(),
        'max_courts' => 10,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    
    // Create a court to try deleting
    $court = \App\Models\Court::factory()->create(['tenant_id' => $tenant->id]);
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    Sanctum::actingAs($user, [], 'business');

    // DELETE request should be blocked (403 Forbidden)
    $response = $this->deleteJson(route('courts.destroy', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]));

    $response->assertStatus(403)
        ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
});

test('tenant profile update allowed with expired subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create an expired invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->subDay(),
        'max_courts' => 10,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    // PUT request to tenant.update should be allowed (200 OK)
    $timezone = \App\Models\Timezone::factory()->create();
    $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenantHashId]), [
        'name' => 'Updated Tenant Name',
        'country_id' => EasyHashAction::encode($tenant->country_id, 'country-id'),
        'address' => 'Test Address',
        'currency' => 'usd',
        'timezone_id' => EasyHashAction::encode($timezone->id, 'timezone-id'),
        'auto_confirm_bookings' => true,
        'booking_interval_minutes' => 60,
        'buffer_between_bookings_minutes' => 0,
    ]);

    $response->assertStatus(200);
});

test('crud allowed with valid subscription', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create a valid invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    // POST request should be allowed (200 OK or 201 Created)
    // We need a valid court type for court creation
    $courtType = \App\Models\CourtType::factory()->create(['tenant_id' => $tenant->id]);
    $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    $response = $this->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
        'name' => 'Court 1',
        'number' => 1,
        'court_type_id' => $courtTypeHashId,
    ]);

    $response->assertStatus(201);
});
