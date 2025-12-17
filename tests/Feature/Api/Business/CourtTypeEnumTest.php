<?php

namespace Tests\Feature\Api\Business;

use App\Enums\CourtTypeEnum;
use App\Models\BusinessUser;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtTypeEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_court_type_enums()
    {
        $tenant = Tenant::factory()->create();
        $businessUser = BusinessUser::factory()->create();
        $tenant->businessUsers()->attach($businessUser);

        $tenantHashId = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');

        $response = $this->actingAs($businessUser, 'business')
            ->getJson("/api/business/v1/business/{$tenantHashId}/court-types/enums/types");

        $response->assertStatus(200)
            ->assertJson([
                'data' => CourtTypeEnum::values()
            ]);
    }

    public function test_cannot_create_court_type_with_invalid_enum()
    {
        $tenant = Tenant::factory()->create();
        $businessUser = BusinessUser::factory()->create();
        $tenant->businessUsers()->attach($businessUser);

        // Create a valid invoice for the tenant
        \App\Models\Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->addDay(),
            'max_courts' => 10,
        ]);

        $tenantHashId = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');

        $response = $this->actingAs($businessUser, 'business')
            ->postJson("/api/business/v1/business/{$tenantHashId}/court-types", [
                'name' => 'Invalid Court Type',
                'type' => 'invalid_type',
                'interval_time_minutes' => 60,
                'buffer_time_minutes' => 0,
                'price_per_interval' => 100,
                'status' => true,
                'availabilities' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }
}
