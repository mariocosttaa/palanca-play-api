<?php

namespace Database\Seeders\Test;

use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CourtTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(int $courtTypesPerTenant = 2, int $courtsPerType = 3): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Create Court Types for each Tenant
            for ($i = 0; $i < $courtTypesPerTenant; $i++) {
                $courtType = CourtType::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Court Type ' . ($i + 1),
                    'description' => 'Description for Court Type ' . ($i + 1),
                    'interval_time_minutes' => 60,
                    'buffer_time_minutes' => 0,
                    'status' => true,
                ]);

                // Create Courts for each Court Type
                for ($j = 0; $j < $courtsPerType; $j++) {
                    $court = Court::create([
                        'court_type_id' => $courtType->id,
                        'type' => 'padel',
                        'name' => 'Court ' . ($j + 1),
                        'number' => (string) ($j + 1),
                        'status' => true,
                    ]);

                    // Create Availability for each Court
                    // 1. Recurring availability (e.g., every Monday)
                    CourtAvailability::create([
                        'tenant_id' => $tenant->id,
                        'court_id' => $court->id,
                        'court_type_id' => $courtType->id,
                        'day_of_week_recurring' => 'Monday',
                        'start_time' => '08:00:00',
                        'end_time' => '22:00:00',
                        'is_available' => true,
                    ]);

                    // 2. Specific date availability (e.g., today)
                    CourtAvailability::create([
                        'tenant_id' => $tenant->id,
                        'court_id' => $court->id,
                        'court_type_id' => $courtType->id,
                        'day_of_week_recurring' => null,
                        'specific_date' => now()->format('Y-m-d'),
                        'start_time' => '09:00:00',
                        'end_time' => '21:00:00',
                        'is_available' => true,
                    ]);
                }
            }
        }
    }
}

