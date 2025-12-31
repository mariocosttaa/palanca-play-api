<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Country;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('business user can update tenant country', function () {
    /** @var TestCase $this */
    
    // Create two countries
    $country1 = Country::factory()->create(['name' => 'Angola']);
    $country2 = Country::factory()->create(['name' => 'Portugal']);
    
    // Create a tenant with country1
    $tenant = Tenant::factory()->create([
        'country_id' => $country1->id,
    ]);
    
    // Create business user and attach to tenant
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);
    
    // Act as the business user
    Sanctum::actingAs($businessUser, [], 'business');
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    
    // Prepare update data with new country
    $timezone = \App\Models\Timezone::factory()->create();
    $updateData = [
        'name' => $tenant->name,
        'address' => $tenant->address,
        'latitude' => $tenant->latitude,
        'longitude' => $tenant->longitude,
        'currency' => $tenant->currency,
        'country_id' => EasyHashAction::encode($country2->id, 'country-id'), // Change to country2
        'timezone_id' => EasyHashAction::encode($timezone->id, 'timezone-id'),
        'auto_confirm_bookings' => $tenant->auto_confirm_bookings,
        'booking_interval_minutes' => $tenant->booking_interval_minutes,
        'buffer_between_bookings_minutes' => $tenant->buffer_between_bookings_minutes,
    ];
    
    // Make the update request
    $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenantHashId]), $updateData);
    
    // Assert response is successful
    $response->assertStatus(200);
    
    // Assert database was updated with new country_id
    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'country_id' => $country2->id, // Should be updated to country2
    ]);
    
    // Verify the old country is not in the database for this tenant
    $this->assertDatabaseMissing('tenants', [
        'id' => $tenant->id,
        'country_id' => $country1->id,
    ]);
    
    // Verify response contains the hashed country_id
    $response->assertJson(fn ($json) => $json
        ->has('data')
        ->has('data.country_id')
        ->where('data.country_id', EasyHashAction::encode($country2->id, 'country-id'))
    );
});
