<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateStart = fake()->dateTimeBetween('-1 month', 'now');
        $dateEnd = clone $dateStart;
        $dateEnd->modify('+1 month');

        return [
            'tenant_id' => Tenant::factory(),
            'period' => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'price' => fake()->numberBetween(10000, 100000), // in cents
            'status' => fake()->randomElement(['pending', 'paid', 'overdue', 'cancelled']),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the invoice is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    /**
     * Indicate that the invoice is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
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
     * Add metadata to the invoice.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}

