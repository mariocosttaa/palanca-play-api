<?php

use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('business user can register with valid data', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/business/v1/business-users/register', [
        'name' => 'Jane Business',
        'surname' => 'Smith',
        'email' => 'jane.business@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('data.token')
            ->has('data.user', fn ($user) => $user
                ->has('id')
                ->where('name', 'Jane Business')
                ->where('surname', 'Smith')
                ->where('email', 'jane.business@example.com')
                ->has('google_login')
                ->has('created_at')
                ->etc()
            )
        );

    // Verify business user was created in database
    $this->assertDatabaseHas('business_users', [
        'email' => 'jane.business@example.com',
        'name' => 'Jane Business',
    ]);
});

test('business user cannot register with invalid email', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/business/v1/business-users/register', [
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

    $response = $this->postJson('/business/v1/business-users/register', [
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

    $response = $this->postJson('/business/v1/business-users/login', [
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

    $response = $this->postJson('/business/v1/business-users/login', [
        'email' => 'jane.business@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('authenticated business user can get their profile', function () {
    /** @var TestCase $this */
    $businessUser = BusinessUser::factory()->create();
    Sanctum::actingAs($businessUser, [], 'business');

    $response = $this->getJson('/business/v1/business-users/me');

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($userJson) => $userJson
                ->where('id', fn ($id) => ! empty($id))
                ->where('email', $businessUser->email)
                ->has('name')
                ->etc()
            )
        );
});

test('unauthenticated business user cannot get profile', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/business/v1/business-users/me');

    $response->assertStatus(401);
});

test('authenticated business user can logout', function () {
    /** @var TestCase $this */
    $businessUser = BusinessUser::factory()->create();
    Sanctum::actingAs($businessUser, [], 'business');

    $response = $this->postJson('/business/v1/business-users/logout');

    $response->assertStatus(204);

    // Verify token was deleted
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $businessUser->id,
        'tokenable_type' => BusinessUser::class,
    ]);
});

test('business user cannot logout without token', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/business/v1/business-users/logout');

    $response->assertStatus(401);
});

