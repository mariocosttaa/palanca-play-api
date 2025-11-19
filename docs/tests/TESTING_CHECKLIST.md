# Testing Checklist for New Endpoints

## 🚨 MANDATORY: Complete this checklist for every new endpoint

### Before Implementation
- [ ] Review [API Testing Patterns](../../backend/api-testing-patterns.md)
- [ ] Check existing test files for similar patterns
- [ ] Use the test template from [API Testing Patterns](../../backend/api-testing-patterns.md#-complete-test-template)

### During Implementation
- [ ] Create test file: `tests/Feature/Api/YourControllerTest.php`
- [ ] Implement endpoint in Controller
- [ ] Write tests immediately after implementation

### Test Coverage Checklist

#### For ALL Endpoints:
- [ ] **Happy Path**: Successful request with valid data
- [ ] **Validation Errors**: Invalid data returns 422
- [ ] **Authentication**: Unauthenticated requests return 401 (if protected)
- [ ] **Response Structure**: JSON matches API Resource format

#### For CREATE Endpoints:
- [ ] Successfully creates resource (201)
- [ ] Returns correct data structure
- [ ] Database contains new record
- [ ] Validation errors for required fields
- [ ] Unauthenticated access blocked (if protected)

#### For READ Endpoints (Index):
- [ ] Returns list of resources (200)
- [ ] Correct pagination structure (if applicable)
- [ ] Filters work correctly (if applicable)
- [ ] Unauthenticated access blocked (if protected)

#### For READ Endpoints (Show):
- [ ] Returns single resource (200)
- [ ] Returns 404 for non-existent resource
- [ ] Correct data structure
- [ ] Unauthenticated access blocked (if protected)

#### For UPDATE Endpoints:
- [ ] Successfully updates resource (200)
- [ ] Returns updated data
- [ ] Database reflects changes
- [ ] Validation errors for invalid data
- [ ] Returns 404 for non-existent resource
- [ ] Unauthenticated access blocked (if protected)

#### For DELETE Endpoints:
- [ ] Successfully deletes resource (204)
- [ ] Database record removed
- [ ] Returns 404 for non-existent resource
- [ ] Unauthenticated access blocked (if protected)

### After Implementation
- [ ] Run tests: `php artisan test --filter YourTest`
- [ ] All tests pass ✅
- [ ] Update Postman collection: `php artisan generate:postman-collection`
- [ ] Commit tests with implementation

## 📝 Test File Naming Convention

- Controller: `UserController.php` → Test: `UserTest.php`
- Controller: `BookingController.php` → Test: `BookingTest.php`
- Controller: `Api\V1\Mobile\Auth\UserAuthController.php` → Test: `Mobile/UserAuthTest.php`
- Controller: `Api\V1\Business\Auth\BusinessUserAuthController.php` → Test: `Business/BusinessUserAuthTest.php`

## 🔍 Quick Test Commands

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter UserTest

# Run with coverage
php artisan test --coverage

# Run only Feature tests
php artisan test tests/Feature
```

## ⚠️ Common Mistakes to Avoid

- ❌ Skipping tests for "simple" endpoints
- ❌ Testing only happy path
- ❌ Hardcoding IDs instead of using factories
- ❌ Not testing authentication/authorization
- ❌ Not verifying database changes
- ❌ Not checking response structure

## 📚 Reference

- [API Testing Patterns](../../backend/api-testing-patterns.md) - **Complete test template included**
- [Example Tests](../../../tests/Feature/Api/UserAuthTest.php)
- [Example Tests](../../../tests/Feature/Api/BusinessUserAuthTest.php)

