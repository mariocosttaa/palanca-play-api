<?php

use App\Enums\CourtTypeEnum;
use App\Models\Tenant;
use App\Actions\General\EasyHashAction;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can get court type enums with auth and verification', function () {
    // Create a verified user
    $user = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $response = $this->getJson("/api/v1/court-types/modalities");

    $response->assertStatus(200)
        ->assertJson([
            'data' => CourtTypeEnum::options()
        ]);
});
