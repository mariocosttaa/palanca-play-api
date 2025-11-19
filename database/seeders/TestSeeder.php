<?php

namespace Database\Seeders;

use Database\Seeders\Test\BookingTestSeeder;
use Database\Seeders\Test\BusinessUserTenantTestSeeder;
use Database\Seeders\Test\BusinessUserTestSeeder;
use Database\Seeders\Test\CourtTestSeeder;
use Database\Seeders\Test\SubscriptionPlanTestSeeder;
use Database\Seeders\Test\TenantTestSeeder;
use Database\Seeders\Test\UserTestSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     *
     * This master seeder orchestrates all test seeders in the correct order.
     * Run with: php artisan db:seed --test
     */
    public function run(): void
    {
        // 0. Ensure default data exists (Countries)
        $this->call(\Database\Seeders\Default\CountrySeeder::class);

        // 1. Seed business users (1 user)
        $this->call(BusinessUserTestSeeder::class, false, ['count' => 1]);

        // 2. Seed app users (1 user)
        $this->call(UserTestSeeder::class, false, ['count' => 1]);

        // 3. Seed tenants (1 tenant with subscription plan)
        $this->call(TenantTestSeeder::class, false, ['count' => 1]);

        // 4. Link business users with tenants
        $this->call(BusinessUserTenantTestSeeder::class, false, ['relationshipsPerBusinessUser' => 1]);

        // 5. Seed courts and availabilities (5 courts)
        $this->call(CourtTestSeeder::class, false, ['courtTypesPerTenant' => 1, 'courtsPerType' => 5]);

        // 6. Seed bookings (5 bookings)
        $this->call(BookingTestSeeder::class, false, ['bookingsPerTenant' => 5]);
    }
}

