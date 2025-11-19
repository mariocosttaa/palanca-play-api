<?php

namespace Database\Seeders\Test;

use App\Models\BusinessUser;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessUserTenantTestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     *
     * This seeder links business users with tenants.
     * It will use existing business users and tenants, or create them if they don't exist.
     *
     * @param int $relationshipsPerBusinessUser Number of tenant relationships to create per business user
     */
    public function run(int $relationshipsPerBusinessUser = 2): void
    {
        // Get existing business users or create some if none exist
        $businessUsers = BusinessUser::all();
        if ($businessUsers->isEmpty()) {
            $businessUsers = BusinessUser::factory(5)->create();
        }

        // Get existing tenants or create some if none exist
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $tenants = Tenant::factory(10)->create();
        }

        // Link business users with tenants
        foreach ($businessUsers as $businessUser) {
            // Get random tenants that this business user doesn't already have
            $availableTenants = $tenants->reject(function ($tenant) use ($businessUser) {
                return $businessUser->tenants->contains($tenant->id);
            });

            // Create relationships using the relationship method (handles duplicates automatically)
            if ($availableTenants->isNotEmpty()) {
                $tenantsToAttach = $availableTenants->random(
                    min($relationshipsPerBusinessUser, $availableTenants->count())
                );

                $businessUser->tenants()->syncWithoutDetaching(
                    $tenantsToAttach->pluck('id')->toArray()
                );
            }
        }
    }
}

