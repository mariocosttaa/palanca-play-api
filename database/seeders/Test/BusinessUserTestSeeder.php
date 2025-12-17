<?php

namespace Database\Seeders\Test;

use App\Models\BusinessUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BusinessUserTestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BusinessUser::firstOrCreate(
            ['email' => 'business@example.com'],
            [
                'name' => 'Business User',
                'surname' => 'Test',
                'password' => Hash::make('password'),
                'country_id' => 1, // Assuming country with ID 1 exists
                'calling_code' => '244', // Angola
                'phone' => '923456789',
                'timezone' => 'Africa/Luanda',
            ]
        );
    }
}
