# API Resource Patterns

## 🎯 Objective
Transform database models into consistent, secure, and optimized JSON responses for the API.

## 🔑 Key Principles
1.  **Dual Structure**: Use `General` resources for lists (performance) and `Specific` for details (completeness).
2.  **Hash Everything**: All IDs must be encoded (e.g., `EasyHashAction::encode($id, 'user-id')`).
3.  **No N+1**: Never load relationships in `General` resources unless explicitly eager loaded.
4.  **Consistent Types**: Money as integers (cents), Dates as ISO 8601 strings.

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
            'status_label' => $this->status_label,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

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
| Nested Specific Resources | Nested **General** Resources (prevents infinite loops) |
| Formatting dates in JS format | `$this->created_at->toISOString()` |
| Logic/Calculations in Resource | Calculate in Model/Accessor, display in Resource |
