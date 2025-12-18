<?php

use App\Models\BusinessUser;
use App\Models\Tenant;
use App\Actions\General\EasyHashAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('can upload tenant logo', function () {
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $tenant->businessUsers()->attach($businessUser);
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    // Create valid invoice for subscription middleware
    \App\Models\Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
    ]);

    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $response = $this->actingAs($businessUser, 'business')
        ->postJson("/api/business/v1/business/{$tenantHashId}/logo", [
            'logo' => $file,
        ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['logo' => $response->json('data.logo')]);

    $this->assertNotNull($response->json('data.logo'));
});



test('can replace existing logo', function () {
    $tenant = Tenant::factory()->create([
        'logo' => '/file/old/logo.jpg'
    ]);
    $businessUser = BusinessUser::factory()->create();
    $tenant->businessUsers()->attach($businessUser);
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    \App\Models\Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
    ]);

    $file = UploadedFile::fake()->image('new-logo.jpg', 100, 100);

    $response = $this->actingAs($businessUser, 'business')
        ->postJson("/api/business/v1/business/{$tenantHashId}/logo", [
            'logo' => $file,
        ]);

    $response->assertStatus(200);

    // Verify new logo was set
    $tenant->refresh();
    expect($tenant->logo)->not->toBe('/file/old/logo.jpg');
    expect($tenant->logo)->not->toBeNull();
});


test('unauthorized user cannot upload logo', function () {
    $tenant = Tenant::factory()->create();
    $unauthorizedUser = BusinessUser::factory()->create();
    // Not attaching user to tenant
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    \App\Models\Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
    ]);

    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $response = $this->actingAs($unauthorizedUser, 'business')
        ->postJson("/api/business/v1/business/{$tenantHashId}/logo", [
            'logo' => $file,
        ]);

    $response->assertStatus(403);
});


test('can delete tenant logo', function () {
    $tenant = Tenant::factory()->create([
        'logo' => 'file/0Pa2e1K9y4bz4xRGpYBbv/logos/logo_1766088155.jpg'
    ]);
    $businessUser = BusinessUser::factory()->create();
    $tenant->businessUsers()->attach($businessUser);
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    \App\Models\Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
    ]);

    // Create a fake logo file
    $logoPath = "tenants/{$tenant->id}/logos/logo_1766088155.jpg";
    Storage::disk('public')->put($logoPath, 'fake logo content');

    $response = $this->actingAs($businessUser, 'business')
        ->deleteJson("/api/business/v1/business/{$tenantHashId}/logo");

    $response->assertStatus(200)
        ->assertJsonFragment(['logo' => null]);

    // Verify logo was removed from database
    $tenant->refresh();
    expect($tenant->logo)->toBeNull();

    // Verify file was deleted
    expect(Storage::disk('public')->exists($logoPath))->toBeFalse();
});


test('cannot delete logo that does not exist', function () {
    $tenant = Tenant::factory()->create(['logo' => null]);
    $businessUser = BusinessUser::factory()->create();
    $tenant->businessUsers()->attach($businessUser);
    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    \App\Models\Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
    ]);

    $response = $this->actingAs($businessUser, 'business')
        ->deleteJson("/api/business/v1/business/{$tenantHashId}/logo");

    $response->assertStatus(404)
        ->assertJson(['message' => 'Nenhum logo encontrado para remover']);
});
