<?php

namespace Tests\Feature\Api\Business;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezoneControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_timezones()
    {
        $this->seed(\Database\Seeders\Default\TimezoneSeeder::class);

        $businessUser = \App\Models\BusinessUser::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($businessUser, [], 'business');

        $response = $this->getJson(route('timezones.index'));

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
