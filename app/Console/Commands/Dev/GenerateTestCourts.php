<?php

namespace App\Console\Commands\Dev;

use App\Enums\CourtTypeEnum;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTestCourts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:generate-courts {tenant_id? : The ID of the tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate 4 court types and 5 courts per type for a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        if (!$tenantId) {
            $tenants = Tenant::all(['id', 'name']);
            if ($tenants->isEmpty()) {
                $this->error('No tenants found.');
                return;
            }
            
            $headers = ['ID', 'Name'];
            $this->table($headers, $tenants->toArray());
            
            $tenantId = $this->ask('Please enter the Tenant ID');
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant with ID {$tenantId} not found.");
            return;
        }

        $this->info("Generating courts for tenant: {$tenant->name}");

        DB::transaction(function () use ($tenant) {
            // Create 4 Court Types
            $types = [
                [
                    'name' => 'Padel Courts',
                    'type' => CourtTypeEnum::PADEL,
                    'description' => 'Professional Padel Courts',
                ],
                [
                    'name' => 'Tennis Courts',
                    'type' => CourtTypeEnum::TENNIS,
                    'description' => 'Standard Tennis Courts',
                ],
                [
                    'name' => 'Football Field',
                    'type' => CourtTypeEnum::FOOTBALL,
                    'description' => '5-a-side Football Field',
                ],
                [
                    'name' => 'Basketball Court',
                    'type' => CourtTypeEnum::BASKETBALL,
                    'description' => 'Indoor Basketball Court',
                ],
            ];

            foreach ($types as $typeData) {
                $this->info("Creating Court Type: {$typeData['name']}");
                
                $courtType = CourtType::create([
                    'tenant_id' => $tenant->id,
                    'name' => $typeData['name'],
                    'type' => $typeData['type'],
                    'description' => $typeData['description'],
                    'interval_time_minutes' => 90,
                    'buffer_time_minutes' => 0,
                    'price_per_interval' => 2000, // 20.00
                    'status' => true,
                ]);

                // Create availabilities for Court Type (Mon-Sun, 09:00-22:00)
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($days as $day) {
                    CourtAvailability::create([
                        'tenant_id' => $tenant->id,
                        'court_type_id' => $courtType->id,
                        'day_of_week_recurring' => $day,
                        'start_time' => '09:00:00',
                        'end_time' => '22:00:00',
                        'is_available' => true,
                    ]);
                }

                // Create 5 Courts for this type
                for ($i = 1; $i <= 5; $i++) {
                    $courtName = "{$typeData['name']} Court {$i}";
                    $this->line("  - Creating Court: {$courtName}");
                    
                    $court = Court::create([
                        'tenant_id' => $tenant->id,
                        'court_type_id' => $courtType->id,
                        'name' => $courtName,
                        'number' => $i,
                        'status' => true,
                    ]);

                    // Optionally create court-specific availabilities (e.g., maintenance on one court)
                    // For now, let's leave them using the court type availability
                }
            }
        });

        $this->newLine();
        $this->info("Successfully generated 4 court types and 20 courts for tenant {$tenant->name}.");
    }
}
