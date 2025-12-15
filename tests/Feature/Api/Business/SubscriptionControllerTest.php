<?php

namespace Tests\Feature\Api\Business;

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_can_get_invoices()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create invoices
        Invoice::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        $response = $this->getJson(route('subscriptions.invoices', ['tenant_id' => $tenantHashId]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'period',
                        'date_start',
                        'date_end',
                        'price',
                        'max_courts',
                        'status',
                        'created_at',
                    ]
                ]
            ]);
    }

    public function test_business_user_can_get_current_subscription_details_active()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create active invoice
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->addDays(30),
            'max_courts' => 5,
        ]);

        // Create some courts
        Court::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        $response = $this->getJson(route('subscriptions.current', ['tenant_id' => $tenantHashId]));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => 'active',
                    'max_courts' => 5,
                    'current_courts' => 2,
                    'days_remaining' => 29, // approx
                ]
            ]);
    }

    public function test_business_user_can_get_current_subscription_details_expired()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create expired invoice
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->subDay(),
            'max_courts' => 5,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        $response = $this->getJson(route('subscriptions.current', ['tenant_id' => $tenantHashId]));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => 'expired',
                    'days_remaining' => 0,
                ]
            ]);
    }

    public function test_business_user_can_get_current_subscription_details_none()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // No invoices

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, [], 'business');

        $response = $this->getJson(route('subscriptions.current', ['tenant_id' => $tenantHashId]));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'status' => 'none',
                    'max_courts' => 0,
                    'invoice' => null,
                ]
            ]);
    }
}
