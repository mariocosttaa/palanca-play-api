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
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $image = UploadedFile::fake()->image('court_new.jpg');

    $response = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'image' => $image,
            'is_primary' => true,
        ]);

    $response->assertStatus(201);
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
    
    // Assert response structure
    $response->assertJsonStructure([
        'message',
        'data' => [
            'id',
            'url',
            'is_primary',
        ]
    ]);
    
    $responseData = $response->json('data');
    expect($responseData['is_primary'])->toBeTrue();
});

test('user can upload image with string boolean for is_primary', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $image = UploadedFile::fake()->image('court_string_bool.jpg');

    // Test with string "false"
    $response = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'image' => $image,
            'is_primary' => 'false', // String boolean
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('courts_images', [
        'court_id' => $court->id,
        'is_primary' => false,
    ]);

    // Test with string "true"
    $image2 = UploadedFile::fake()->image('court_string_true.jpg');
    $response2 = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'image' => $image2,
            'is_primary' => 'true', // String boolean
        ]);

    $response2->assertStatus(201);
    $this->assertDatabaseHas('courts_images', [
        'court_id' => $court->id,
        'is_primary' => true,
    ]);
});

test('user can upload image without setting as primary', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $image = UploadedFile::fake()->image('court_secondary.jpg');

    $response = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'image' => $image,
            'is_primary' => false,
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('courts_images', [
        'court_id' => $court->id,
        'is_primary' => false,
    ]);
});

test('user cannot upload invalid file type', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $invalidFile = UploadedFile::fake()->create('document.pdf', 1000); // PDF instead of image

    $response = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'image' => $invalidFile,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['image']);
});

test('user cannot upload file larger than 10MB', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $largeFile = UploadedFile::fake()->image('large_image.jpg')->size(11000); // 11MB > 10MB limit

    $response = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'image' => $largeFile,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['image']);
});

test('uploading primary image unsets other primary images', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create an existing primary image
    $existingPrimary = CourtImage::factory()->create([
        'court_id' => $court->id,
        'is_primary' => true,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $newImage = UploadedFile::fake()->image('new_primary.jpg');

    $response = $this->actingAs($user, 'business')
        ->postJson(route('courts.images.store', ['tenant_id' => $tenantHashId, 'court_id' => $courtHashId]), [
            'image' => $newImage,
            'is_primary' => true,
        ]);

    $response->assertStatus(201);
    
    // New image should be primary
    $newCourtImage = CourtImage::where('court_id', $court->id)
        ->where('id', '!=', $existingPrimary->id)
        ->first();
    expect($newCourtImage->is_primary)->toBeTrue();
    
    // Old primary should be unset
    $existingPrimary->refresh();
    expect($existingPrimary->is_primary)->toBeFalse();
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
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $imageHashId = EasyHashAction::encode($courtImage->id, 'court-image-id');

    $response = $this->actingAs($user, 'business')
        ->deleteJson(route('courts.images.destroy', [
            'tenant_id' => $tenantHashId, 
            'court_id' => $courtHashId, 
            'image_id' => $imageHashId
        ]));

    $response->assertStatus(200);
    $this->assertDatabaseMissing('courts_images', ['id' => $courtImage->id]);
    // The deletion logic expects to delete from tenants/{id}/$path
    Storage::disk('public')->assertMissing("tenants/{$tenant->id}/" . $path);
});

test('user can set image as primary', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create an existing primary image
    $existingPrimary = CourtImage::factory()->create([
        'court_id' => $court->id,
        'is_primary' => true,
    ]);
    
    // Create a secondary image
    $secondaryImage = CourtImage::factory()->create([
        'court_id' => $court->id,
        'is_primary' => false,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $imageHashId = EasyHashAction::encode($secondaryImage->id, 'court-image-id');

    $response = $this->actingAs($user, 'business')
        ->patchJson(route('courts.images.set-primary', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtHashId,
            'image_id' => $imageHashId
        ]), [
            'is_primary' => true,
        ]);

    $response->assertStatus(200);
    
    // New image should be primary
    $secondaryImage->refresh();
    expect($secondaryImage->is_primary)->toBeTrue();
    
    // Old primary should be unset
    $existingPrimary->refresh();
    expect($existingPrimary->is_primary)->toBeFalse();
    
    // Assert response structure
    $response->assertJsonStructure([
        'message',
        'data' => [
            'id',
            'url',
            'is_primary',
        ]
    ]);
    
    $responseData = $response->json('data');
    expect($responseData['is_primary'])->toBeTrue();
});

test('user can unset image as primary', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create a primary image
    $primaryImage = CourtImage::factory()->create([
        'court_id' => $court->id,
        'is_primary' => true,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');
    $imageHashId = EasyHashAction::encode($primaryImage->id, 'court-image-id');

    $response = $this->actingAs($user, 'business')
        ->patchJson(route('courts.images.set-primary', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtHashId,
            'image_id' => $imageHashId
        ]), [
            'is_primary' => false,
        ]);

    $response->assertStatus(200);
    
    // Image should no longer be primary
    $primaryImage->refresh();
    expect($primaryImage->is_primary)->toBeFalse();
    
    // Assert response structure
    $responseData = $response->json('data');
    expect($responseData['is_primary'])->toBeFalse();
});

test('images are returned with primary first', function () {
    $tenant = Tenant::factory()->create();
    $user = BusinessUser::factory()->create();
    $user->tenants()->attach($tenant);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'paid',
        'date_end' => now()->addDay(),
        'max_courts' => 10,
    ]);
    $court = Court::factory()->create(['tenant_id' => $tenant->id]);
    
    // Create images in reverse order (secondary first, then primary)
    $secondary1 = CourtImage::factory()->create([
        'court_id' => $court->id,
        'is_primary' => false,
    ]);
    
    $secondary2 = CourtImage::factory()->create([
        'court_id' => $court->id,
        'is_primary' => false,
    ]);
    
    $primary = CourtImage::factory()->create([
        'court_id' => $court->id,
        'is_primary' => true,
    ]);

    $tenantHashId = EasyHashAction::encode($tenant->id, 'tenant-id');
    $courtHashId = EasyHashAction::encode($court->id, 'court-id');

    $response = $this->actingAs($user, 'business')
        ->getJson(route('courts.show', [
            'tenant_id' => $tenantHashId,
            'court_id' => $courtHashId
        ]));

    $response->assertStatus(200);
    
    $images = $response->json('data.images');
    expect($images)->toBeArray()
        ->and(count($images))->toBe(3)
        ->and($images[0]['is_primary'])->toBeTrue() // Primary should be first
        ->and($images[1]['is_primary'])->toBeFalse()
        ->and($images[2]['is_primary'])->toBeFalse();
});
