# API Pagination Patterns

## 🎯 Objective
Efficiently handle large datasets by returning manageable chunks of data with navigation metadata.

## 🔑 Key Principles
1.  **Always Paginate Lists**: Never return `all()` for potentially large tables.
2.  **Use Resource Collections**: Wrap paginated results in API Resources.
3.  **Include Meta**: Ensure `total`, `per_page`, `current_page` are included.
4.  **Default Limits**: Enforce a default `per_page` (e.g., 15) and a max limit (e.g., 100).

## 📝 Implementation

### Standard Pagination
Using Laravel's `paginate()` with API Resources.

```php
public function index(Request $request)
{
    // 1. Query & Filter
    $query = Product::query()->where('active', true);
    
    // 2. Paginate (Default 15)
    $products = $query->paginate($request->input('per_page', 15));

    // 3. Return Collection
    return ProductResourceGeneral::collection($products);
}
```

### Response Structure
Laravel Resources automatically flatten the pagination data:
```json
{
    "data": [ ... ],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75
    }
}
```

### Custom Meta Data
Appending extra info to a paginated response.
```php
return ProductResourceGeneral::collection($products)->additional([
    'meta' => [
        'filters' => $request->only(['search', 'status']),
    ],
]);
```

## 💡 Cursor Pagination
Use for infinite scrolling or high-performance requirements (avoids `OFFSET` slowness).
```php
$users = User::orderBy('id')->cursorPaginate(15);
return UserResourceGeneral::collection($users);
```
*Note: Cursor pagination does not return total counts.*

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `User::all()` | `User::paginate(15)` |
| Allowing `per_page=10000` | Validate `per_page` (max: 100) |
| Manual array slicing | Use `paginate()` or `cursorPaginate()` |
