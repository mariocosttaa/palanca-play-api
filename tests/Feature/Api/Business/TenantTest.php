<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('business user can get all tenants they are attached to', function () {
    /** @var TestCase $this */
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
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    //LOGIC GOES HERE

    $response = $this->getJson(route('tenant.index', ['tenant_id' => $tenantHashId]));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
            ]
        ],
        'links',
        'meta'
    ]);
    $response->assertJsonCount(1, 'data');
});

test('business user cannot get tenants they are not attached to', function () {
       /** @var TestCase $this */
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
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    //LOGIC GOES HERE

    $response = $this->getJson(route('tenant.index', ['tenant_id' => $tenantHashId]));
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
            ]
        ],
        'links',
        'meta'
    ]);

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

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

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

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Logic goes here
    $timezone = \App\Models\Timezone::factory()->create();
    $tenantUpdateData = Tenant::factory()->make()->toArray();
    $tenantUpdateData['country_id'] = \App\Actions\General\EasyHashAction::encode($tenant->country_id, 'country-id');
    $tenantUpdateData['timezone_id'] = \App\Actions\General\EasyHashAction::encode($timezone->id, 'timezone-id');
    unset($tenantUpdateData['logo']);

    $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenantHashId]), $tenantUpdateData);

    $response->assertStatus(200);
    $response->assertJson(fn ($json) => $json
        ->has('data')
        ->has('data.id')
        ->has('data.name')
    );

    $data = $tenantUpdateData;
    $data['id'] = $tenant->id;
    $data['country_id'] = $tenant->country_id;
    $data['timezone_id'] = $timezone->id;
    unset($data['timezone']); // Timezone string is updated by controller based on timezone_id
    $this->assertDatabaseHas('tenants', $data);
});

test('business user cannot update a tenant details they are not attached to', function () {
       /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

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
    $tenant2HashId = EasyHashAction::encode($tenant2->id, 'tenant-id');

    // Logic goes here
    $timezone = \App\Models\Timezone::factory()->create();
    $tenantUpdateData = Tenant::factory()->make()->toArray();
    $tenantUpdateData['country_id'] = \App\Actions\General\EasyHashAction::encode($tenant->country_id, 'country-id');
    $tenantUpdateData['timezone_id'] = \App\Actions\General\EasyHashAction::encode($timezone->id, 'timezone-id');
    unset($tenantUpdateData['logo']);
    $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenant2HashId]), $tenantUpdateData);

    $response->assertStatus(403);
    $response->assertJson(fn ($json) => $json
        ->has('message')
        ->etc()
    );
});

test('business user can update tenant timezone', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $timezone = \App\Models\Timezone::factory()->create(['name' => 'Europe/London']);
    $timezoneHashId = EasyHashAction::encode($timezone->id, 'timezone-id');

    $tenantUpdateData = Tenant::factory()->make()->toArray();
    $tenantUpdateData['country_id'] = EasyHashAction::encode($tenant->country_id, 'country-id');
    $tenantUpdateData['timezone_id'] = $timezoneHashId;
    unset($tenantUpdateData['logo']);

    $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenantHashId]), $tenantUpdateData);

    $response->assertStatus(200);
    
    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'timezone_id' => $timezone->id,
        'timezone' => 'Europe/London',
    ]);
});
