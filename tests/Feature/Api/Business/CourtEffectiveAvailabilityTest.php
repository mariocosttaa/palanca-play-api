<?php

namespace Tests\Feature\Api\Business;

use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtEffectiveAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $courtType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->courtType = CourtType::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_it_returns_court_specific_availabilities_when_they_exist()
    {
        $court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_type_id' => $this->courtType->id,
        ]);

        // Create court-specific availability
        $courtAvailability = CourtAvailability::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $court->id,
            'court_type_id' => null,
            'day_of_week_recurring' => 'monday',
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        // Create court type availability (should be ignored)
        CourtAvailability::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => null,
            'court_type_id' => $this->courtType->id,
            'day_of_week_recurring' => 'monday',
            'start_time' => '09:00',
            'end_time' => '22:00',
        ]);

        $effectiveAvailabilities = $court->getEffectiveAvailabilities();

        $this->assertCount(1, $effectiveAvailabilities);
        $this->assertEquals($courtAvailability->id, $effectiveAvailabilities->first()->id);
        $this->assertEquals('08:00', $effectiveAvailabilities->first()->start_time);
    }

    public function test_it_returns_court_type_availabilities_when_no_court_specific_exist()
    {
        $court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_type_id' => $this->courtType->id,
        ]);

        // Create only court type availability
        $courtTypeAvailability = CourtAvailability::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => null,
            'court_type_id' => $this->courtType->id,
            'day_of_week_recurring' => 'tuesday',
            'start_time' => '09:00',
            'end_time' => '22:00',
        ]);

        $effectiveAvailabilities = $court->getEffectiveAvailabilities();

        $this->assertCount(1, $effectiveAvailabilities);
        $this->assertEquals($courtTypeAvailability->id, $effectiveAvailabilities->first()->id);
        $this->assertEquals('09:00', $effectiveAvailabilities->first()->start_time);
    }

    public function test_it_returns_empty_collection_when_no_availabilities_exist()
    {
        $court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_type_id' => $this->courtType->id,
        ]);

        $effectiveAvailabilities = $court->getEffectiveAvailabilities();

        $this->assertCount(0, $effectiveAvailabilities);
        $this->assertTrue($effectiveAvailabilities->isEmpty());
    }

    public function test_it_returns_all_court_specific_availabilities_when_multiple_exist()
    {
        $court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_type_id' => $this->courtType->id,
        ]);

        // Create multiple court-specific availabilities
        CourtAvailability::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $court->id,
            'court_type_id' => null,
            'day_of_week_recurring' => 'monday',
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        CourtAvailability::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $court->id,
            'court_type_id' => null,
            'day_of_week_recurring' => 'tuesday',
            'start_time' => '09:00',
            'end_time' => '19:00',
        ]);

        $effectiveAvailabilities = $court->getEffectiveAvailabilities();

        $this->assertCount(2, $effectiveAvailabilities);
    }

    public function test_api_endpoint_returns_effective_availabilities()
    {
        $businessUser = \App\Models\BusinessUser::factory()->create();
        $tenant = Tenant::factory()->create();
        
        // Create the business_user_tenant relationship using DB table directly
        \DB::table('business_users_tenants')->insert([
            'tenant_id' => $tenant->id,
            'business_user_id' => $businessUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courtType = CourtType::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $court = Court::factory()->create([
            'tenant_id' => $tenant->id,
            'court_type_id' => $courtType->id,
        ]);

        // Create court type availability only
        CourtAvailability::factory()->create([
            'tenant_id' => $tenant->id,
            'court_id' => null,
            'court_type_id' => $courtType->id,
            'day_of_week_recurring' => 'wednesday',
            'start_time' => '10:00',
            'end_time' => '20:00',
        ]);

        $tenantHashId = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');
        $courtHashId = \App\Actions\General\EasyHashAction::encode($court->id, 'court-id');

        $response = $this->actingAs($businessUser, 'business')
            ->getJson("/api/business/v1/business/{$tenantHashId}/courts/{$courtHashId}/availabilities");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'day_of_week_recurring',
                        'start_time',
                        'end_time',
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('wednesday', $response->json('data.0.day_of_week_recurring'));
    }
}
