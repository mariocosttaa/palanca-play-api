<?php

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

test('user can like and unlike a court type', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    $user = createAuthenticatedUser($tenant);
    
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    
    $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');
    
    // Like
    $response = $this->postJson("/api/v1/court-types/{$courtTypeHashId}/like");
    
    $response->assertStatus(200)
        ->assertJsonFragment(['is_liked' => true, 'likes_count' => 1]);
    
    $this->assertDatabaseHas('court_type_user_likes', [
        'user_id' => $user->id,
        'court_type_id' => $courtType->id,
    ]);
    
    $this->assertEquals(1, $courtType->fresh()->likes_count);
    
    // Unlike
    $response = $this->postJson("/api/v1/court-types/{$courtTypeHashId}/like");
    
    $response->assertStatus(200)
        ->assertJsonFragment(['is_liked' => false, 'likes_count' => 0]);
        
    $this->assertDatabaseMissing('court_type_user_likes', [
        'user_id' => $user->id,
        'court_type_id' => $courtType->id,
    ]);
    
    $this->assertEquals(0, $courtType->fresh()->likes_count);
});

test('popular court types are returned in correct order', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    createAuthenticatedUser($tenant);
    
    $ct1 = CourtType::factory()->create(['tenant_id' => $tenant->id, 'likes_count' => 10, 'name' => 'Popular']);
    $ct2 = CourtType::factory()->create(['tenant_id' => $tenant->id, 'likes_count' => 50, 'name' => 'Most Popular']);
    $ct3 = CourtType::factory()->create(['tenant_id' => $tenant->id, 'likes_count' => 5, 'name' => 'Least Popular']);
    
    $response = $this->getJson("/api/v1/court-types/popular");
    
    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Most Popular')
        ->assertJsonPath('data.1.name', 'Popular')
        ->assertJsonPath('data.2.name', 'Least Popular');
});

test('court type resource includes is_liked and likes_count', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    $user = createAuthenticatedUser($tenant);
    
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id, 'likes_count' => 5]);
    $user->likedCourtTypes()->attach($courtType->id);
    
    $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');
    
    $response = $this->getJson("/api/v1/court-types/{$courtTypeHashId}");
    
    $response->assertStatus(200)
        ->assertJsonPath('data.likes_count', 5)
        ->assertJsonPath('data.is_liked', true);
});

test('unauthenticated user cannot like a court type', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    $courtType = CourtType::factory()->create(['tenant_id' => $tenant->id]);
    
    $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');
    
    $response = $this->postJson("/api/v1/court-types/{$courtTypeHashId}/like");
    
    $response->assertStatus(401);
});

test('popular court types endpoint is paginated', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    createAuthenticatedUser($tenant);
    
    CourtType::factory()->count(20)->create(['tenant_id' => $tenant->id]);
    
    $response = $this->getJson("/api/v1/court-types/popular");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'links',
            'meta'
        ])
        ->assertJsonCount(5, 'data');
});

test('can filter court types by country', function () {
    /** @var TestCase $this */
    $country1 = Country::factory()->create();
    $country2 = Country::factory()->create();
    
    $tenant1 = Tenant::factory()->create(['country_id' => $country1->id]);
    $tenant2 = Tenant::factory()->create(['country_id' => $country2->id]);
    
    CourtType::factory()->create(['tenant_id' => $tenant1->id, 'name' => 'Country 1 Court']);
    CourtType::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'Country 2 Court']);
    
    createAuthenticatedUser();
    
    $country1HashId = EasyHashAction::encode($country1->id, 'country-id');
    
    $response = $this->getJson("/api/v1/court-types?country_id={$country1HashId}");
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Country 1 Court');
});

test('can search court types by name', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    CourtType::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Specific Name']);
    CourtType::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Other Name']);
    
    createAuthenticatedUser();
    
    $response = $this->getJson("/api/v1/court-types?search=Specific");
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Specific Name');
});

test('can filter court types by modality', function () {
    /** @var TestCase $this */
    $tenant = Tenant::factory()->create();
    CourtType::factory()->create(['tenant_id' => $tenant->id, 'type' => \App\Enums\CourtTypeEnum::PADEL, 'name' => 'Padel Court']);
    CourtType::factory()->create(['tenant_id' => $tenant->id, 'type' => \App\Enums\CourtTypeEnum::TENNIS, 'name' => 'Tennis Court']);
    
    createAuthenticatedUser();
    
    $response = $this->getJson("/api/v1/court-types?modality=padel");
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Padel Court');
});
