# API Controller Patterns

## 🎯 Objective
Standardize Laravel API controllers to ensure consistent RESTful responses, secure data handling, and clean separation of concerns.

## 🔑 Key Principles
1.  **No Business Logic**: Controllers should delegate to Models or Actions.
2.  **Always Use Resources**: Never return Models directly; use API Resources (`General` for lists, `Specific` for details).
3.  **Organize Data**: Prepare variables before the return statement.
4.  **Try-Catch Blocks**: Wrap state-changing operations (Store, Update, Delete) in try-catch.
5.  **Secure Inputs**: Never use `$request->validated()` directly; access properties explicitly.

## 📂 Structure & Naming
- **Namespace**: `App\Http\Controllers\Api`
- **Naming**: `{Entity}Controller` (e.g., `ProductController`)
- **Inheritance**: Extend `App\Http\Controllers\Controller` (or a Base `ApiController`).

## 📝 Standard Actions

### 1. Index (List)
- **Purpose**: Return paginated list of resources.
- **Resource**: `ResourceGeneral`.
- **Pattern**: Filter -> Sort -> Paginate -> Return.

```php
public function index(Request $request)
{
    $query = Entity::with(['relation'])->where('tenant_id', $request->attributes->get('tenant_id'));
    
    // Apply Filters
    $query->when($request->status, fn($q) => $q->where('status', $request->status));

    $entities = $query->orderBy('created_at', 'desc')->paginate(15);

    return EntityResourceGeneral::collection($entities);
}
```

### 2. Show (Detail)
- **Purpose**: Return single resource with details.
- **Resource**: `ResourceSpecific`.
- **Pattern**: Load Relations -> Return.

```php
public function show(Entity $entity)
{
    $this->authorize('view', $entity);
    $entity->load(['details', 'history']);
    
    return new EntityResourceSpecific($entity);
}
```

### 3. Store (Create)
- **Purpose**: Validate and create new record.
- **Pattern**: Try-Catch -> Create -> Return 201.
- **Note**: `tenant_id` comes from attributes, not request body.

```php
public function store(StoreEntityRequest $request)
{
    try {
        $entity = Entity::create([
            'tenant_id' => $request->attributes->get('tenant_id'),
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return (new EntityResourceSpecific($entity))
            ->response()
            ->setStatusCode(201);
            
    } catch (\Exception $e) {
        Log::error('Entity creation failed: ' . $e->getMessage());
        return response()->json(['message' => 'Failed to create entity.'], 500);
    }
}
```

### 4. Update (Edit)
- **Purpose**: Validate and modify existing record.
- **Pattern**: Authorize -> Try-Catch -> Update -> Return Updated Resource.

```php
public function update(UpdateEntityRequest $request, Entity $entity)
{
    $this->authorize('update', $entity);

    try {
        $entity->update([
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return new EntityResourceSpecific($entity);
        
    } catch (\Exception $e) {
        Log::error('Entity update failed: ' . $e->getMessage());
        return response()->json(['message' => 'Failed to update entity.'], 500);
    }
}
```

### 5. Destroy (Delete)
- **Purpose**: Remove record.
- **Pattern**: Authorize -> Try-Catch -> Delete -> Return 204 No Content.

```php
public function destroy(Entity $entity)
{
    $this->authorize('delete', $entity);

    try {
        $entity->delete();
        return response()->json(null, 204);
    } catch (\Exception $e) {
        Log::error('Entity deletion failed: ' . $e->getMessage());
        return response()->json(['message' => 'Failed to delete entity.'], 500);
    }
}
```

## ⚠️ Anti-Patterns (Do Not Do This)

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `return Entity::all();` | `return EntityResourceGeneral::collection($entities);` |
| `$request->validated()` | `$request->name`, `$request->email` (Explicit properties) |
| `Entity::create($request->all())` | `Entity::create(['name' => $request->name, ...])` |
| Logic in Controller | Logic in `Action` class or Model method |
| Return 200 for Create | Return 201 Created |
| Exposing `$e->getMessage()` | `Log::error($e);` return generic message |

## 💡 Advanced Patterns

### Custom Meta Data
Return additional data alongside the resource collection.
```php
    return response()->json([
        'data' => EntityResourceGeneral::collection($entities),
        'meta' => [
        'stats' => ['total_active' => 100],
        ],
    ]);
```

### Multi-Tenant Scope
Always scope queries by tenant if applicable.
```php
$query->where('tenant_id', $request->attributes->get('tenant_id'));
```
