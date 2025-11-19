<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Court>
 */
class CourtFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Court::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'court_type_id' => CourtType::factory(),
            'type' => fake()->randomElement(['padel', 'tennis', 'squash', 'badminton', 'other']),
            'name' => fake()->words(2, true),
            'number' => fake()->numberBetween(1, 20),
            'status' => true,
        ];
    }

    /**
     * Indicate that the court is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    /**
     * Set the court type.
     */
    public function forCourtType(CourtType|int $courtType): static
    {
        return $this->state(fn (array $attributes) => [
            'court_type_id' => $courtType instanceof CourtType ? $courtType->id : $courtType,
        ]);
    }

    /**
     * Set the court type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}

