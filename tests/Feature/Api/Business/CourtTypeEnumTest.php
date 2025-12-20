<?php

use App\Enums\CourtTypeEnum;
use App\Models\BusinessUser;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Actions\General\EasyHashAction;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can get court type enums', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $tenant->businessUsers()->attach($businessUser);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $response = $this->actingAs($businessUser, 'business')
        ->getJson("/api/business/v1/business/{$tenantHashId}/court-types/modalities");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['value', 'label']
            ]
        ])
        ->assertJsonCount(7, 'data');
});

test('cannot create court type with invalid enum', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $tenant->businessUsers()->attach($businessUser);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

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
});
