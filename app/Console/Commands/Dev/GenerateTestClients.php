<?php

namespace App\Console\Commands\Dev;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTestClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:generate-clients {tenant_id? : The ID of the tenant} {--count=100 : Number of clients to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate random clients for a specific tenant';

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

        $this->info("Generating {$count} clients for tenant: {$tenant->name} (ID: {$tenant->id})");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        DB::transaction(function () use ($tenant, $count, $bar) {
            // Create users in chunks to avoid memory issues if count is large
            $chunkSize = 50;
            $remaining = $count;

            while ($remaining > 0) {
                $currentBatch = min($remaining, $chunkSize);
                
                $users = User::factory()->count($currentBatch)->create([
                    'country_id' => $tenant->country_id,
                    'is_app_user' => false, // Business clients
                    'calling_code' => '351', // Defaulting to Portugal/Tenant region or random? Let's keep it simple or use factory default if I update factory usage.
                    // Actually, let's use the factory's phone generation if possible, but factory has nulls.
                    // Let's explicitly set some fields to ensure they look good.
                ]);

                // Attach users to tenant
                $tenant->users()->attach($users->pluck('id'));

                $bar->advance($currentBatch);
                $remaining -= $currentBatch;
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Successfully generated {$count} clients for tenant {$tenant->name}.");
    }
}
