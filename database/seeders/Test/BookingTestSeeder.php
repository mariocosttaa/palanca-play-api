<?php

namespace Database\Seeders\Test;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookingTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(int $bookingsPerTenant = 5): void
    {
        $tenants = Tenant::with('courtTypes.courts')->get();
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($tenants as $tenant) {
            // Get all courts for this tenant
            $courts = $tenant->courtTypes->flatMap->courts;

            if ($courts->isEmpty()) {
                continue;
            }

            // Create bookings
            for ($i = 0; $i < $bookingsPerTenant; $i++) {
                $court = $courts->random();
                $user = $users->random();
                $startDate = now()->addDays(rand(1, 30));
                $startTime = $startDate->copy()->setTime(rand(8, 20), 0, 0);
                $endTime = $startTime->copy()->addHour();

                Booking::create([
                    'tenant_id' => $tenant->id,
                    'court_id' => $court->id,
                    'user_id' => $user->id,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $startDate->format('Y-m-d'),
                    'start_time' => $startTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'price' => rand(1000, 5000), // cents
                    'is_pending' => false,
                    'is_cancelled' => false,
                    'is_paid' => true,
                ]);
            }
        }
    }
}

