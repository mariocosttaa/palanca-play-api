<?php

namespace Tests\Feature\Api\V1\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_with_phone_code_and_phone()
    {
        $user = User::factory()->create();
        $country = \App\Models\Country::factory()->create();
        $timezone = \App\Models\Timezone::factory()->create();

        $countryHashId = \App\Actions\General\EasyHashAction::encode($country->id, 'country-id');
        $timezoneHashId = \App\Actions\General\EasyHashAction::encode($timezone->id, 'timezone-id');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'surname' => 'Updated Surname',
                'phone_code' => '+351',
                'phone' => '912345678',
                'country_id' => $countryHashId,
                'timezone_id' => $timezoneHashId,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.surname', 'Updated Surname')
            ->assertJsonPath('data.user.calling_code', '351') // Check normalization
            ->assertJsonPath('data.user.phone', '912345678')
            ->assertJsonPath('data.user.country_id', $country->id)
            ->assertJsonPath('data.user.timezone_id', $timezone->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'surname' => 'Updated Surname',
            'calling_code' => '351',
            'phone' => '912345678',
            'country_id' => $country->id,
            'timezone_id' => $timezone->id,
        ]);
    }

    public function test_user_can_update_profile_without_phone_code()
    {
        $user = User::factory()->create([
            'calling_code' => '1',
            'phone' => '5555555555',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'calling_code' => '1', // Should remain unchanged
            'phone' => '5555555555',
        ]);
    }

    public function test_user_can_update_profile_with_calling_code()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'calling_code' => '+244',
                'phone' => '912322',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.calling_code', '244')
            ->assertJsonPath('data.user.phone', '912322');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'calling_code' => '244',
            'phone' => '912322',
        ]);
    }
}
