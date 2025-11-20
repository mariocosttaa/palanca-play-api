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
     */
    public function run(): void
    {
        $businessUser = BusinessUser::where('email', 'business@example.com')->first();
        $tenant = Tenant::first();

        if ($businessUser && $tenant) {
            $businessUser->tenants()->syncWithoutDetaching([$tenant->id]);
        }
    }
}

