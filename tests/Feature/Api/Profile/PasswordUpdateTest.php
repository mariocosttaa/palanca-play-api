<?php

namespace Tests\Feature\Api\Profile;

use App\Models\User;
use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_password()
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'old-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_user_cannot_update_password_with_wrong_current_password()
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'wrong-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_user_cannot_update_password_to_same_as_current()
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'old-password',
                'new_password' => 'old-password',
                'new_password_confirmation' => 'old-password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_business_user_can_update_password()
    {
        $user = BusinessUser::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user, 'business')
            ->putJson('/api/business/v1/profile/password', [
                'current_password' => 'old-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }
}
