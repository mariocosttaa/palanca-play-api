# API Resource Patterns

## 🎯 Objective
Transform database models into consistent, secure, and optimized JSON responses for the API.

## 🔑 Key Principles
1.  **Dual Structure**: Use `General` resources for lists (performance) and `Specific` for details (completeness).
2.  **Hash Everything**: All IDs must be encoded (e.g., `EasyHashAction::encode($id, 'user-id')`).
3.  **No N+1**: Never load relationships in `General` resources unless explicitly eager loaded.
4.  **Wrap Relationships**: Always wrap relationships in their corresponding Resource class (e.g., `new CountryResource(...)`).
5.  **Consistent Types**: Money as integers (cents), Dates as ISO 8601 strings.

## 📂 File Structure
```
app/Http/Resources/
├── General/   # Minimal data, optimized for lists (index)
└── Specific/  # Full data, includes relationships (show)
```

## 📝 Standard Patterns

### 1. General Resource (List View)
**Use for:** `index()`, collections, dropdowns.
**Focus:** Speed, minimal data.

```php
namespace App\Http\Resources\General;

class UserResourceGeneral extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'user-id'), // Hashed ID
            'name' => $this->name,
            'email' => $this->email,
            // Always include foreign key ID
            'country_id' => $this->country_id 
                ? EasyHashAction::encode($this->country_id, 'country-id') 
                : null,
            // Include relationship only when eager loaded (prevents N+1)
            'country' => new CountryResourceGeneral($this->whenLoaded('country')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

**Key Pattern:** Always include both the foreign key ID and the relationship object using `whenLoaded()`. This allows:
- **Without eager loading**: Only the ID is returned (fast, no extra queries)
- **With eager loading**: Full relationship object is returned (no N+1 queries)

### 2. Specific Resource (Detail View)
**Use for:** `show()`, `store()`, `update()`.
**Focus:** Completeness, relationships.

```php
namespace App\Http\Resources\Specific;

class UserResourceSpecific extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'user-id'),
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            
            // Relationships: Use General resources for children to prevent deep nesting/N+1
            'orders' => OrderResourceGeneral::collection($this->whenLoaded('orders')),
            'profile' => new ProfileResourceGeneral($this->whenLoaded('profile')),
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

## 🚀 Advanced Techniques

### Preventing N+1 Queries in General Resources
Always include both foreign key IDs and relationships using `whenLoaded()`:

```php
public function toArray(Request $request): array
{
    return [
        // Always include foreign key ID
        'country_id' => $this->country_id 
            ? EasyHashAction::encode($this->country_id, 'country-id') 
            : null,
        // Include relationship only when eager loaded
        'country' => new CountryResourceGeneral($this->whenLoaded('country')),
        
        // For collections, use collection() method
        'bookings' => BookingResourceGeneral::collection($this->whenLoaded('bookings')),
    ];
}
```

**Usage in Controllers:**
```php
// Without relationships (only IDs returned)
User::all(); // Fast, minimal data

// With relationships (full objects returned, no N+1)
User::with('country')->get(); // Eager loaded, full country object included
```

### Conditional Attributes
Include fields only when necessary or permitted.
```php
'secret' => $this->when($request->user()->isAdmin(), $this->secret_field),
'orders' => OrderResourceGeneral::collection($this->whenLoaded('orders')),
```

### ID Encoding (Hashids)
Always use `EasyHashAction`.
- Pattern: `{model-name}-id`
- Example: `user-id`, `product-id`, `order-item-id`

```php
'category_id' => $this->category_id 
    ? EasyHashAction::encode($this->category_id, 'category-id') 
    : null,
```

### Money Formatting
Return both raw value (cents) and formatted string.
```php
'price' => $this->price,           // 1000 (cents)
'price_fmt' => $this->formatted_price, // "$10.00"
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `'id' => $this->id` | `'id' => EasyHashAction::encode($this->id, 'type')` |
| `'country' => new CountryResource($this->country)` (always loads) | `'country' => new CountryResourceGeneral($this->whenLoaded('country'))` (conditional) |
| Only foreign key ID, no relationship | Both `country_id` AND `country` with `whenLoaded()` |
| Returning raw relationship model/array | Wrapping relationship in `new Resource(...)` or `Resource::collection(...)` |
| Nested Specific Resources | Nested **General** Resources (prevents infinite loops) |
| Formatting dates in JS format | `$this->created_at->toISOString()` |
| Logic/Calculations in Resource | Calculate in Model/Accessor, display in Resource |
| Loading relationships without eager loading | Use `whenLoaded()` to prevent N+1 queries |
