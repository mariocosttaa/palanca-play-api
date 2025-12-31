<?php

namespace Database\Factories;

use App\Models\BusinessNotification;
use App\Models\BusinessUser;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessNotification>
 */
class BusinessNotificationFactory extends Factory
{
    protected $model = BusinessNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'business_user_id' => BusinessUser::factory(),
            'subject' => $this->faker->sentence,
            'message' => $this->faker->paragraph,
            'read_at' => null,
        ];
    }
}
