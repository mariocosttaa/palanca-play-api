# Mobile API Structure

API structure documentation for the **Mobile API** endpoints used by regular users (iOS, Android apps).

## Base URL
- **Prefix**: `/api/v1`
- **Route File**: `routes/api-mobile.php`
- **Guard**: `auth:sanctum` (default)
- **User Model**: `App\Models\User`
- **Controller**: `App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController`

## Test Directory
- **Location**: `tests/Feature/Api/Mobile/`
- **Test Files**:
  - `UserAuthTest.php` - User authentication tests (register, login, logout, profile)

## Running Tests
```bash
# Run all mobile API tests
php artisan test tests/Feature/Api/Mobile

# Run specific test file
php artisan test tests/Feature/Api/Mobile/UserAuthTest.php
```

