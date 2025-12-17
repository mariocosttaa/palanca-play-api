<?php

use App\Models\Country;
use App\Models\Manager\CurrencyModel;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can get all countries from mobile api without auth', function () {
    // Create some countries
    Country::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/countries');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'code',
                    'calling_code',
                ]
            ]
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('can get all currencies from mobile api without auth', function () {
    // Create some currencies
    CurrencyModel::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/currencies');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'code',
                    'symbol',
                    'decimal_separator',
                ]
            ]
        ]);

    expect($response->json('data'))->toHaveCount(3);
});
