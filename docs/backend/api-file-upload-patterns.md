# API File Upload Patterns

## 🎯 Objective
Securely handle file uploads via API, store them efficiently (Cloud/Local), and return accessible URLs in JSON resources using `TenantFileAction`.

## 🔑 Key Principles
1.  **Validation**: Always validate Mime Types (`mimes:jpg,png`) and Max Size (`max:10240`).
2.  **Action-Based**: Use `TenantFileAction` for consistent file storage logic.
3.  **Resources**: Return the full URL in the API Resource, not the file path.
4.  **Multipart/Form-Data**: Client must send headers `Content-Type: multipart/form-data`.
5.  **Transactional**: Wrap file upload + DB creation in a `DB::transaction`.

## 📝 Standard Pattern

### 1. Form Request Validation
Define rules and messages. Never rely on the frontend alone.

```php
namespace App\Http\Requests\Api\Entity;

use Illuminate\Foundation\Http\FormRequest;

class EntityStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:10240'], // 10MB
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
```

### 2. Controller Logic
Use `TenantFileAction` and `EasyHashAction` inside a Transaction.

```php
public function store(EntityStoreRequest $request)
{
    try {
        return DB::transaction(function () use ($request) {
            // 1. Get Tenant ID (from middleware/attributes)
            $tenantId = $request->attributes->get('tenant_id');
            
            // 2. Upload File using Action
            $fileData = TenantFileAction::save(
                $tenantId,
                $request->file('file'),
                isPublic: true,
                folder: 'entity-folder'
            );

            // 3. Create Record
            $entity = Entity::create([
                'tenant_id' => $tenantId,
                'title' => $request->title,
                'src' => $fileData->url,       // Store URL/Path
                'type' => $fileData->type,     // Store MIME type
                'size' => $fileData->size,     // Store Size
            ]);

            return (new EntityResourceSpecific($entity))
                ->response()
                ->setStatusCode(201);
        });

    } catch (\Exception $e) {
        Log::error('Upload failed: ' . $e->getMessage());
        // Cleanup file if DB fails (Optional implementation in catch)
        return response()->json(['message' => 'Failed to create entity.'], 500);
    }
}
```

### 3. Update Logic (Replacing Files)
Handle optional file replacement and cleanup of old files.

```php
public function update(EntityUpdateRequest $request, Entity $entity)
{
    try {
        return DB::transaction(function () use ($request, $entity) {
            $tenantId = $request->attributes->get('tenant_id');
            $data = $request->validated();

            if ($request->hasFile('file')) {
                // 1. Delete Old File
                if ($entity->src) {
                    TenantFileAction::delete($tenantId, null, $entity->src, isPublic: true);
                }

                // 2. Upload New File
                $fileData = TenantFileAction::save(
                    $tenantId,
                    $request->file('file'),
                    isPublic: true,
                    folder: 'entity-folder'
                );

                $data['src'] = $fileData->url;
                $data['type'] = $fileData->type;
                $data['size'] = $fileData->size;
            }

            $entity->update($data);

            return new EntityResourceSpecific($entity);
        });

    } catch (\Exception $e) {
        Log::error('Update failed: ' . $e->getMessage());
        return response()->json(['message' => 'Failed to update entity.'], 500);
    }
}
```

### 4. Deletion Logic
Clean up files when deleting the record.

```php
public function destroy(Entity $entity)
{
    try {
        DB::transaction(function () use ($entity) {
            if ($entity->src) {
                TenantFileAction::delete(
                    $entity->tenant_id, 
                    null, 
                    $entity->src, 
                    isPublic: true
                );
            }
            $entity->delete();
        });

        return response()->json(null, 204);

    } catch (\Exception $e) {
        Log::error('Deletion failed: ' . $e->getMessage());
        return response()->json(['message' => 'Failed to delete entity.'], 500);
    }
}
```

## 🛡️ Security Checks
- **File Types**: Restrict to safe extensions (`images`, `pdf`).
- **File Names**: `TenantFileAction` should generate unique hashes (UUID) to prevent overwrites.
- **Private Files**: For invoices/contracts, use `isPublic: false` and generate Signed URLs in the Resource.

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Storing files in `public/uploads` manually | Use `TenantFileAction` (Storage abstraction) |
| Saving original filenames | Use generated hashes (prevents overwrites) |
| Returning raw paths (`avatars/xyz.jpg`) | Return full URLs in Resources |
| Forgetting DB Transactions | Wrap Upload + Create in `DB::transaction` |
