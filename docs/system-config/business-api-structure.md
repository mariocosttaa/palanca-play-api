# Business API Structure

API structure documentation for the **Business API** endpoints used by business users/managers (web dashboard).

## Base URL
- **Prefix**: `/business/v1`
- **Route File**: `routes/api-business.php`
- **Guard**: `auth:business`
- **User Model**: `App\Models\BusinessUser`
- **Controller**: `App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController`

## Test Directory
- **Location**: `tests/Feature/Api/Business/`
- **Test Files**:
  - `BusinessUserAuthTest.php` - Business user authentication tests (register, login, logout, profile)

## Running Tests
```bash
# Run all business API tests
php artisan test tests/Feature/Api/Business

# Run specific test file
php artisan test tests/Feature/Api/Business/BusinessUserAuthTest.php
```

## Important Notes
When using `Sanctum::actingAs()` in tests, always specify the guard:
```php
Sanctum::actingAs($businessUser, [], 'business');
```

