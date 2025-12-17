<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('user can register with valid data', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    \App\Models\Country::factory()->create(['calling_code' => '+1']);

    $email = 'john.doe@example.com';

    // Step 1: Initiate
    $this->postJson('/api/v1/users/register/initiate', [
        'name' => 'John Doe',
        'surname' => 'Doe',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Test Device',
        'calling_code' => '+1',
        'phone' => '123456789',
    ])->assertStatus(200);

    // Get code
    $code = \App\Models\EmailSent::where('user_email', $email)->first()->code;

    // Step 2: Complete
    $response = $this->postJson('/api/v1/users/register/complete', [
        'name' => 'John Doe',
        'surname' => 'Doe',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Test Device',
        'calling_code' => '+1',
        'phone' => '123456789',
        'code' => $code,
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('data.token')
            ->has('data.user', fn ($user) => $user
                ->has('id')
                ->where('name', 'John Doe')
                ->where('surname', 'Doe')
                ->where('email', 'john.doe@example.com')
                ->has('google_login')
                ->has('created_at')
                ->etc()
            )
        );

    // Verify user was created in database
    $this->assertDatabaseHas('users', [
        'email' => 'john.doe@example.com',
        'name' => 'John Doe',
    ]);
});

test('user cannot register with invalid email', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users/register/initiate', [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user cannot register with mismatched passwords', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users/register/initiate', [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('user cannot register with duplicate email', function () {
    /** @var TestCase $this */
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/v1/users/register/initiate', [
        'name' => 'John Doe',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('user can login with valid credentials', function () {
    /** @var TestCase $this */
    $user = User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/users/login', [
        'email' => 'john.doe@example.com',
        'password' => 'password123',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('data.token')
            ->has('data.user', fn ($userJson) => $userJson
                ->where('email', 'john.doe@example.com')
                ->has('id')
                ->etc()
            )
        );
});

test('user cannot login with invalid credentials', function () {
    /** @var TestCase $this */
    User::factory()->create([
        'email' => 'john.doe@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/users/login', [
        'email' => 'john.doe@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('authenticated user can get their profile', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/users/me');

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($userJson) => $userJson
                ->where('id', fn ($id) => ! empty($id))
                ->where('email', $user->email)
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

test('authenticated user can logout', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $token = $user->createToken('test-device')->plainTextToken;

    $response = $this->postJson('/api/v1/users/logout', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);

    // Verify token was deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

test('user cannot logout without token', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users/logout');

    $response->assertStatus(401);
});

test('user cannot complete registration with invalid code', function () {
    /** @var TestCase $this */
    $email = 'john.doe@example.com';

    $response = $this->postJson('/api/v1/users/register/complete', [
        'name' => 'John Doe',
        'surname' => 'Doe',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Test Device',
        'calling_code' => '+1',
        'phone' => '123456789',
        'code' => '000000', // Invalid code
    ]);

    $response->assertStatus(422);
});

test('user registration is rate limited by cooldown', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    \App\Models\Country::factory()->create(['calling_code' => '+1']);
    $email = 'rate.limit@example.com';

    // First request
    $this->postJson('/api/v1/users/register/initiate', [
        'name' => 'Mobile',
        'surname' => 'User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '123456789',
        'calling_code' => '+1',
    ])->assertStatus(200);

    // Immediate second request
    $response = $this->postJson('/api/v1/users/register/initiate', [
        'name' => 'Mobile',
        'surname' => 'User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '123456789',
        'calling_code' => '+1',
    ]);

    $response->assertStatus(429);
    $this->assertStringContainsString('seconds before requesting a new verification email.', $response->json('message'));
});

test('user registration is rate limited by daily max', function () {
    /** @var TestCase $this */
    \Illuminate\Support\Facades\Mail::fake();
    \App\Models\Country::factory()->create(['calling_code' => '+1']);
    $email = 'daily.limit@example.com';

    // Create 10 emails sent in the last 24 hours
    for ($i = 0; $i < 10; $i++) {
        \App\Models\EmailSent::create([
            'user_email' => $email,
            'code' => '123456',
            'type' => \App\Enums\EmailTypeEnum::CONFIRMATION_EMAIL,
            'subject' => 'Subject',
            'title' => 'Title',
            'html_content' => 'Content',
            'status' => 'sent',
            'sent_at' => now()->subHours(1), // Within 24 hours
        ]);
    }

    // Request should fail
    $response = $this->postJson('/api/v1/users/register/initiate', [
        'name' => 'Mobile',
        'surname' => 'User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '123456789',
        'calling_code' => '+1',
    ]);

    $response->assertStatus(429)
        ->assertJsonFragment(['message' => 'You have reached the maximum number of verification emails. Please contact support for assistance.']);
});

