<?php

use App\Models\Court;
use App\Models\CourtType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Timezone;
use App\Models\Country;
use App\Actions\General\EasyHashAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('user can list courts globally', function () {
    /** @var TestCase $this */
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    Court::factory()->create(['tenant_id' => $tenant1->id, 'name' => 'Court 1']);
    Court::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'Court 2']);
    
    createAuthenticatedUser();
    
    $response = $this->getJson('/api/v1/courts');
    
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('user can filter courts by country', function () {
    /** @var TestCase $this */
    $country1 = Country::factory()->create();
    $country2 = Country::factory()->create();
    
    $tenant1 = Tenant::factory()->create(['country_id' => $country1->id]);
    $tenant2 = Tenant::factory()->create(['country_id' => $country2->id]);
    
    Court::factory()->create(['tenant_id' => $tenant1->id, 'name' => 'Country 1 Court']);
    Court::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'Country 2 Court']);
    
    createAuthenticatedUser();
    
    $country1HashId = EasyHashAction::encode($country1->id, 'country-id');
    
    $response = $this->getJson("/api/v1/courts?country_id={$country1HashId}");
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Country 1 Court');
});

test('user can search courts by name', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Specific Court']);
    Court::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Other Court']);
    
    createAuthenticatedUser();
    
    $response = $this->getJson('/api/v1/courts?search=Specific');
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Specific Court');
});

test('user can get court availability globally', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    createAuthenticatedUser();
    
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $startDate = now()->format('Y-m-d');
    $endDate = now()->addDays(7)->format('Y-m-d');
    
    $response = $this->getJson("/api/v1/courts/{$courtHashId}/availability/dates?start_date={$startDate}&end_date={$endDate}");
    
    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['dates', 'count']]);
});

test('user can get court slots globally', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    createAuthenticatedUser();
    
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $date = now()->format('Y-m-d');
    
    $response = $this->getJson("/api/v1/courts/{$courtHashId}/availability/{$date}/slots");
    
    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['date', 'slots', 'count']]);
});
