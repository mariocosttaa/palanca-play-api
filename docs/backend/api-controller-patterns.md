# API Controller Patterns

## 🎯 Objective
Standardize Laravel API controllers to ensure consistent RESTful responses, secure data handling, and clean separation of concerns.

## 🔑 Key Principles
1.  **No Business Logic**: Controllers should delegate to Models or Actions.
2.  **Always Use Resources**: Never return Models directly; use API Resources (`General` for lists, `Specific` for details).
3.  **MANDATORY: Always Resolve Resources**: Always call `->resolve()` on resources before returning to prevent extra data from leaking to the frontend. Use `$this->dataResponse()` with resolved data.
4.  **Organize Data**: Prepare variables before the return statement.
5.  **Try-Catch Blocks**: Wrap state-changing operations (Store, Update, Delete) in try-catch.
6.  **Secure Inputs**: Never use `$request->validated()` directly; access properties explicitly.
7.  **Use Controller Helpers**: Always use `$this->errorResponse()`, `$this->successResponse()`, and `$this->dataResponse()` instead of manually building JSON responses or using `Log::error()`.

## 📂 Structure & Naming
- **Namespace**: `App\Http\Controllers\Api`
- **Naming**: `{Entity}Controller` (e.g., `ProductController`)
- **Inheritance**: Extend `App\Http\Controllers\Controller` (or a Base `ApiController`).

## 📝 Standard Actions

### 1. Index (List)
- **Purpose**: Return paginated list of resources.
- **Resource**: `ResourceGeneral`.
- **Pattern**: Filter -> Sort -> Paginate -> Resolve -> Return.

```php
public function index(Request $request)
{
    try {
        $query = Entity::with(['relation'])->where('tenant_id', $request->attributes->get('tenant_id'));
        
        // Apply Filters
        $query->when($request->status, fn($q) => $q->where('status', $request->status));

        $entities = $query->orderBy('created_at', 'desc')->paginate(15);

        return $this->dataResponse(
            EntityResourceGeneral::collection($entities)->resolve()
        );
    } catch (\Exception $e) {
        return $this->errorResponse('Failed to retrieve entities.', $e->getMessage(), 500);
    }
}
```

### 2. Show (Detail)
- **Purpose**: Return single resource with details.
- **Resource**: `ResourceSpecific`.
- **Pattern**: Authorize -> Load Relations -> Resolve -> Return.

```php
public function show(Entity $entity)
{
    try {
        $this->authorize('view', $entity);
        $entity->load(['details', 'history']);
        
        return $this->dataResponse(
            (new EntityResourceSpecific($entity))->resolve()
        );
    } catch (\Exception $e) {
        return $this->errorResponse('Failed to retrieve entity.', $e->getMessage(), 500);
    }
}
```

### 3. Store (Create)
- **Purpose**: Validate and create new record.
- **Pattern**: Try-Catch -> Begin Transaction -> Create -> Commit -> Resolve -> Return 201.
- **Note**: `tenant_id` comes from attributes, not request body.
- **Transaction**: Use safe transaction methods for SQLite test compatibility.

```php
public function store(StoreEntityRequest $request)
{
    try {
        $this->beginTransactionSafe();

        $entity = Entity::create([
            'tenant_id' => $request->attributes->get('tenant_id'),
            'name' => $request->name,
            'status' => $request->status,
        ]);

        $this->commitSafe();

        return $this->dataResponse(
            (new EntityResourceSpecific($entity))->resolve(),
            201
        );
            
    } catch (\Exception $e) {
        $this->rollBackSafe();
        return $this->errorResponse('Failed to create entity.', $e->getMessage(), 500);
    }
}
```

### 4. Update (Edit)
- **Purpose**: Validate and modify existing record.
- **Pattern**: Authorize -> Try-Catch -> Begin Transaction -> Update -> Commit -> Resolve -> Return Updated Resource.
- **Transaction**: Use safe transaction methods for SQLite test compatibility.

```php
public function update(UpdateEntityRequest $request, Entity $entity)
{
    $this->authorize('update', $entity);

    try {
        $this->beginTransactionSafe();

        $entity->update([
            'name' => $request->name,
            'status' => $request->status,
        ]);

        $this->commitSafe();

        return $this->dataResponse(
            (new EntityResourceSpecific($entity))->resolve()
        );
        
    } catch (\Exception $e) {
        $this->rollBackSafe();
        return $this->errorResponse('Failed to update entity.', $e->getMessage(), 500);
    }
}
```

### 5. Destroy (Delete)
- **Purpose**: Remove record.
- **Pattern**: Authorize -> Try-Catch -> Begin Transaction -> Delete -> Commit -> Return Success Response.
- **Transaction**: Use safe transaction methods for SQLite test compatibility.

```php
public function destroy(Entity $entity)
{
    $this->authorize('delete', $entity);

    try {
        $this->beginTransactionSafe();

        $entity->delete();

        $this->commitSafe();

        return $this->successResponse('Entity deleted successfully');
    } catch (\Exception $e) {
        $this->rollBackSafe();
        return $this->errorResponse('Failed to delete entity.', $e->getMessage(), 500);
    }
}
```

