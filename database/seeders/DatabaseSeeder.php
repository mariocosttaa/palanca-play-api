<?php

namespace Database\Seeders;

use Database\Seeders\Default\CountrySeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Default Configuration (Production Safe)
        $this->call([
            'Database\\Seeders\\Default\\CountrySeeder',
        ]);
    }
}
