<?php

namespace Database\Factories;

use App\Enums\CourtTypeEnum;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourtType>
 */
class CourtTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = CourtType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement(CourtTypeEnum::cases())->value,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'interval_time_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'buffer_time_minutes' => fake()->randomElement([0, 5, 10, 15, 20, 25, 30]),
            'price_per_interval' => fake()->numberBetween(1000, 5000),
            'status' => true,
        ];
    }

    public function forType(string|CourtTypeEnum $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type instanceof CourtTypeEnum ? $type->value : $type,
        ]);
    }

    /**
     * Indicate that the court type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}

