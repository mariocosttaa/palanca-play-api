<?php

namespace Tests\Feature\Api\Profile;

use App\Models\User;
use App\Models\BusinessUser;
use App\Models\EmailSent;
use App\Enums\EmailTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_email_update_and_receives_verification_code()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $newEmail = 'newemail@example.com';

        $response = $this->actingAs($user)
            ->putJson('/api/v1/profile/email', [
                'email' => $newEmail,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $newEmail)
            ->assertJsonPath('message', 'Código de verificação enviado para o novo email.');

        // User email should NOT be updated yet
        $user->refresh();
        $this->assertNotEquals($newEmail, $user->email);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('emails_sent', [
            'user_email' => $newEmail,
            'type' => EmailTypeEnum::CONFIRMATION_EMAIL->value,
        ]);
    }

    public function test_user_can_verify_and_finalize_email_update()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $newEmail = 'newemail@example.com';
        $oldEmail = $user->email;

        // 1. Request update
        $this->actingAs($user)
            ->putJson('/api/v1/profile/email', ['email' => $newEmail]);

        $emailSent = EmailSent::where('user_email', $newEmail)->latest()->first();

        // 2. Verify update
        $response = $this->actingAs($user)
            ->postJson('/api/v1/profile/email/verify', [
                'email' => $newEmail,
                'code' => $emailSent->code,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $newEmail)
            ->assertJsonPath('message', 'Email atualizado e verificado com sucesso.');

        $user->refresh();
        $this->assertEquals($newEmail, $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_business_user_can_request_email_update_and_receives_verification_code()
    {
        $user = BusinessUser::factory()->create(['email_verified_at' => now()]);
        $newEmail = 'newbusiness@example.com';

        $response = $this->actingAs($user, 'business')
            ->putJson('/api/business/v1/profile/email', [
                'email' => $newEmail,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $newEmail)
            ->assertJsonPath('message', 'Código de verificação enviado para o novo email.');

        $user->refresh();
        $this->assertNotEquals($newEmail, $user->email);

        $this->assertDatabaseHas('emails_sent', [
            'user_email' => $newEmail,
            'type' => EmailTypeEnum::CONFIRMATION_EMAIL->value,
        ]);
    }

    public function test_business_user_can_verify_and_finalize_email_update()
    {
        $user = BusinessUser::factory()->create(['email_verified_at' => now()]);
        $newEmail = 'newbusiness@example.com';

        // 1. Request update
        $this->actingAs($user, 'business')
            ->putJson('/api/business/v1/profile/email', ['email' => $newEmail]);

        $emailSent = EmailSent::where('user_email', $newEmail)->latest()->first();

        // 2. Verify update
        $response = $this->actingAs($user, 'business')
            ->postJson('/api/business/v1/profile/email/verify', [
                'email' => $newEmail,
                'code' => $emailSent->code,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $newEmail)
            ->assertJsonPath('message', 'Email atualizado e verificado com sucesso.');

        $user->refresh();
        $this->assertEquals($newEmail, $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_update_rate_limiting()
    {
        $user = User::factory()->create();
        $newEmail = 'limit@example.com';

        // Send 3 emails (burst limit)
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)
                ->putJson('/api/v1/profile/email', ['email' => $newEmail]);
        }

        // 4th email should be rate limited
        $response = $this->actingAs($user)
            ->putJson('/api/v1/profile/email', ['email' => $newEmail]);

        $response->assertStatus(429);
        $this->assertStringContainsString('Please wait', $response->json('message'));
    }

    public function test_email_update_validation_unique()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $response = $this->actingAs($user1)
            ->putJson('/api/v1/profile/email', [
                'email' => 'user2@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
