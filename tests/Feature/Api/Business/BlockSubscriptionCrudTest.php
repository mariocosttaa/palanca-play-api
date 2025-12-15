<?php

namespace Tests\Feature\Api\Business;

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockSubscriptionCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_requests_allowed_with_expired_subscription()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create an expired invoice
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->subDay(),
            'max_courts' => 10,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        // GET request should be allowed (200 OK)
        $response = $this->getJson(route('tenant.show', ['tenant_id' => $tenantHashId]));
        $response->assertStatus(200);
    }

    public function test_post_requests_blocked_with_expired_subscription()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create an expired invoice
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->subDay(),
            'max_courts' => 10,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        // POST request should be blocked (403 Forbidden)
        // Using court creation as an example
        $response = $this->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
            'name' => 'Court 1',
            'number' => 1,
            // other required fields...
        ]);

        $response->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
    }

    public function test_put_requests_blocked_with_expired_subscription()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create an expired invoice
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->subDay(),
            'max_courts' => 10,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
        
        // Create a court to try updating
        $court = \App\Models\Court::factory()->create(['tenant_id' => $tenant->id]);
        $courtHashId = EasyHashAction::encode($court->id, 'court-id');

        Sanctum::actingAs($user, [], 'business');

        // PUT request should be blocked (403 Forbidden)
        $response = $this->putJson(route('courts.update', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'name' => 'Updated Court',
        ]);

        $response->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
    }

    public function test_delete_requests_blocked_with_expired_subscription()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create an expired invoice
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->subDay(),
            'max_courts' => 10,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
        
        // Create a court to try deleting
        $court = \App\Models\Court::factory()->create(['tenant_id' => $tenant->id]);
        $courtHashId = EasyHashAction::encode($court->id, 'court-id');

        Sanctum::actingAs($user, [], 'business');

        // DELETE request should be blocked (403 Forbidden)
        $response = $this->deleteJson(route('courts.destroy', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]));

        $response->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
    }

    public function test_tenant_profile_update_allowed_with_expired_subscription()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create an expired invoice
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->subDay(),
            'max_courts' => 10,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        // PUT request to tenant.update should be allowed (200 OK)
        $response = $this->putJson(route('tenant.update', ['tenant_id' => $tenantHashId]), [
            'name' => 'Updated Tenant Name',
            'country_id' => 1,
            'address' => 'Test Address',
            'currency' => 'usd',
            'timezone' => 'UTC',
            'auto_confirm_bookings' => true,
            'booking_interval_minutes' => 60,
            'buffer_between_bookings_minutes' => 0,
        ]);

        $response->assertStatus(200);
    }

    public function test_crud_allowed_with_valid_subscription()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create a valid invoice
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->addDay(),
            'max_courts' => 10,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        // POST request should be allowed (200 OK or 201 Created)
        // We need a valid court type for court creation
        $courtType = \App\Models\CourtType::factory()->create(['tenant_id' => $tenant->id]);
        $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

        $response = $this->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
            'name' => 'Court 1',
            'number' => 1,
            'court_type_id' => $courtTypeHashId,
        ]);

        $response->assertStatus(200);
    }
}
