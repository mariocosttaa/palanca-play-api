<?php

use App\Actions\General\EasyHashAction;
use App\Models\User;
use App\Models\Timezone;
use Database\Seeders\Default\TimezoneSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TimezoneSeeder::class);
});

test('mobile user can_update_timezone', function () {
    $user = User::factory()->create();
    $timezone = Timezone::where('name', 'Asia/Tokyo')->first();
    $encodedTimezoneId = EasyHashAction::encode($timezone->id, 'timezone-id');

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile/timezone', [
            'timezone_id' => $encodedTimezoneId,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'timezone' => 'Asia/Tokyo',
                'message' => 'Fuso horário atualizado com sucesso',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'timezone_id' => $timezone->id,
    ]);
});

test('mobile user cannot update timezone with invalid id', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile/timezone', [
            'timezone_id' => 'invalid-hash',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['timezone_id']);
});
