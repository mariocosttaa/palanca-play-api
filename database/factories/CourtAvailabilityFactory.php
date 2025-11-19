<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourtAvailability>
 */
class CourtAvailabilityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = CourtAvailability::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->time('H:i:s');
        $endTime = date('H:i:s', strtotime($startTime) + (2 * 3600)); // 2 hours later

        return [
            'tenant_id' => Tenant::factory(),
            'court_id' => null,
            'court_type_id' => null,
            'day_of_week_recurring' => fake()->randomElement(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            'specific_date' => null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'breaks' => null,
            'is_available' => true,
        ];
    }

    /**
     * Indicate that the availability is for a specific date.
     */
    public function specificDate(string|\DateTime $date): static
    {
        return $this->state(fn (array $attributes) => [
            'specific_date' => $date instanceof \DateTime ? $date->format('Y-m-d') : $date,
            'day_of_week_recurring' => null,
        ]);
    }

    /**
     * Indicate that the availability is recurring.
     */
    public function recurring(string $dayOfWeek): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week_recurring' => $dayOfWeek,
            'specific_date' => null,
        ]);
    }

    /**
     * Indicate that the availability is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * Set the tenant.
     */
    public function forTenant(Tenant|int $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
        ]);
    }

    /**
     * Set the court.
     */
    public function forCourt(Court|int $court): static
    {
        return $this->state(fn (array $attributes) => [
            'court_id' => $court instanceof Court ? $court->id : $court,
            'court_type_id' => null,
        ]);
    }

    /**
     * Set the court type.
     */
    public function forCourtType(CourtType|int $courtType): static
    {
        return $this->state(fn (array $attributes) => [
            'court_type_id' => $courtType instanceof CourtType ? $courtType->id : $courtType,
            'court_id' => null,
        ]);
    }

    /**
     * Add breaks to the availability.
     */
    public function withBreaks(array $breaks): static
    {
        return $this->state(fn (array $attributes) => [
            'breaks' => $breaks,
        ]);
    }
}

