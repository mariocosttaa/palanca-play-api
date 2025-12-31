<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\CourtType;
use App\Models\Invoice;
use App\Models\Tenant;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('access allowed for get without invoice', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // GET request should be allowed now
    $this->actingAs($user, 'business')
        ->getJson(route('tenant.show', ['tenant_id' => $tenantHashId]))
        ->assertStatus(200);
        
    // POST request should be blocked
    $this->actingAs($user, 'business')
            ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [])
            ->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
});

test('access allowed for get with expired invoice', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->subDay(),
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // GET request should be allowed now
    $this->actingAs($user, 'business')
        ->getJson(route('tenant.show', ['tenant_id' => $tenantHashId]))
        ->assertStatus(200);
        
    // POST request should be blocked
    $this->actingAs($user, 'business')
            ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [])
            ->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
});

test('access granted with valid invoice', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $response = $this->actingAs($user, 'business')
        ->getJson(route('tenant.show', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200);
});

test('court creation limit enforced by invoice', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 1,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

    // Create 1st court (allowed)
    $this->actingAs($user, 'business')
        ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
            'name' => 'Court 1',
            'number' => 1,
            'court_type_id' => $courtTypeHashId,
        ])->assertStatus(201);

    // Create 2nd court (denied)
    $this->actingAs($user, 'business')
        ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
            'name' => 'Court 2',
            'number' => 2,
            'court_type_id' => $courtTypeHashId,
        ])->assertStatus(403)
        ->assertJson(['message' => 'Court limit reached for your subscription plan.']);
});
