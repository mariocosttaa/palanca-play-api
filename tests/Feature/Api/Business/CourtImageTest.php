<?php

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtImage;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('user can add image to court', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $image = UploadedFile::fake()->image('court_new.jpg');

    $response = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $court->id]), [
            'image' => $image,
            'is_primary' => true,
        ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('courts_images', [
        'court_id' => $court->id,
        'is_primary' => true,
    ]);

    $courtImage = CourtImage::where('court_id', $court->id)->first();
    
    // The path in DB is a URL like file/{hash}/courts/{filename}
    // We need to extract the filename to check physical existence
    $parts = explode('/', $courtImage->path);
    $filename = end($parts);
    
    Storage::disk('public')->assertExists("tenants/{$tenant->id}/courts/{$filename}");
});

test('user can delete image from court', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    // Create a valid invoice for the tenant
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create an image manually
    $file = UploadedFile::fake()->image('court_delete.jpg');
    // Store manually to simulate existing file
    $file->storeAs("tenants/{$tenant->id}/courts", $file->hashName(), 'public');
    $path = "courts/" . $file->hashName(); // Path relative to tenant folder for deletion logic
    
    $courtImage = CourtImage::create([
        'court_id' => $court->id,
        'path' => $path,
        'is_primary' => false,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');

    $response = $this->actingAs($user, 'business')
        ->deleteJson(route('courts.images.destroy', [
            'tenant_id' => $tenantHashId, 
            'court_id' => $court->id, 
            'image_id' => $courtImage->id
        ]));

    $response->assertStatus(200);
    $this->assertDatabaseMissing('courts_images', ['id' => $courtImage->id]);
    // The deletion logic expects to delete from tenants/{id}/$path
    Storage::disk('public')->assertMissing("tenants/{$tenant->id}/" . $path);
});
