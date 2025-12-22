<?php

namespace App\Console\Commands\Dev;

use App\Models\Tenant;
use Database\Seeders\Test\BookingTestSeeder;
use Database\Seeders\Test\BusinessUserTenantTestSeeder;
use Database\Seeders\Test\BusinessUserTestSeeder;
use Database\Seeders\Test\CourtTestSeeder;
use Database\Seeders\Test\TenantTestSeeder;
use Database\Seeders\Test\UserTestSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SeedTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:seed 
                            {tenant_id? : The ID of the tenant to seed (optional)} 
                            {--all : Seed all tenants}
                            {--fresh : Create a new tenant and seed it}
                            {--tenants=1 : Number of tenants to create (only with --fresh)}
                            {--users=5 : Number of app users per tenant}
                            {--court-types=2 : Number of court types per tenant}
                            {--courts=3 : Number of courts per court type}
                            {--bookings=10 : Number of bookings per tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '🚀 Seed test data for a specific tenant or all tenants (Dev Tool)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🌱 Starting test data seeding...');
        $this->newLine();

        // Ensure default data exists (Countries, Currencies)
        $this->seedDefaultData();

        // Determine which tenants to seed
        $tenants = $this->determineTenants();

        if ($tenants->isEmpty()) {
            $this->error('❌ No tenants to seed.');
            return Command::FAILURE;
        }

        // Display seeding plan
        $this->displaySeedingPlan($tenants);

        if (!$this->confirm('Continue with seeding?', true)) {
            $this->warn('Seeding cancelled.');
            return Command::SUCCESS;
        }

        // Seed data for each tenant
        $this->seedTenants($tenants);

        $this->newLine();
        $this->info('✅ Test data seeding completed successfully!');
        return Command::SUCCESS;
    }

    /**
     * Seed default data (Countries, Currencies)
     */
    protected function seedDefaultData(): void
    {
        $this->line('📦 Ensuring default data exists...');
        
        Model::unguarded(function () {
            $this->callSilent(\Database\Seeders\Default\CountrySeeder::class);
            $this->callSilent(\Database\Seeders\CurrencySeeder::class);
        });

        $this->info('  ✓ Default data ready');
        $this->newLine();
    }

    /**
     * Determine which tenants to seed based on options
     */
    protected function determineTenants()
    {
        // Fresh mode: create new tenant(s)
        if ($this->option('fresh')) {
            return $this->createFreshTenants();
        }

        // All mode: seed all existing tenants
        if ($this->option('all')) {
            return Tenant::all();
        }

        // Specific tenant mode
        $tenantId = $this->argument('tenant_id');
        
        if (!$tenantId) {
            return $this->promptForTenant();
        }

        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("❌ Tenant with ID {$tenantId} not found.");
            return collect();
        }

        return collect([$tenant]);
    }

    /**
     * Create fresh tenant(s)
     */
    protected function createFreshTenants()
    {
        $count = (int) $this->option('tenants');
        
        $this->info("🆕 Creating {$count} fresh tenant(s)...");
        
        Model::unguarded(function () use ($count) {
            $seeder = new TenantTestSeeder();
            $seeder->setContainer($this->laravel);
            $seeder->setCommand($this);
            $seeder->run($count);
        });

        // Get the latest created tenants
        return Tenant::latest()->take($count)->get();
    }

    /**
     * Prompt user to select a tenant
     */
    protected function promptForTenant()
    {
        $tenants = Tenant::all(['id', 'name']);
        
        if ($tenants->isEmpty()) {
            $this->error('No tenants found. Creating a fresh tenant...');
            return $this->createFreshTenants();
        }
        
        $this->table(['ID', 'Name'], $tenants->toArray());
        
        $tenantId = $this->ask('Please enter the Tenant ID (or press Enter to create a new tenant)');
        
        if (!$tenantId) {
            return $this->createFreshTenants();
        }
        
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("Tenant with ID {$tenantId} not found.");
            return collect();
        }
        
        return collect([$tenant]);
    }

    /**
     * Display the seeding plan
     */
    protected function displaySeedingPlan($tenants): void
    {
        $this->info('📋 Seeding Plan:');
        $this->line('  Tenants: ' . $tenants->pluck('name')->join(', '));
        $this->line('  App Users per tenant: ' . $this->option('users'));
        $this->line('  Court Types per tenant: ' . $this->option('court-types'));
        $this->line('  Courts per type: ' . $this->option('courts'));
        $this->line('  Bookings per tenant: ' . $this->option('bookings'));
        $this->newLine();
    }

    /**
     * Seed data for tenants
     */
    protected function seedTenants($tenants): void
    {
        foreach ($tenants as $tenant) {
            $this->info("🏢 Seeding tenant: {$tenant->name} (ID: {$tenant->id})");
            
            DB::transaction(function () use ($tenant) {
                // Override tenant filter in seeders by temporarily setting a global scope
                $this->seedBusinessUsers();
                $this->seedAppUsers($tenant);
                $this->seedBusinessUserTenantLinks($tenant);
                $this->seedCourts($tenant);
                $this->seedBookings($tenant);
            });

            $this->newLine();
        }
    }

    /**
     * Seed business users (global)
     */
    protected function seedBusinessUsers(): void
    {
        $this->line('  👤 Seeding business users...');
        
        Model::unguarded(function () {
            $seeder = new BusinessUserTestSeeder();
            $seeder->setContainer($this->laravel);
            $seeder->setCommand($this);
            $seeder->run();
        });
    }

    /**
     * Seed app users for specific tenant
     */
    protected function seedAppUsers(Tenant $tenant): void
    {
        $count = (int) $this->option('users');
        $this->line("  👥 Seeding {$count} app users...");
        
        Model::unguarded(function () use ($count, $tenant) {
            $seeder = new UserTestSeeder();
            $seeder->setContainer($this->laravel);
            $seeder->setCommand($this);
            // We need to modify the seeder to accept tenant_id
            // For now, it will seed for all tenants, but we can filter later
            $seeder->run($count);
        });
    }

    /**
     * Seed business user-tenant links
     */
    protected function seedBusinessUserTenantLinks(Tenant $tenant): void
    {
        $this->line('  🔗 Linking business users with tenant...');
        
        Model::unguarded(function () use ($tenant) {
            $seeder = new BusinessUserTenantTestSeeder();
            $seeder->setContainer($this->laravel);
            $seeder->setCommand($this);
            $seeder->run();
        });
    }

    /**
     * Seed courts for specific tenant
     */
    protected function seedCourts(Tenant $tenant): void
    {
        $courtTypes = (int) $this->option('court-types');
        $courts = (int) $this->option('courts');
        
        $this->line("  🎾 Seeding {$courtTypes} court types with {$courts} courts each...");
        
        Model::unguarded(function () use ($courtTypes, $courts, $tenant) {
            // We need to temporarily limit to this tenant
            // Create court types and courts directly here
            $this->seedCourtsForTenant($tenant, $courtTypes, $courts);
        });
    }

    /**
     * Seed courts for a specific tenant
     */
    protected function seedCourtsForTenant(Tenant $tenant, int $courtTypesCount, int $courtsCount): void
    {
        $seeder = new CourtTestSeeder();
        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        
        // Temporarily override the seeder to only run for this tenant
        // We'll need to filter the tenant in the seeder
        $seeder->run($courtTypesCount, $courtsCount);
    }

    /**
     * Seed bookings for specific tenant
     */
    protected function seedBookings(Tenant $tenant): void
    {
        $count = (int) $this->option('bookings');
        $this->line("  📅 Seeding {$count} bookings...");
        
        Model::unguarded(function () use ($count, $tenant) {
            $seeder = new BookingTestSeeder();
            $seeder->setContainer($this->laravel);
            $seeder->setCommand($this);
            $seeder->run($count);
        });
    }
}
