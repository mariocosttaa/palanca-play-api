<?php

use Database\Seeders\Default\TimezoneSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can list timezones publicly', function () {
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
