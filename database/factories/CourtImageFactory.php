<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourtImage>
 */
class CourtImageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = CourtImage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'path' => 'courts/' . fake()->uuid() . '.jpg',
            'alt' => fake()->sentence(),
            'is_primary' => false,
        ];
    }

    /**
     * Indicate that the image is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Set the court.
     */
    public function forCourt(Court|int $court): static
    {
        return $this->state(fn (array $attributes) => [
            'court_id' => $court instanceof Court ? $court->id : $court,
        ]);
    }
}

