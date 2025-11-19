<?php

namespace Database\Seeders\Test;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserTestSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::create([
                'name' => 'User ' . ($i + 1),
                'surname' => 'Test',
                'email' => $i === 0 ? 'user@example.com' : 'user' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'country_id' => 1, // Assuming country with ID 1 exists
                'calling_code' => '+1',
                'phone' => '0987654321',
            ]);
        }
    }
}

