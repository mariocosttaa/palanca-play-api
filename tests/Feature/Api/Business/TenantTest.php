<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('business user can get all tenants they are attached to', function () {
    /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    //LOGIC GOES HERE

    $response = $this->getJson(route('tenant.index', ['tenant_id' => $tenantHashId]));
    $response->assertStatus(200);
    $response->assertJson(fn ($json) => $json
        ->has('data', 1)
        ->has('data.0.id')
        ->has('data.0.name')
    );
});

test('business user cannot get tenants they are not attached to', function () {
       /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    //LOGIC GOES HERE

    $response = $this->getJson(route('tenant.index', ['tenant_id' => $tenantHashId]));
    $response->assertStatus(200);
    $response->assertJson(fn ($json) => $json
        ->has('data')
        ->has('data.0.id')
        ->has('data.0.name')
    );

     //check only returned tenant they have access to
     $tenatVerified = [];
    foreach ($response->json('data') as $tenantData) {
       $tenantId = EasyHashAction::decode($tenantData['id'], 'tenant-id');

       $tenant = Tenant::with('businessUsers')->find($tenantId);
       if(!$tenant->businessUsers->contains($businessUser)) {
         $tenatVerified[] = false;
       }

       $tenatVerified[] = true;
    }

    if (in_array(false, $tenatVerified)) {
        $this->fail('Are showing tenants that the business user does not have access to');
    }

    $this->assertTrue(true);
});

test('business user can get a tenant details', function () {
       /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    //LOGIC GOES HERE

    $response = $this->getJson(route('tenant.show', ['tenant_id' => $tenantHashId]));

    $response->assertStatus(200);
    $response->assertJson(fn ($json) => $json
        ->has('data')
        ->has('data.id')
        ->has('data.name')
    );
});


test('business user can update a tenant details', function () {
       /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Logic goes here
    $tenantUpdateData = Tenant::factory()->make()->toArray();

    $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenantHashId]), $tenantUpdateData);

    $response->assertStatus(200);
    $response->assertJson(fn ($json) => $json
        ->has('data')
        ->has('data.id')
        ->has('data.name')
    );

    $data = $tenantUpdateData;
    $data['id'] = $tenant->id;
    $this->assertDatabaseHas('tenants', $data);
});

test('business user cannot update a tenant details they are not attached to', function () {
       /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');
    $tenant2HashId = EasyHashAction::encode($tenant2->id, 'tenant-id');

    // Logic goes here
    $tenantUpdateData = Tenant::factory()->make()->toArray();
    $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenant2HashId]), $tenantUpdateData);

    $response->assertStatus(500);
    $response->assertJson(fn ($json) => $json
        ->has('message')
    );
});
