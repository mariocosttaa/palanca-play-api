<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_timezones_are_seeded()
    {
        $this->seed(\Database\Seeders\Default\TimezoneSeeder::class);

        $this->assertDatabaseHas('timezones', ['name' => 'Africa/Luanda']);
        $this->assertDatabaseHas('timezones', ['name' => 'Europe/Lisbon']);
    }

    public function test_user_can_have_timezone()
    {
        $this->seed(\Database\Seeders\Default\TimezoneSeeder::class);
        $timezone = \App\Models\Timezone::where('name', 'Africa/Luanda')->first();

        $user = \App\Models\User::factory()->create(['timezone_id' => $timezone->id]);

        $this->assertTrue($user->timezone->is($timezone));
    }

    public function test_business_user_can_have_timezone()
    {
        $this->seed(\Database\Seeders\Default\TimezoneSeeder::class);
        $timezone = \App\Models\Timezone::where('name', 'Europe/Lisbon')->first();

        $businessUser = \App\Models\BusinessUser::factory()->create(['timezone_id' => $timezone->id]);

        $this->assertTrue($businessUser->timezone->is($timezone));
    }
}
