<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessNotification;
use App\Models\BusinessUser;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('business user can get recent notifications', function () {
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
});

test('business user can get all notifications with pagination', function () {
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
});

test('business user can mark notification as read', function () {
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
});

test('business user cannot access other users notifications', function () {
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
});
