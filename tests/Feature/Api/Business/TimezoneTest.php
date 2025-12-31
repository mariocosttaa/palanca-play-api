<?php

use App\Models\BusinessUser;
use Database\Seeders\Default\TimezoneSeeder;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can list timezones', function () {
    $this->seed(TimezoneSeeder::class);

    $businessUser = BusinessUser::factory()->create();
    Sanctum::actingAs($businessUser, [], 'business');

    $response = $this->getJson(route('timezones.index'));

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
