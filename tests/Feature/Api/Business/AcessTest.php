<?php
use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('business user can access business endpoints', function () {
    /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);

    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');

    // Encode the tenant ID
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Get the court type
    $response = $this->getJson(route('tenant.show', ['tenant_id' => $tenantHashId]));

    // Assert the response is successful
    $response->assertStatus(200);

    // Assert the response contains the message
    $response->assertJson(fn ($json) => $json
        ->has('data')
        ->has('data.id')
        ->has('data.name')
    );
});

test('different business users cannot access each other\'s tenants', function () {
    /** @var TestCase $this */
    // Create a tenant and business user and attach the business user to the tenant
    $tenant = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $businessUser1 = BusinessUser::factory()->create(['email' => 'test-1@example.com']);
    $businessUser2 = BusinessUser::factory()->create(['email' => 'test-2@example.com']);
    $businessUser1->tenants()->attach($tenant->id);
    $businessUser2->tenants()->attach($tenant2->id);

    // Act as the business user 1
    Sanctum::actingAs($businessUser1, [], 'business');

    // Encode the tenant 2ID
    $tenantHashId2 = EasyHashAction::encode($tenant2->id, 'tenant-id');

    // Try to access the tenant as the second business user 2
    $response = $this->getJson(route('tenant.show', ['tenant_id' => $tenantHashId2]));

    // Assert the response is a 403 error
    $response->assertStatus(403);
});
