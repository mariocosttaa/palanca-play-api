<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('can list clients', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    
    User::factory()->count(3)->create();

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.index', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200);
    $this->assertGreaterThanOrEqual(3, count($response->json('data')));
});

test('can show client', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $client = User::factory()->create();
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.show', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]));

    $response->assertStatus(200)
        ->assertJsonFragment(['id' => $client->id, 'name' => $client->name]);
});

test('can create client', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('clients.store', ['tenant_id' => $tenantHashId]), [
        'name' => 'Test Client',
        'email' => 'testclient@example.com',
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['name' => 'Test Client', 'email' => 'testclient@example.com', 'is_app_user' => false]);

    $this->assertDatabaseHas('users', [
        'email' => 'testclient@example.com',
        'is_app_user' => false,
    ]);
});

test('can update business created client', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $client = User::factory()->create(['is_app_user' => false]);
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('clients.update', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]), [
        'name' => 'Updated Name',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['name' => 'Updated Name']);

    $this->assertDatabaseHas('users', [
        'id' => $client->id,
        'name' => 'Updated Name',
    ]);
});

test('cannot update app user client', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $client = User::factory()->create(['is_app_user' => true]);
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('clients.update', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]), [
        'name' => 'Updated Name',
    ]);

    $response->assertStatus(403);

    $this->assertDatabaseHas('users', [
        'id' => $client->id,
        'name' => $client->name, // Should remain unchanged
    ]);
});
