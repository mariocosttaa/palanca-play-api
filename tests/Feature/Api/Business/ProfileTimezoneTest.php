<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Timezone;
use Database\Seeders\Default\TimezoneSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TimezoneSeeder::class);
});

test('business user can update timezone', function () {
    $user = BusinessUser::factory()->create();
    $timezone = Timezone::where('name', 'Europe/Madrid')->first();
    $encodedTimezoneId = EasyHashAction::encode($timezone->id, 'timezone-id');

    $response = $this->actingAs($user, 'business')
        ->putJson('/api/business/v1/profile/timezone', [
            'timezone_id' => $encodedTimezoneId,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'timezone' => 'Europe/Madrid',
                'message' => 'Fuso horário atualizado com sucesso',
            ],
        ]);

    $this->assertDatabaseHas('business_users', [
        'id' => $user->id,
        'timezone_id' => $timezone->id,
    ]);
});

test('business user cannot update timezone with invalid id', function () {
    $user = BusinessUser::factory()->create();

    $response = $this->actingAs($user, 'business')
        ->putJson('/api/business/v1/profile/timezone', [
            'timezone_id' => 'invalid-hash',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['timezone_id']);
});
