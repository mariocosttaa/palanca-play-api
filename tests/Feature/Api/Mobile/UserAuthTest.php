<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('user can register and receive verification code', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    \App\Models\Country::factory()->create(['calling_code' => '+1']);

    $email = 'john.doe@example.com';

    // Step 1: Register
    $response = $this->postJson('/api/v1/users/register', [
        'name' => 'John Doe',
        'surname' => 'Doe',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Test Device',
        'calling_code' => '+1',
        'phone' => '123456789',
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('data.token')
            ->has('data.verification_needed')
            ->has('data.user', fn ($user) => $user
                ->has('id')
                ->where('name', 'John Doe')
                ->where('email', $email)
                ->etc()
            )
        );

    // Verify user created but not verified
    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->toBeNull();

    // Verify email sent
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailVerificationCode::class);
});

test('user can verify email with valid code', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    \App\Models\Country::factory()->create(['calling_code' => '+1']);
    $email = 'john.doe@example.com';

    // Register
    $registerResponse = $this->postJson('/api/v1/users/register', [
        'name' => 'John Doe',
        'surname' => 'Doe',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Test Device',
        'calling_code' => '+1',
        'phone' => '123456789',
    ]);

    $token = $registerResponse->json('data.token');
    $code = \App\Models\EmailSent::where('user_email', $email)->first()->code;

    // Verify
    $response = $this->postJson('/api/v1/users/verification/verify', [
        'code' => $code,
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Email verified successfully.']);

    $user = User::where('email', $email)->first();
    expect($user->email_verified_at)->not->toBeNull();
});

test('user cannot verify email with invalid code', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/users/verification/verify', [
        'code' => '000000',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Invalid or expired verification code.']);
});

test('user can resend verification code', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    $user = User::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/users/verification/resend', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Verification code sent successfully.']);

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailVerificationCode::class);
});

test('resend verification code is rate limited', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    $user = User::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    // First send
    $this->postJson('/api/v1/users/verification/resend', [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(200);

    // Immediate second send
    $response = $this->postJson('/api/v1/users/verification/resend', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(429);
});

test('user can check verification status', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    // Check status (unverified)
    $response = $this->getJson('/api/v1/users/verification/status', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['verified' => false]);

    // Manually verify
    $user->markEmailAsVerified();

    // Clear auth cache to ensure fresh user is loaded
    \Illuminate\Support\Facades\Auth::forgetGuards();

    // Check status (verified)
    $response = $this->getJson('/api/v1/users/verification/status', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['verified' => true]);
});

test('user cannot register with invalid email', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users/register', [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user cannot register with duplicate email', function () {
    /** @var TestCase $this */
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/v1/users/register', [
        'name' => 'John Doe',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

