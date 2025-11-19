<?php

namespace Database\Seeders\Test;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantTestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(int $count = 1): void
    {
        // Create tenants
        for ($i = 0; $i < $count; $i++) {
            $tenant = Tenant::create([
                'name' => 'Tenant ' . ($i + 1),
                'address' => '123 Test St',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
                'auto_confirm_bookings' => true,
                'booking_interval_minutes' => 60,
                'buffer_between_bookings_minutes' => 0,
            ]);

            // Create a subscription plan for each tenant
            SubscriptionPlan::create([
                'tenant_id' => $tenant->id,
                'courts' => 5,
                'price' => 9900,
            ]);
        }
    }
}

