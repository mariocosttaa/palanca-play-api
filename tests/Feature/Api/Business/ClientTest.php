<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
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
        ->assertJsonFragment(['id' => $clientHashId, 'name' => $client->name]);
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

test('can get client stats', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $client = User::factory()->create();
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create bookings
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'is_pending' => true]);
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'is_cancelled' => true, 'is_pending' => false]);
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'is_pending' => false, 'is_cancelled' => false, 'present' => false]);
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'is_pending' => false, 'is_cancelled' => false, 'present' => true]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.stats', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'total' => 4,
                'pending' => 1,
                'cancelled' => 1,
                'not_present' => 1,
            ]
        ]);
});

test('can get client bookings', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $client = User::factory()->create();
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Booking::factory()->count(3)->create(['user_id' => $client->id, 'tenant_id' => $tenant->id]);

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.bookings', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]));

    $response->assertStatus(200);
    $this->assertCount(3, $response->json('data'));
});

test('can search clients by name', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    // Create clients with specific names
    User::factory()->create(['name' => 'John', 'surname' => 'Doe']);
    User::factory()->create(['name' => 'Jane', 'surname' => 'Smith']);
    User::factory()->create(['name' => 'Bob', 'surname' => 'Johnson']);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.index', ['tenant_id' => $tenantHashId, 'search' => 'John']));

    $response->assertStatus(200);
    $data = $response->json('data');
    $this->assertGreaterThanOrEqual(1, count($data));
    
    // Verify results contain "John"
    $names = collect($data)->pluck('name')->toArray();
    $this->assertTrue(in_array('John', $names) || in_array('Bob', $names)); // Should match "John" or "Johnson"
});

test('can search clients by email', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    // Create clients with specific emails
    User::factory()->create(['email' => 'alice@example.com']);
    User::factory()->create(['email' => 'bob@test.com']);
    User::factory()->create(['email' => 'charlie@example.com']);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.index', ['tenant_id' => $tenantHashId, 'search' => 'example.com']));

    $response->assertStatus(200);
    $data = $response->json('data');
    $this->assertGreaterThanOrEqual(2, count($data));
});

test('can search clients by phone', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    // Create clients with specific phone numbers
    User::factory()->create(['phone' => '123456789']);
    User::factory()->create(['phone' => '987654321']);
    User::factory()->create(['phone' => '123999888']);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.index', ['tenant_id' => $tenantHashId, 'search' => '123']));

    $response->assertStatus(200);
    $data = $response->json('data');
    $this->assertGreaterThanOrEqual(2, count($data));
});

test('search returns no results when no match found', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.index', ['tenant_id' => $tenantHashId, 'search' => 'nonexistent']));

    $response->assertStatus(200);
    $this->assertCount(0, $response->json('data'));
});

