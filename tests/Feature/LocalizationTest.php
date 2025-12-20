<?php

namespace Tests\Feature;

use App\Enums\CourtTypeEnum;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_court_type_enum_translation()
    {
        App()->setLocale('en');
        $this->assertEquals('Football', CourtTypeEnum::FOOTBALL->label());

        App()->setLocale('pt');
        $this->assertEquals('Futebol', CourtTypeEnum::FOOTBALL->label());
    }

    public function test_validation_messages_translation()
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\BusinessUser::factory()->create();
        $tenant->businessUsers()->attach($user);
        
        // Create valid invoice
        \App\Models\Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_start' => now()->subDay(),
            'date_end' => now()->addMonth(),
            'max_courts' => 10
        ]);
        
        $tenantIdHash = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, ['*'], 'business');

        // Test English
        $response = $this->postJson(route('courts.create', ['tenant_id' => $tenantIdHash]), [], ['Accept-Language' => 'en']);
        
        $response->assertStatus(422);
        $this->assertStringContainsString('required', $response->json('errors.name.0'));

        // Test Portuguese
        $responsePt = $this->postJson(route('courts.create', ['tenant_id' => $tenantIdHash]), [], ['Accept-Language' => 'pt']);
        
        $responsePt->assertStatus(422);
        $this->assertStringContainsString('obrigatório', $responsePt->json('errors.name.0'));
    }

    public function test_user_preference_overrides_header()
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\BusinessUser::factory()->create(['locale' => \App\Enums\LocaleEnum::PT]);
        $tenant->businessUsers()->attach($user);
        
        // Create valid invoice
        \App\Models\Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_start' => now()->subDay(),
            'date_end' => now()->addMonth(),
            'max_courts' => 10
        ]);
        
        $tenantIdHash = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, ['*'], 'business');

        // Send header 'en', but user has 'pt'. Should return PT.
        $response = $this->postJson(route('courts.create', ['tenant_id' => $tenantIdHash]), [], ['Accept-Language' => 'en']);
        
        $response->assertStatus(422);
        $this->assertStringContainsString('obrigatório', $response->json('errors.name.0'));
    }

    public function test_api_response_messages_translation()
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\BusinessUser::factory()->create();
        $tenant->businessUsers()->attach($user);
        
        // Create valid invoice
        \App\Models\Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'paid',
            'date_start' => now()->subDay(),
            'date_end' => now()->addMonth(),
            'max_courts' => 10
        ]);
        
        $tenantIdHash = \App\Actions\General\EasyHashAction::encode($tenant->id, 'tenant-id');

        Sanctum::actingAs($user, ['*'], 'business');

        // Create a court
        $court = Court::factory()->create(['tenant_id' => $tenant->id]);
        $courtIdHash = \App\Actions\General\EasyHashAction::encode($court->id, 'court-id');
        
        $court->delete();
        
        // Now try to update it - English
        $response = $this->putJson(route('courts.update', ['tenant_id' => $tenantIdHash, 'court_id' => $courtIdHash]), [
            'name' => 'New Name',
            'court_type_id' => 1,
            'number' => 1
        ], ['Accept-Language' => 'en']);
        
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Court not found.']);
        
        // Now in Portuguese  
        Sanctum::actingAs($user, ['*'], 'business');
        $responsePt = $this->withHeaders(['Accept-Language' => 'pt'])
            ->putJson(route('courts.update', ['tenant_id' => $tenantIdHash, 'court_id' => $courtIdHash]), [
                'name' => 'New Name',
                'court_type_id' => 1,
                'number' => 1
            ]);
        
        $responsePt->assertStatus(404);
        $responsePt->assertJson(['message' => 'Quadra não encontrada.']);
    }
}
