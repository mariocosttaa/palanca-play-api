# API Versioning Patterns

## 🎯 Objective
Maintain backward compatibility for existing clients while allowing the API to evolve over time.

## 🔑 Key Principles
1.  **URL Versioning**: Use `/api/v1/...`, `/api/v2/...`. It is explicit, cache-friendly, and easy to debug.
2.  **Namespace Isolation**: Separate Controllers and Resources by version (`App\Http\Controllers\Api\V1`).
3.  **Deprecation Policy**: Announce deprecation headers before shutting down a version.
4.  **No Breaking Changes**: Never change the response structure of a live version. Create a new version instead.

## 📝 Standard Pattern

### 1. Directory Structure
Keep versions physically separated.
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── V1/
│   │   │   │   └── UserController.php
│   │   │   └── V2/
│   │   │       └── UserController.php
```

### 2. Route Definition (`routes/api.php`)
Group routes by prefix and namespace.
```php
// V1 Routes
Route::prefix('v1')
    ->namespace('App\Http\Controllers\Api\V1')
    ->group(function () {
        Route::apiResource('users', 'UserController');
    });

// V2 Routes
Route::prefix('v2')
    ->namespace('App\Http\Controllers\Api\V2')
    ->group(function () {
        Route::apiResource('users', 'UserController');
    });
```

### 3. Handling Evolution
When V2 is needed (e.g., `name` splits into `first_name` + `last_name`):
1.  Copy `V1\UserController` to `V2\UserController`.
2.  Copy `V1\UserResource` to `V2\UserResource`.
3.  Modify V2 logic.
4.  Leave V1 logic **untouched**.

### 4. Resources Versioning
Even Resources should be versioned if the output structure changes.
```php
namespace App\Http\Resources\V1;
class UserResource extends JsonResource { ... }

namespace App\Http\Resources\V2;
class UserResource extends JsonResource { ... }
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Header Versioning (`Accept: application/vnd.app.v1+json`) | URL Versioning (`/api/v1/`) (Easier to test/debug) |
| `if ($v2) { ... }` inside Controller | Separate Controllers (`V1\Ctrl`, `V2\Ctrl`) |
| Changing field types (int to string) in V1 | Create V2 for type changes |
| Deleting V1 immediately | Maintain V1 for x months with deprecation warnings |

