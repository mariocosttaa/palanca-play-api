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
            // First create the tenant
            // First create or retrieve the tenant
            $tenant = Tenant::firstOrCreate(
                ['name' => 'Tenant ' . ($i + 1)],
                [
                    'country_id' => 1,
                    'address' => '123 Test St',
                    'latitude' => 40.7128,
                    'longitude' => -74.0060,
                    'auto_confirm_bookings' => true,
                    'booking_interval_minutes' => 60,
                    'buffer_between_bookings_minutes' => 0,
                ]
            );

            // Then create the subscription plan for the tenant if not exists
            if (!$tenant->subscriptionPlan) {
                SubscriptionPlan::factory()->create([
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Create a valid subscription (Invoice) if not exists
            if (!$tenant->invoices()->where('status', 'paid')->where('date_end', '>', now())->exists()) {
                \App\Models\Invoice::factory()->create([
                    'tenant_id' => $tenant->id,
                    'status' => 'paid',
                    'date_start' => now(),
                    'date_end' => now()->addMonth(),
                ]);
            }
        }
    }
}

