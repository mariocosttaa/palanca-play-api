<?php

use App\Models\BusinessUser;
use App\Models\PasswordResetCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('business user can request password reset code', function () {
    /** @var TestCase $this */
    Mail::fake();
    $user = BusinessUser::factory()->create(['email' => 'jane.business@example.com']);

    $response = $this->postJson('/api/business/v1/business-users/password/forgot', [
        'email' => 'jane.business@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Código de recuperação enviado para seu email']);

    $this->assertDatabaseHas('password_reset_codes', [
        'email' => 'jane.business@example.com',
    ]);

    Mail::assertSent(\App\Mail\PasswordResetCode::class);
});

test('business user cannot request code for non-existent email', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/business/v1/business-users/password/forgot', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('business user can verify code and reset password', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create([
        'email' => 'jane.business@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $code = '123456';
    PasswordResetCode::create([
        'email' => 'jane.business@example.com',
        'code' => $code,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/business/v1/business-users/password/verify', [
        'email' => 'jane.business@example.com',
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

test('business user cannot reset password with invalid code', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email' => 'jane.business@example.com']);

    $response = $this->postJson('/api/business/v1/business-users/password/verify', [
        'email' => 'jane.business@example.com',
        'code' => '000000',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['message' => 'Código inválido ou expirado']);
});

test('business user can check if code is valid', function () {
    /** @var TestCase $this */
    $code = '123456';
    PasswordResetCode::create([
        'email' => 'jane.business@example.com',
        'code' => $code,
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->getJson("/api/business/v1/business-users/password/verify/{$code}");

    $response->assertStatus(200)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.email', 'jane.business@example.com');
});
