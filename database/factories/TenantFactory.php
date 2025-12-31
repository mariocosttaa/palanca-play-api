<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => \App\Models\Country::factory(),
            'name' => fake()->company(),
            'logo' => fake()->imageUrl(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'currency' => fake()->randomElement(['usd', 'aoa', 'eur', 'brl']),
            'timezone' => 'UTC',
            'auto_confirm_bookings' => fake()->boolean(),
            'booking_interval_minutes' => fake()->numberBetween(30, 120),
            'buffer_between_bookings_minutes' => fake()->numberBetween(0, 30),
        ];
    }
}

