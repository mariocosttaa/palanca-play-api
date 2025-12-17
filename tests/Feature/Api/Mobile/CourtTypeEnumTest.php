<?php

namespace Tests\Feature\Api\Mobile;

use App\Enums\CourtTypeEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtTypeEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_court_type_enums()
    {
        $tenant = Tenant::factory()->create();
        $tenantHashId = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');

        $response = $this->getJson("/api/v1/tenants/{$tenantHashId}/court-types/modalities");

        $response->assertStatus(200)
            ->assertJson([
                'data' => CourtTypeEnum::values()
            ]);
    }
}
