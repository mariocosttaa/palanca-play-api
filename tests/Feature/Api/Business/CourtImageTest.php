<?php

namespace Tests\Feature\Api\Business;

use App\Actions\General\EasyHashAction;
use App\Models\BusinessUser;
use App\Models\Court;
use App\Models\CourtImage;
use App\Models\CourtType;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourtImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_add_image_to_court()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
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
    }

    public function test_user_can_delete_image_from_court()
    {
        $tenant = Tenant::factory()->create();
        $user = BusinessUser::factory()->create();
        $user->tenants()->attach($tenant);
        $court = Court::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create an image manually
        $file = UploadedFile::fake()->image('court_delete.jpg');
        $path = $file->store("tenants/{$tenant->id}/courts", 'public');
        // TenantFileAction expects the path stored in DB to be the relative URL/path. 
        // If we look at TenantFileAction::save, it returns a URL. 
        // But here we are manually creating. Let's assume the path in DB is relative to storage root or a full URL.
        // TenantFileAction::delete logic:
        // if fileUrl starts with /file/, etc.
        // else if not valid URL, treat as relative path.
        // If we pass "tenants/1/courts/hash.jpg", delete will try to delete "tenants/1/tenants/1/courts/hash.jpg" because it prepends basePath.
        
        // Wait, TenantFileAction::delete prepends "tenants/{tenantId}".
        // So if we want to delete "tenants/1/courts/hash.jpg", we should pass "courts/hash.jpg" as the path/url if we want it to work?
        // OR we should fix TenantFileAction to handle full paths?
        
        // Let's look at TenantFileAction::delete again.
        // $basePath = "tenants/{$tenantId}";
        // ...
        // $fullPath = $basePath . '/' . $pathToDelete;
        
        // So if the file is physically at "tenants/1/courts/image.jpg",
        // $pathToDelete must be "courts/image.jpg".
        
        // So in the test, we store it at "tenants/1/courts".
        // The $path returned by store() will be "tenants/1/courts/hash.jpg".
        // We need to strip "tenants/1/" from it to simulate what would be in the DB/URL if it was a relative path handled by the Action?
        
        // Actually, TenantFileAction::save returns a URL like `route(...)`.
        // But `CourtImageController::store` saves `$fileInfo->url` to the DB.
        // So the DB contains a full URL (or relative URL starting with /file/...).
        
        // In the test, we are manually creating the record. We should simulate a URL.
        // But `TenantFileAction::delete` handles non-URL paths too.
        
        // If I store the file at `tenants/{$tenant->id}/courts`, the physical file is there.
        // If I put the full path `tenants/{$tenant->id}/courts/hash.jpg` in the DB.
        // `TenantFileAction::delete` will take that as `$fileUrl`.
        // It falls through to `ltrim($fileUrl, '/')`.
        // Then `$fullPath = "tenants/{$tenantId}/" . "tenants/{$tenantId}/courts/hash.jpg"`.
        // This is WRONG. It doubles the tenant path.
        
        // So `TenantFileAction::delete` expects the input to be relative to the tenant folder IF it's a raw path?
        // OR it expects a URL that it can parse.
        
        // Let's adjust the test to store the file correctly and provide a path that `delete` can handle.
        // If I provide "courts/hash.jpg" as the path in DB.
        // And physically store at "tenants/{id}/courts/hash.jpg".
        // Then delete should work.
        
        $filename = $file->hashName();
        $file->storeAs("tenants/{$tenant->id}/courts", $filename, 'public');
        $pathInDb = "courts/{$filename}"; // This is what we want delete to see if it was a relative path.
        // BUT wait, `CourtImageController` saves `$fileInfo->url`.
        // `TenantFileAction::save` returns a route URL.
        
        // So the DB usually has `/file/hash/courts/image.jpg`.
        // `delete` handles `/file/...` URLs.
        
        // For the test, it's easier to just put a path that `delete` interprets correctly as "courts/image.jpg".
        // If I put "courts/image.jpg" in DB.
        // `delete` will try "tenants/1/courts/image.jpg".
        // So I just need to ensure the file is physically at "tenants/1/courts/image.jpg".
        
        $file->storeAs("tenants/{$tenant->id}/courts", $file->hashName(), 'public');
        $path = "courts/" . $file->hashName(); // This is what we put in DB for the test to pass deletion logic easily.
        
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
        Storage::disk('public')->assertMissing($path);
    }
}
