<?php

namespace Tests\Feature\Api\Business;

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_create_courts_within_limit()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        
        // Create subscription plan with limit of 2 courts
        SubscriptionPlan::factory()->create([
            'tenant_id' => $tenant->id,
            'courts' => 2,
        ]);

        // Create a valid invoice for the tenant with max_courts = 2
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_end' => now()->addDay(),
            'max_courts' => 2,
        ]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

        $courtType = \App\Models\CourtType::factory()->create(['tenant_id' => $tenant->id]);

        $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

        // Create 1st court
        $response1 = $this->actingAs($user, 'business')
            ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
                'name' => 'Court 1',
                'number' => 1,
                'court_type_id' => $courtTypeHashId,
            ]);
        $response1->assertStatus(200);

        // Create 2nd court
        $response2 = $this->actingAs($user, 'business')
            ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
                'name' => 'Court 2',
                'number' => 2,
                'court_type_id' => $courtTypeHashId,
            ]);
        $response2->assertStatus(200);

        // Try to create 3rd court (should fail)
        $response3 = $this->actingAs($user, 'business')
            ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
                'name' => 'Court 3',
                'number' => 3,
                'court_type_id' => $courtTypeHashId,
            ]);
        $response3->assertStatus(403);
        $response3->assertJson(['message' => 'Limite de quadras atingido para o seu plano de subscrição.']);
    }

    public function test_tenant_without_invoice_cannot_create_courts()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        $courtType = \App\Models\CourtType::factory()->create(['tenant_id' => $tenant->id]);

        $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
        $courtTypeHashId = EasyHashAction::encode($courtType->id, 'court-type-id');

        // Try to create a court without invoice
        $this->actingAs($user, 'business')
            ->postJson(route('courts.create', ['tenant_id' => $tenantHashId]), [
                'name' => "Court 1",
                'number' => 1,
                'court_type_id' => $courtTypeHashId,
            ])->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED']);
    }
}
