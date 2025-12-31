<?php

use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('business user can register and receive verification code', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    \App\Models\Country::factory()->create(['calling_code' => '+1']);

    $email = 'jane.business@example.com';

    // Step 1: Register
    $response = $this->postJson('/api/business/v1/business-users/register', [
        'name' => 'Jane Business',
        'surname' => 'Smith',
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
                ->where('name', 'Jane Business')
                ->where('email', $email)
                ->etc()
            )
        );

    // Verify user created but not verified
    $user = BusinessUser::where('email', $email)->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->toBeNull();

    // Verify email sent
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailVerificationCode::class);
});

test('business user can verify email with valid code', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    \App\Models\Country::factory()->create(['calling_code' => '+1']);
    $email = 'jane.business@example.com';

    // Register
    $registerResponse = $this->postJson('/api/business/v1/business-users/register', [
        'name' => 'Jane Business',
        'surname' => 'Smith',
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
    $response = $this->postJson('/api/business/v1/business-users/verification/verify', [
        'code' => $code,
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Email verified successfully.']);

    $user = BusinessUser::where('email', $email)->first();
    expect($user->email_verified_at)->not->toBeNull();
});

test('business user cannot verify email with invalid code', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/business/v1/business-users/verification/verify', [
        'code' => '000000',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Invalid or expired verification code.']);
});

test('business user can resend verification code', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    $user = BusinessUser::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/business/v1/business-users/verification/resend', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Verification code sent successfully.']);

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailVerificationCode::class);
});

test('business user resend verification code is rate limited', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    $user = BusinessUser::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    // Send 3 times (allowed)
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/business/v1/business-users/verification/resend', [], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(200);
    }

    // 4th time (blocked)
    $response = $this->postJson('/api/business/v1/business-users/verification/resend', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(429);
});

test('business user can check verification status', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    // Check status (unverified)
    $response = $this->getJson('/api/business/v1/business-users/verification/status', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['verified' => false]);

    // Manually verify
    $user->markEmailAsVerified();

    // Clear auth cache
    \Illuminate\Support\Facades\Auth::forgetGuards();

    // Check status (verified)
    $response = $this->getJson('/api/business/v1/business-users/verification/status', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['verified' => true]);
});