**Note**: While REST standard suggests `204 No Content` for delete operations, using `$this->successResponse()` with a message provides better user feedback. For strict REST compliance, you can use `response()->json(null, 204)` instead.

## ⚠️ Anti-Patterns (Do Not Do This)

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `return Entity::all();` | `return $this->dataResponse(EntityResourceGeneral::collection($entities)->resolve());` |
| `return EntityResourceGeneral::collection($entities);` | `return $this->dataResponse(EntityResourceGeneral::collection($entities)->resolve());` |
| `return new EntityResourceSpecific($entity);` | `return $this->dataResponse((new EntityResourceSpecific($entity))->resolve());` |
| `$request->validated()` | `$request->name`, `$request->email` (Explicit properties) |
| `Entity::create($request->all())` | `Entity::create(['name' => $request->name, ...])` |
| Logic in Controller | Logic in `Action` class or Model method |
| Return 200 for Create | Return 201 Created |
| `Log::error()` + manual JSON response | `$this->errorResponse($message, $errorLog, $status)` |
| `response()->json(['message' => ...])` | `$this->errorResponse()` or `$this->successResponse()` |
| `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()` | `$this->beginTransactionSafe()` / `$this->commitSafe()` / `$this->rollBackSafe()` |

## 💡 Advanced Patterns

### Custom Meta Data
Return additional data alongside the resource collection.
```php
    return response()->json([
        'data' => EntityResourceGeneral::collection($entities)->resolve(),
        'meta' => [
            'stats' => ['total_active' => 100],
        ],
    ]);
```

### Why Resolve is Mandatory
**CRITICAL**: Always call `->resolve()` on resources before returning them. This ensures:
- Only the data defined in `toArray()` is sent to the frontend
- No internal Laravel Resource metadata leaks through
- Consistent response structure using `dataResponse()` helper
- Better security by controlling exactly what data is exposed

**Never return resources directly** - always resolve them first:
```php
// ❌ BAD - Resource object may leak metadata
return EntityResourceGeneral::collection($entities);

// ✅ GOOD - Only resolved array data is returned
return $this->dataResponse(EntityResourceGeneral::collection($entities)->resolve());
```

### Database Transactions

**CRITICAL**: Always use safe transaction methods when performing state-changing operations (Create, Update, Delete).

The base `Controller` class provides three safe transaction methods that handle SQLite nested transaction issues in tests:

- `$this->beginTransactionSafe()` - Only starts a transaction if not already in one
- `$this->commitSafe()` - Only commits if we started the transaction
- `$this->rollBackSafe()` - Only rolls back if we started the transaction

**Why Safe Transactions?**
- In production (MySQL): Works normally - starts and commits transactions as expected
- In tests (SQLite): Detects existing test transaction and skips starting a new one, preventing nested transaction errors

**Pattern for State-Changing Operations:**
```php
try {
    $this->beginTransactionSafe();
    
    // Your database operations here...
    
    $this->commitSafe();
    return $this->dataResponse($data);
} catch (\Exception $e) {
    $this->rollBackSafe();
    return $this->errorResponse('Error message');
}
```

**Important Notes:**
- Always call `rollBackSafe()` before returning early (e.g., validation errors, not found errors)
- Never use `DB::beginTransaction()`, `DB::commit()`, or `DB::rollBack()` directly in controllers
- These methods are available in all controllers that extend `App\Http\Controllers\Controller`

### Controller Helper Methods

The base `Controller` class provides standardized response methods that should always be used:

#### `$this->errorResponse(string $message, ?string $errorlog = null, int $status = 400)`
Returns a standardized error response and automatically logs errors if `$errorlog` is provided.

```php
// Simple error response
return $this->errorResponse('Resource not found', null, 404);

// Error response with logging
return $this->errorResponse('Failed to create entity.', $e->getMessage(), 500);
```

**Benefits:**
- Automatically logs errors when `$errorlog` parameter is provided
- Consistent error response format across all endpoints
- No need to manually call `Log::error()` or build JSON responses

#### `$this->successResponse(string $message, int $status = 200)`
Returns a standardized success message response.

```php
return $this->successResponse('Entity deleted successfully', 200);
```

#### `$this->dataResponse(mixed $data, int $status = 200)`
Returns a standardized data response with the `data` key.

```php
return $this->dataResponse(EntityResourceGeneral::collection($entities)->resolve(), 200);
```

**Important:**
- Always use these helper methods instead of manually building JSON responses
- The `errorResponse()` method automatically handles logging when you pass the error message as the second parameter
- Never use `Log::error()` directly in controllers - pass the error to `errorResponse()` instead

### Multi-Tenant Scope
Always scope queries by tenant if applicable.
```php
$query->where('tenant_id', $request->attributes->get('tenant_id'));
```
