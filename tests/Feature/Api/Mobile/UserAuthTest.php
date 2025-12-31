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
            ->has('token')
            ->has('verification_needed')
            ->has('message')
            ->has('user', fn ($user) => $user
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

    $token = $registerResponse->json('token');
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

    // Send 3 times (allowed)
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/users/verification/resend', [], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(200);
    }

    // 4th time (blocked)
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

test('unverified user cannot access protected routes', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    // Try to access notifications (protected by verified.api)
    $response = $this->getJson('/api/v1/notifications', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(403)
        ->assertJsonFragment(['message' => 'Your email address is not verified.']);
});

test('verified user can access protected routes', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email_verified_at' => now()]);
    $token = $user->createToken('test')->plainTextToken;

    // Try to access notifications
    $response = $this->getJson('/api/v1/notifications', [
        'Authorization' => "Bearer {$token}",
    ]);

    // Should be 200 OK (or empty list, but definitely not 403)
    $response->assertStatus(200);
});

test('unverified user can update profile', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email_verified_at' => null, 'name' => 'Original Name']);
    $token = $user->createToken('test')->plainTextToken;

    // Profile updates don't require email verification
    $response = $this->putJson('/api/v1/profile', [
        'name' => 'Updated Name',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.name', 'Updated Name');
    
    $user->refresh();
    expect($user->name)->toBe('Updated Name');
});

test('unverified user can update language preference', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email_verified_at' => null, 'locale' => 'en']);
    $token = $user->createToken('test')->plainTextToken;

    // Language updates don't require email verification
    $response = $this->patchJson('/api/v1/profile/language', [
        'locale' => 'pt',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.locale', 'pt');
    
    $user->refresh();
    expect($user->locale->value)->toBe('pt');
});

test('user can login with google', function () {
    /** @var TestCase $this */
    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getId')->andReturn('1234567890');
    $abstractUser->shouldReceive('getName')->andReturn('Google User');
    $abstractUser->shouldReceive('getEmail')->andReturn('google@example.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://en.gravatar.com/userimage');

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('stateless')->andReturn($provider);
    $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->postJson('/api/v1/users/auth/google', [
        'token' => 'valid-google-token',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('token')
            ->has('user', fn ($user) => $user
                ->where('email', 'google@example.com')
                ->where('name', 'Google User')
                ->where('google_login', true)
                ->etc()
            )
        );

    $user = User::where('email', 'google@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_login)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
});

test('user can link google account', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'google@example.com', 'google_login' => false]);
    $token = $user->createToken('test')->plainTextToken;

    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getEmail')->andReturn('google@example.com');

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('stateless')->andReturn($provider);
    $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->postJson('/api/v1/users/auth/google/link', [
        'token' => 'valid-google-token',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Google account linked successfully.']);

    $user->refresh();
    expect($user->google_login)->toBeTrue();
});

test('user cannot link google account with different email', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'user@example.com', 'google_login' => false]);
    $token = $user->createToken('test')->plainTextToken;

    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getEmail')->andReturn('other@example.com');

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('stateless')->andReturn($provider);
    $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->postJson('/api/v1/users/auth/google/link', [
        'token' => 'valid-google-token',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Google email does not match your account email.']);

    $user->refresh();
    expect($user->google_login)->toBeFalse();
});

test('user can unlink google account', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['google_login' => true]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/users/auth/google/unlink', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Google account unlinked successfully.']);

    $user->refresh();
    expect($user->google_login)->toBeFalse();
});



test('authenticated user can get their profile', function () {
    /** @var TestCase $this */
    $timezone = \App\Models\Timezone::factory()->create(['name' => 'Asia/Tokyo']);
    $user = User::factory()->create(['timezone_id' => $timezone->id]);
    Sanctum::actingAs($user, [], 'sanctum');

    $response = $this->getJson('/api/v1/users/me');

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($userJson) => $userJson
                ->where('id', fn ($id) => ! empty($id))
                ->where('email', $user->email)
                ->where('timezone.name', 'Asia/Tokyo')
                ->has('timezone_id')
                ->has('name')
                ->etc()
            )
        );
});

test('unauthenticated user cannot get profile', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/api/v1/users/me');

    $response->assertStatus(401);
});
