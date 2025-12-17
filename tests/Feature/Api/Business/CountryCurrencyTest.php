<?php

use App\Models\BusinessUser;
use App\Models\Country;
use App\Models\Manager\CurrencyModel;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('can get all countries from business api', function () {
    $businessUser = BusinessUser::factory()->create();
    
    // Create some countries
    Country::factory()->count(3)->create();

    $response = $this->actingAs($businessUser, 'business')
        ->getJson('/api/business/v1/countries');

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

test('can get all currencies from business api', function () {
    $businessUser = BusinessUser::factory()->create();
    
    // Create some currencies
    CurrencyModel::factory()->count(3)->create();

    $response = $this->actingAs($businessUser, 'business')
        ->getJson('/api/business/v1/currencies');

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
