<?php

namespace Database\Seeders\Default;

use App\Models\Timezone;
use Illuminate\Database\Seeder;

class TimezoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Timezone::count() > 0) {
            return;
        }

        $filePath = database_path('seeders/Default/TimezoneSeeder.json');
        $jsonContent = file_get_contents($filePath);
        $timezones = json_decode($jsonContent, true);

        foreach ($timezones as $timezone) {
            Timezone::create([
                'name' => $timezone['name'],
                'label' => $timezone['label'],
                'offset' => $timezone['offset'],
            ]);
        }
    }
}
