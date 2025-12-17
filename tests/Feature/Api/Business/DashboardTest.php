<?php

namespace Tests\Feature\Api\Business;

use App\Models\Booking;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_dashboard_statistics()
    {
        // Setup
        $tenant = Tenant::factory()->create(['currency' => 'usd']);
        $businessUser = BusinessUser::factory()->create();
        $tenant->businessUsers()->attach($businessUser);
        $tenantHashId = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');

        $court = Court::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create();

        // Create bookings
        // 1. Paid booking today (Revenue, Open Booking, Client, Court Usage)
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'court_id' => $court->id,
            'user_id' => $user->id,
            'start_date' => now()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'price' => 1000, // $10.00
            'is_paid' => true,
            'is_cancelled' => false,
        ]);

        // 2. Unpaid booking tomorrow (Open Booking, Client, Court Usage)
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'court_id' => $court->id,
            'user_id' => $user->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '12:00',
            'end_time' => '13:00',
            'price' => 1500,
            'is_paid' => false,
            'is_cancelled' => false,
        ]);

        // 3. Cancelled booking yesterday (No Revenue, No Open Booking, Client, No Court Usage)
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'court_id' => $court->id,
            'user_id' => $user->id,
            'start_date' => now()->subDay()->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'price' => 2000,
            'is_paid' => true, // Refunded? Logic says cancelled doesn't count for revenue usually, but let's assume cancelled excludes it from revenue in controller logic
            'is_cancelled' => true,
        ]);

        // Act
        $response = $this->actingAs($businessUser, 'business')
            ->getJson("/api/business/v1/business/{$tenantHashId}/dashboard");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'cards' => [
                        'total_revenue',
                        'total_revenue_formatted',
                        'total_open_bookings',
                        'total_clients',
                        'total_court_usage_hours',
                    ],
                    'lists' => [
                        'recent_bookings',
                        'active_clients',
                        'popular_courts',
                    ],
                    'charts' => [
                        'daily_revenue',
                    ],
                ]
            ]);

        // Verify values
        $data = $response->json('data');
        
        // Revenue: Only booking 1 (1000)
        $this->assertEquals(1000, $data['cards']['total_revenue']);
        $this->assertEquals('$ 10.00', $data['cards']['total_revenue_formatted']);

        // Open Bookings: Booking 1 (Today) + Booking 2 (Tomorrow) = 2
        $this->assertEquals(2, $data['cards']['total_open_bookings']);

        // Clients: 1 unique client
        $this->assertEquals(1, $data['cards']['total_clients']);

        // Court Usage: Booking 1 (1h) + Booking 2 (1h) = 2h. Cancelled doesn't count.
        $this->assertEquals(2.0, $data['cards']['total_court_usage_hours']);

        // Lists
        $this->assertCount(3, $data['lists']['recent_bookings']); // All 3 created
        $this->assertCount(1, $data['lists']['active_clients']);
        $this->assertEquals(3, $data['lists']['active_clients'][0]['total_bookings_count']); // All 3 bookings belong to this user
        $this->assertCount(1, $data['lists']['popular_courts']);
        $this->assertEquals(3, $data['lists']['popular_courts'][0]['total_bookings_count']);

        // Chart
        // Should have entry for today with 1000 revenue
        $todayStat = collect($data['charts']['daily_revenue'])->firstWhere('date', now()->format('Y-m-d'));
        $this->assertNotNull($todayStat);
        $this->assertEquals(1000, $todayStat['revenue']);
    }
}
