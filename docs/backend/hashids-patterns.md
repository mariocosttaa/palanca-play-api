# Hashids Patterns

## 🎯 Objective
Obfuscate all database IDs in public API responses to prevent enumeration attacks and hide business metrics.

## 🔑 Key Principles
1.  **Never Expose Raw IDs**: All IDs sent to the frontend MUST be hashed.
2.  **Decode on Entry**: All IDs received from the frontend MUST be decoded immediately (in Middleware or Request).
3.  **Consistent Salt**: Use `{model}-id` as the salt/type (e.g., `user-id`).
4.  **Action Class**: Always use `EasyHashAction` for encoding/decoding.

## 🛠️ Implementation

### 1. Encoding (Sending to Frontend)
Use in **API Resources**.
```php
use App\Actions\General\EasyHashAction;

// Inside Resource::toArray()
'id' => EasyHashAction::encode($this->id, 'user-id'),
'category_id' => EasyHashAction::encode($this->category_id, 'category-id'),
```

### 2. Decoding (Receiving from Frontend)
Use in **Form Requests** (`prepareForValidation`).
```php
protected function prepareForValidation(): void
{
    if ($this->user_id) {
        $this->merge([
            'user_id' => EasyHashAction::decode($this->user_id, 'user-id'),
        ]);
    }
}
```

### 3. Route Binding
Models in URLs (e.g., `/api/users/{user}`) must handle decoding automatically.
```php
// App\Models\User.php

public function resolveRouteBinding($value, $field = null)
{
    $decoded = EasyHashAction::decode($value, 'user-id');
    return $this->where('id', $decoded)->firstOrFail();
}
```

## 📚 Common Hash Types
Follow the `{model}-id` convention.

| Model | Hash Type |
|-------|-----------|
| User | `user-id` |
| Product | `product-id` |
| Order | `order-id` |
| OrderItem | `order-item-id` |
| Category | `category-id` |
| Tenant | `tenant-id` |

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `EasyHashAction::encode($id)` (No type) | `EasyHashAction::encode($id, 'user-id')` |
| Mixing types (`user-id` for Order) | strict matching: Order -> `order-id` |
| Decoding in Controller | Decoding in `FormRequest` or Middleware |
| Sending integer IDs to Frontend | Sending string hashes (`Xy7z`) |
