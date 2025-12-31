<?php

namespace Tests\Feature\Api\Mobile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezoneControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_timezones_publicly()
    {
        $this->seed(\Database\Seeders\Default\TimezoneSeeder::class);

        $response = $this->getJson('/api/v1/timezones');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'label',
                        'offset',
                    ],
                ],
            ]);
    }
}
