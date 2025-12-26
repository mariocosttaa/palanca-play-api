<?php

use App\Actions\General\EasyHashAction;
use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Enums\BookingStatusEnum;

uses(RefreshDatabase::class);

test('can list clients', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    
    $clients = User::factory()->count(3)->create();
    // Link clients to the tenant
    foreach ($clients as $client) {
        $client->tenants()->attach($tenant);
    }

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.index', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200);
    $this->assertCount(3, $response->json('data'));
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
    
    // Verify user is linked to tenant
    $this->assertDatabaseHas('user_tenants', [
        'tenant_id' => $tenant->id,
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
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'status' => BookingStatusEnum::PENDING]);
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'status' => BookingStatusEnum::CANCELLED]);
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'status' => BookingStatusEnum::CONFIRMED, 'present' => false]);
    Booking::factory()->create(['user_id' => $client->id, 'tenant_id' => $tenant->id, 'status' => BookingStatusEnum::CONFIRMED, 'present' => true]);

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

    // Create clients with specific names and link to tenant
    $client1 = User::factory()->create(['name' => 'John', 'surname' => 'Doe']);
    $client2 = User::factory()->create(['name' => 'Jane', 'surname' => 'Smith']);
    $client3 = User::factory()->create(['name' => 'Bob', 'surname' => 'Johnson']);
    
    $client1->tenants()->attach($tenant);
    $client2->tenants()->attach($tenant);
    $client3->tenants()->attach($tenant);

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

    // Create clients with specific emails and link to tenant
    $client1 = User::factory()->create(['email' => 'alice@example.com']);
    $client2 = User::factory()->create(['email' => 'bob@test.com']);
    $client3 = User::factory()->create(['email' => 'charlie@example.com']);
    
    $client1->tenants()->attach($tenant);
    $client2->tenants()->attach($tenant);
    $client3->tenants()->attach($tenant);

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

    // Create clients with specific phone numbers and link to tenant
    $client1 = User::factory()->create(['phone' => '123456789']);
    $client2 = User::factory()->create(['phone' => '987654321']);
    $client3 = User::factory()->create(['phone' => '123999888']);
    
    $client1->tenants()->attach($tenant);
    $client2->tenants()->attach($tenant);
    $client3->tenants()->attach($tenant);

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

    $client = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $client->tenants()->attach($tenant);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('clients.index', ['tenant_id' => $tenantHashId, 'search' => 'nonexistent']));

    $response->assertStatus(200);
    $this->assertCount(0, $response->json('data'));
});

test('can update client with same email', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $client = User::factory()->create(['email' => 'test@example.com', 'is_app_user' => false]);
    $client->tenants()->attach($tenant);
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('clients.update', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]), [
        'email' => 'test@example.com',
        'name' => 'Updated Name',
    ]);

    $response->assertStatus(200);
});

test('cannot update client with existing email', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);

    $existingClient = User::factory()->create(['email' => 'existing@example.com']);
    $client = User::factory()->create(['email' => 'test@example.com', 'is_app_user' => false]);
    $client->tenants()->attach($tenant);
    
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('clients.update', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]), [
        'email' => 'existing@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('can create client with calling code and phone', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    
    $country = \App\Models\Country::factory()->create(['calling_code' => '351']);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->postJson(route('clients.store', ['tenant_id' => $tenantHashId]), [
        'name' => 'Test Client',
        'email' => 'testclient@example.com',
        'calling_code' => '351',
        'phone' => '912345678',
        'country_id' => $country->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment([
            'name' => 'Test Client', 
            'email' => 'testclient@example.com',
            'calling_code' => '351',
            'phone' => '912345678',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'testclient@example.com',
        'calling_code' => '351',
        'phone' => '912345678',
    ]);
});

test('can update client with calling code and phone', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    Invoice::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid', 'date_end' => now()->addMonth()]);
    
    $country = \App\Models\Country::factory()->create(['calling_code' => '351']);

    $client = User::factory()->create(['is_app_user' => false]);
    $client->tenants()->attach($tenant);
    $clientHashId = EasyHashAction::encode($client->id, 'user-id');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->putJson(route('clients.update', ['tenant_id' => $tenantHashId, 'client_id' => $clientHashId]), [
        'calling_code' => '351',
        'phone' => '987654321',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment([
            'calling_code' => '351',
            'phone' => '987654321',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $client->id,
        'calling_code' => '351',
        'phone' => '987654321',
    ]);
});

