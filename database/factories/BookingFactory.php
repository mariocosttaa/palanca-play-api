<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Manager\CurrencyModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+1 month');
        $endDate = clone $startDate;
        $startTime = fake()->time('H:i:s');
        $endTime = date('H:i:s', strtotime($startTime) + (2 * 3600)); // 2 hours later

        return [
            'tenant_id' => Tenant::factory(),
            'court_id' => Court::factory(),
            'user_id' => User::factory(),
            'currency_id' => CurrencyModel::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'price' => $this->faker->numberBetween(1000, 5000), // in cents
            'status' => BookingStatusEnum::PENDING,
            'payment_status' => PaymentStatusEnum::PENDING,
            'payment_method' => null,
        ];
    }

    /**
     * Indicate that the booking is confirmed.
     */
    public function confirmed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => BookingStatusEnum::CONFIRMED,
            ];
        });
    }

    /**
     * Indicate that the booking is cancelled.
     */
    public function cancelled()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => BookingStatusEnum::CANCELLED,
            ];
        });
    }

    /**
     * Indicate that the booking is paid.
     */
    public function paid()
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_status' => PaymentStatusEnum::PAID,
                'payment_method' => PaymentMethodEnum::CASH,
            ];
        });
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
        ]);
    }

    /**
     * Set the user.
     */
    public function forUser(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }
}

