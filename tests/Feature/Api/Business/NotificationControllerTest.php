<?php

namespace Tests\Feature\Api\Business;

use App\Actions\General\EasyHashAction;
use App\Models\BusinessNotification;
use App\Models\BusinessUser;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_business_user_can_get_recent_notifications()
    {
        $tenant = Tenant::factory()->create();
        $businessUser = BusinessUser::factory()->create();
        $businessUser->tenants()->attach($tenant);
        
        Sanctum::actingAs($businessUser, ['*'], 'business');

        BusinessNotification::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'business_user_id' => $businessUser->id,
        ]);

        $response = $this->getJson(route('business.notifications.recent'));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_business_user_can_get_all_notifications_with_pagination()
    {
        $tenant = Tenant::factory()->create();
        $businessUser = BusinessUser::factory()->create();
        $businessUser->tenants()->attach($tenant);
        
        Sanctum::actingAs($businessUser, ['*'], 'business');

        BusinessNotification::factory()->count(15)->create([
            'tenant_id' => $tenant->id,
            'business_user_id' => $businessUser->id,
        ]);

        $response = $this->getJson(route('business.notifications.index', ['per_page' => 10]));

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 15);
    }

    public function test_business_user_can_mark_notification_as_read()
    {
        $tenant = Tenant::factory()->create();
        $businessUser = BusinessUser::factory()->create();
        $businessUser->tenants()->attach($tenant);
        
        Sanctum::actingAs($businessUser, ['*'], 'business');

        $notification = BusinessNotification::factory()->create([
            'tenant_id' => $tenant->id,
            'business_user_id' => $businessUser->id,
            'read_at' => null,
        ]);

        $hashedId = EasyHashAction::encode($notification->id, 'notification-id');

        $response = $this->patchJson(route('business.notifications.read', ['notification_id' => $hashedId]));

        $response->assertStatus(200)
            ->assertJsonPath('data.read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_business_user_cannot_access_other_users_notifications()
    {
        $tenant = Tenant::factory()->create();
        $businessUser1 = BusinessUser::factory()->create();
        $businessUser2 = BusinessUser::factory()->create();
        
        $businessUser1->tenants()->attach($tenant);
        $businessUser2->tenants()->attach($tenant);
        
        Sanctum::actingAs($businessUser1, ['*'], 'business');

        $notification = BusinessNotification::factory()->create([
            'tenant_id' => $tenant->id,
            'business_user_id' => $businessUser2->id,
        ]);

        $hashedId = EasyHashAction::encode($notification->id, 'notification-id');

        $response = $this->patchJson(route('business.notifications.read', ['notification_id' => $hashedId]));

        $response->assertStatus(404);
    }
}