test('business user cannot register with invalid email', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/business/v1/business-users/register', [
        'name' => 'Jane Business',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('business user cannot register with duplicate email', function () {
    /** @var TestCase $this */
    BusinessUser::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/business/v1/business-users/register', [
        'name' => 'Jane Business',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('business user can login with valid credentials', function () {
    /** @var TestCase $this */
    $businessUser = BusinessUser::factory()->create([
        'email' => 'jane.business@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/business/v1/business-users/login', [
        'email' => 'jane.business@example.com',
        'password' => 'password123',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('data.token')
            ->has('data.user', fn ($userJson) => $userJson
                ->where('email', 'jane.business@example.com')
                ->has('id')
                ->etc()
            )
        );
});

test('business user cannot login with invalid credentials', function () {
    /** @var TestCase $this */
    BusinessUser::factory()->create([
        'email' => 'jane.business@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/business/v1/business-users/login', [
        'email' => 'jane.business@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('authenticated business user can get their profile', function () {
    /** @var TestCase $this */
    $timezone = \App\Models\Timezone::factory()->create(['name' => 'Europe/Madrid']);
    $businessUser = BusinessUser::factory()->create(['timezone_id' => $timezone->id]);
    Sanctum::actingAs($businessUser, [], 'business');

    $response = $this->getJson('/api/business/v1/business-users/me');

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($userJson) => $userJson
                ->where('id', fn ($id) => ! empty($id))
                ->where('email', $businessUser->email)
                ->where('timezone.name', 'Europe/Madrid')
                ->has('timezone_id')
                ->has('name')
                ->etc()
            )
        );
});

test('unauthenticated business user cannot get profile', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/api/business/v1/business-users/me');

    $response->assertStatus(401);
});

test('authenticated business user can logout', function () {
    /** @var TestCase $this */
    $businessUser = BusinessUser::factory()->create();
    $token = $businessUser->createToken('test-device')->plainTextToken;

    $response = $this->postJson('/api/business/v1/business-users/logout', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);

    // Verify token was deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $businessUser->id,
        'tokenable_type' => BusinessUser::class,
    ]);
});



test('unverified business user cannot access tenant routes', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email_verified_at' => null]);
    $token = $user->createToken('test')->plainTextToken;

    // Try to access tenant list (protected by verified.api)
    $response = $this->getJson('/api/business/v1/tenants', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(403)
        ->assertJsonFragment(['message' => 'Your email address is not verified.']);
});

test('verified business user can access tenant routes', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email_verified_at' => now()]);
    $token = $user->createToken('test')->plainTextToken;

    // Try to access tenant list
    $response = $this->getJson('/api/business/v1/tenants', [
        'Authorization' => "Bearer {$token}",
    ]);

    // Should be 200 OK
    $response->assertStatus(200);
});

test('unverified business user can update profile', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email_verified_at' => null, 'name' => 'Original Name']);
    $token = $user->createToken('test')->plainTextToken;

    // Profile updates don't require email verification
    $response = $this->putJson('/api/business/v1/profile', [
        'name' => 'Updated Name',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.name', 'Updated Name');
    
    $user->refresh();
    expect($user->name)->toBe('Updated Name');
});

test('unverified business user can update language preference', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email_verified_at' => null, 'locale' => 'en']);
    $token = $user->createToken('test')->plainTextToken;

    // Language updates don't require email verification
    $response = $this->patchJson('/api/business/v1/profile/language', [
        'locale' => 'pt',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.locale', 'pt');
    
    $user->refresh();
    expect($user->locale->value)->toBe('pt');
});

test('business user can login with google', function () {
    /** @var TestCase $this */
    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getId')->andReturn('1234567890');
    $abstractUser->shouldReceive('getName')->andReturn('Google Business User');
    $abstractUser->shouldReceive('getEmail')->andReturn('business.google@example.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://en.gravatar.com/userimage');

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('stateless')->andReturn($provider);
    $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->postJson('/api/business/v1/business-users/auth/google', [
        'token' => 'valid-google-token',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('data.token')
            ->has('data.user', fn ($user) => $user
                ->where('email', 'business.google@example.com')
                ->where('name', 'Google Business User')
                ->where('google_login', true)
                ->etc()
            )
        );

    $user = BusinessUser::where('email', 'business.google@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_login)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
});

test('business user can link google account', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email' => 'business.google@example.com', 'google_login' => false]);
    $token = $user->createToken('test')->plainTextToken;

    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getEmail')->andReturn('business.google@example.com');

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('stateless')->andReturn($provider);
    $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->postJson('/api/business/v1/business-users/auth/google/link', [
        'token' => 'valid-google-token',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Google account linked successfully.']);

    $user->refresh();
    expect($user->google_login)->toBeTrue();
});

test('business user cannot link google account with different email', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['email' => 'business@example.com', 'google_login' => false]);
    $token = $user->createToken('test')->plainTextToken;

    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getEmail')->andReturn('other@example.com');

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('stateless')->andReturn($provider);
    $provider->shouldReceive('userFromToken')->andReturn($abstractUser);

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->postJson('/api/business/v1/business-users/auth/google/link', [
        'token' => 'valid-google-token',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Google email does not match your account email.']);

    $user->refresh();
    expect($user->google_login)->toBeFalse();
});

test('business user can unlink google account', function () {
    /** @var TestCase $this */
    $user = BusinessUser::factory()->create(['google_login' => true]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/business/v1/business-users/auth/google/unlink', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Google account unlinked successfully.']);

    $user->refresh();
    expect($user->google_login)->toBeFalse();
});

