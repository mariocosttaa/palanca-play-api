<?php

namespace Database\Factories;

use App\Models\BusinessUser;
use App\Models\BusinessUserTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessUserTenant>
 */
class BusinessUserTenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BusinessUserTenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_user_id' => BusinessUser::factory(),
            'tenant_id' => Tenant::factory(),
        ];
    }

    /**
     * Set the business user.
     */
    public function forBusinessUser(BusinessUser|int $businessUser): static
    {
        return $this->state(fn (array $attributes) => [
            'business_user_id' => $businessUser instanceof BusinessUser ? $businessUser->id : $businessUser,
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
}

