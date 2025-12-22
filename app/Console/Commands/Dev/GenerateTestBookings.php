<?php

namespace App\Console\Commands\Dev;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTestBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:generate-bookings {tenant_id? : The ID of the tenant} {--count=50 : Number of bookings to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate random bookings for a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $count = $this->option('count');

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

        // Get tenant's courts
        $courts = Court::where('tenant_id', $tenant->id)->get();
        if ($courts->isEmpty()) {
            $this->error("No courts found for tenant {$tenant->name}. Please generate courts first.");
            return;
        }

        // Get tenant's users
        $users = User::forTenant($tenant->id)->get();
        if ($users->isEmpty()) {
            $this->error("No users found for tenant {$tenant->name}. Please generate clients first.");
            return;
        }

        // Get currency
        $currencyId = \App\Models\Manager\CurrencyModel::where('code', $tenant->currency)->first()->id ?? 1;

        $this->info("Generating {$count} bookings for tenant: {$tenant->name}");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        DB::transaction(function () use ($tenant, $count, $bar, $courts, $users, $currencyId) {
            for ($i = 0; $i < $count; $i++) {
                $court = $courts->random();
                $user = $users->random();
                
                // Generate random date (past, present, future)
                $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
                $startTime = fake()->numberBetween(9, 20) . ':00:00';
                $endTime = date('H:i:s', strtotime($startTime) + (90 * 60)); // 90 mins later
                
                Booking::create([
                    'tenant_id' => $tenant->id,
                    'court_id' => $court->id,
                    'user_id' => $user->id,
                    'currency_id' => $currencyId,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $startDate->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'price' => fake()->numberBetween(1000, 4000),
                    'is_pending' => fake()->boolean(20), // 20% pending
                    'is_cancelled' => fake()->boolean(10), // 10% cancelled
                    'is_paid' => fake()->boolean(60), // 60% paid
                    'paid_at_venue' => fake()->boolean(30),
                ]);

                // Ensure user-tenant link exists (just in case)
                UserTenant::firstOrCreate([
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                ]);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Successfully generated {$count} bookings.");
    }
}
