<?php

use Database\Seeders\Default\TimezoneSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can list timezones with auth and verification', function () {
    // Create a verified user
    $user = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->seed(TimezoneSeeder::class);

    $response = $this->getJson('/api/v1/timezones');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'label',
                    'offset',
                ],
            ],
        ]);
});

test('guest can list timezones', function () {
    $this->seed(TimezoneSeeder::class);

    $response = $this->getJson('/api/v1/timezones');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'label',
                    'offset',
                ],
            ],
        ]);
});
