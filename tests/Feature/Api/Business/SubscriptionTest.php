<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('business user can get invoices', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create invoices
    Invoice::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('subscriptions.invoices', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'period',
                    'date_start',
                    'date_end',
                    'price',
                    'price_formatted',
                    'currency',
                    'max_courts',
                    'status',
                    'created_at',
                ]
            ]
        ]);
});

test('business user can get current subscription details active', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create active invoice
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDays(30),
        'max_courts' => 5,
    ]);

    // Create some courts
    Court::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('subscriptions.current', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'status' => 'active',
                'max_courts' => 5,
                'current_courts' => 2,
                'days_remaining' => 29, // approx
            ]
        ]);
});

test('business user can get current subscription details expired', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // Create expired invoice
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->subDay(),
        'max_courts' => 5,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('subscriptions.current', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'status' => 'expired',
                'days_remaining' => 0,
            ]
        ]);
});

test('business user can get current subscription details none', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);
    
    // No invoices

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    Sanctum::actingAs($user, [], 'business');

    $response = $this->getJson(route('subscriptions.current', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'status' => 'none',
                'max_courts' => 0,
                'invoice' => null,
            ]
        ]);
});
