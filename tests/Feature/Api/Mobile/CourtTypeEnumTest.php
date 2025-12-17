<?php

use App\Enums\CourtTypeEnum;
use App\Models\Tenant;
use App\Actions\General\EasyHashAction;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can get court type enums', function () {
    $tenant = Tenant::factory()->create();
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $response = $this->getJson("/api/v1/tenants/{$tenantHashId}/court-types/modalities");

    $response->assertStatus(200)
        ->assertJson([
            'data' => CourtTypeEnum::values()
        ]);
});
