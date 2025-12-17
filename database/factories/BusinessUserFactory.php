<?php

namespace Database\Factories;

use App\Models\BusinessUser;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessUser>
 */
class BusinessUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BusinessUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'google_login' => false,
            'country_id' => null,
            'calling_code' => null,
            'phone' => null,
            'timezone' => fake()->timezone(),
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ];
    }

    /**
     * Indicate that the business user should have a country.
     */
    public function withCountry(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_id' => Country::factory(),
            'calling_code' => fake()->numerify('+###'),
            'phone' => fake()->phoneNumber(),
        ]);
    }

    /**
     * Indicate that the user's email is not verified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

