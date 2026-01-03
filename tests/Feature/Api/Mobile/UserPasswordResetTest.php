<?php

use App\Models\User;
use App\Models\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('user can request password reset code', function () {
    /** @var TestCase $this */
    Mail::fake();
    $user = User::factory()->create(['email' => 'john.doe@example.com']);

    $response = $this->postJson('/api/v1/password/forgot', [
        'email' => 'john.doe@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Código de recuperação enviado para seu email']);

    $this->assertDatabaseHas('password_reset_codes', [
        'email' => 'john.doe@example.com',
    ]);

    Mail::assertSent(\App\Mail\PasswordResetCode::class);
});

test('user cannot request code for non-existent email', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/password/forgot', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user can verify code and reset password', function () {
    /** @var TestCase $this */
    $user = User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $code = '123456';
    PasswordResetCode::create([
        'email' => 'john.doe@example.com',
        'code' => $code,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/v1/password/verify', [
        'email' => 'john.doe@example.com',
        'code' => $code,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Senha redefinida com sucesso']);

    $user->refresh();
    $this->assertTrue(Hash::check('new-password123', $user->password));

    $this->assertNotNull(PasswordResetCode::where('code', $code)->first()->used_at);
});

test('user cannot reset password with invalid code', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'john.doe@example.com']);

    $response = $this->postJson('/api/v1/password/verify', [
        'email' => 'john.doe@example.com',
        'code' => '000000',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['message' => 'Código inválido ou expirado']);
});

test('user can check if code is valid', function () {
    /** @var TestCase $this */
    $code = '123456';
    PasswordResetCode::create([
        'email' => 'john.doe@example.com',
        'code' => $code,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->getJson("/api/v1/password/verify/{$code}");

    $response->assertStatus(200)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.email', 'john.doe@example.com');
});

test('user is rate limited when requesting password reset code too many times', function () {
    /** @var TestCase $this */
    Mail::fake();
    $user = User::factory()->create(['email' => 'john.doe@example.com']);

    // Request 3 times (burst limit)
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/password/forgot', ['email' => 'john.doe@example.com'])
            ->assertStatus(200);
    }

    // 4th request should be rate limited
    $response = $this->postJson('/api/v1/password/forgot', ['email' => 'john.doe@example.com']);

    $response->assertStatus(429)
        ->assertJsonStructure(['message']);
    
    $this->assertStringContainsString('Please wait', $response->json('message'));
});
