# Feature Tests vs Unit Tests

## 🎯 Overview

Understanding when to use **Feature Tests** vs **Unit Tests** is crucial for maintaining a well-organized test suite.

## 📊 Quick Comparison

| Aspect | Feature Tests | Unit Tests |
|--------|---------------|------------|
| **Location** | `tests/Feature/` | `tests/Unit/` |
| **What They Test** | Full HTTP requests, endpoints, routes | Individual classes, methods, functions |
| **Database** | ✅ Uses real database (with RefreshDatabase) | ❌ No database (mocked if needed) |
| **HTTP Layer** | ✅ Tests through HTTP (`getJson`, `postJson`) | ❌ No HTTP layer |
| **Middleware** | ✅ Runs through all middleware | ❌ No middleware |
| **Authentication** | ✅ Tests real auth (Sanctum, guards) | ❌ Mocks authentication |
| **Speed** | Slower (full Laravel bootstrap) | Faster (isolated) |
| **Use Case** | API endpoints, user flows | Business logic, utilities, helpers |

## 🔵 Feature Tests

### What Are Feature Tests?

Feature tests test your application **end-to-end** through HTTP requests. They simulate real user interactions with your API.

### When to Use Feature Tests

✅ **Use Feature Tests for:**
- API endpoints (CRUD operations)
- Authentication flows (login, register, logout)
- Authorization (permissions, access control)
- Request validation
- Response structure
- Database operations through endpoints
- Middleware behavior
- Route testing

### Example: Feature Test

```php
<?php
// tests/Feature/Api/Business/CourtTest.php

use App\Models\Court;
use App\Models\Tenant;
use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('business user can create a court', function () {
    // Arrange
    $tenant = Tenant::factory()->create();
    $businessUser = BusinessUser::factory()->create();
    $businessUser->tenants()->attach($tenant);
    Sanctum::actingAs($businessUser, [], 'business');
    
    // Act - Make HTTP request
    $response = $this->postJson("/business/v1/tenants/{$tenant->id}/courts", [
        'name' => 'Court 1',
        'court_type_id' => 1,
    ]);
    
    // Assert - Check HTTP response and database
    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json->has('data'));
    
    $this->assertDatabaseHas('courts', [
        'name' => 'Court 1',
        'tenant_id' => $tenant->id,
    ]);
});
```

### Your Current Feature Tests Structure ✅

```
tests/Feature/
├── Api/
│   ├── Business/
│   │   ├── AcessTest.php          ✅ Testing access control
│   │   ├── BusinessUserAuthTest.php ✅ Testing auth endpoints
│   │   ├── CourtTest.php          ✅ Testing court CRUD
│   │   ├── CourtTypeTest.php      ✅ Testing court type CRUD
│   │   └── TenantTest.php         ✅ Testing tenant CRUD
│   └── Mobile/
│       └── UserAuthTest.php        ✅ Testing mobile auth
└── ExampleTest.php                 ⚠️ Can be removed (just example)
```

**Your structure is CORRECT!** ✅ All your API endpoint tests are properly organized in `tests/Feature/Api/`.

## 🟢 Unit Tests

### What Are Unit Tests?

Unit tests test **individual units** of code in isolation - classes, methods, or functions - without the full Laravel application context.

### When to Use Unit Tests

✅ **Use Unit Tests for:**
- Helper classes (Actions, Services)
- Utility functions
- Business logic calculations
- Data transformations
- Validation logic (custom rules)
- Formatters, parsers
- Complex algorithms
- Traits (if they have complex logic)

❌ **Don't Use Unit Tests for:**
- API endpoints (use Feature tests)
- Database operations (use Feature tests)
- Authentication flows (use Feature tests)
- Anything that needs HTTP layer (use Feature tests)

### Example: Unit Test

```php
<?php
// tests/Unit/Actions/EasyHashActionTest.php

use App\Actions\General\EasyHashAction;

test('can encode and decode hashid correctly', function () {
    $originalId = 123;
    $context = 'court-id';
    
    // Encode
    $hashId = EasyHashAction::encode($originalId, $context);
    
    // Decode
    $decodedId = EasyHashAction::decode($hashId, $context);
    
    expect($decodedId)->toBe($originalId);
    expect($hashId)->not->toBe($originalId); // Should be hashed
});

test('decode throws exception for invalid hashid', function () {
    expect(fn() => EasyHashAction::decode('invalid-hash', 'court-id'))
        ->toThrow(\Exception::class);
});
```

### What Should Be Unit Tested in Your Project?

Based on your codebase, here are candidates for Unit Tests:

1. **Actions** (`app/Actions/`)
   - `EasyHashAction` - Hash encoding/decoding logic
   - Any calculation or transformation logic

2. **Traits** (if they have complex logic)
   - `HasHashid` - If it has complex hashid logic
   - `HasMoney` - If it has money formatting/calculation logic

3. **Custom Validation Rules** (if you create any)
   - Custom `Rule` classes

4. **Helper Classes** (if you create any)
   - Utility classes with pure functions

## 📁 Recommended Structure

### Feature Tests (Current - ✅ Correct)

```
tests/Feature/
├── Api/
│   ├── Business/
│   │   ├── AcessTest.php
│   │   ├── BusinessUserAuthTest.php
│   │   ├── CourtTest.php
│   │   ├── CourtTypeTest.php
│   │   └── TenantTest.php
│   └── Mobile/
│       └── UserAuthTest.php
└── ExampleTest.php  ⚠️ Remove this (just an example)
```

### Unit Tests (Recommended Structure)

```
tests/Unit/
├── Actions/
│   ├── General/
│   │   └── EasyHashActionTest.php  ✅ Should be created
│   └── ...
├── Traits/
│   ├── HasHashidTest.php            ✅ If trait has complex logic
│   └── HasMoneyTest.php             ✅ If trait has complex logic
└── Rules/                           ✅ If you create custom rules
    └── ...
```

## 🎯 Decision Tree

```
Is it testing an API endpoint?
├─ YES → Feature Test (tests/Feature/Api/)
└─ NO
   ├─ Is it testing a class/method in isolation?
   │  ├─ YES → Unit Test (tests/Unit/)
   │  └─ NO → Probably Feature Test
   │
   └─ Does it need HTTP layer, database, or middleware?
      ├─ YES → Feature Test
      └─ NO → Unit Test
```

## 📝 Best Practices

### Feature Tests
1. ✅ Always use `RefreshDatabase` trait
2. ✅ Test through HTTP (`getJson`, `postJson`, etc.)
3. ✅ Test both happy path and error cases
4. ✅ Verify database changes
5. ✅ Test authentication and authorization
6. ✅ Use factories for test data

### Unit Tests
1. ✅ Test one thing at a time
2. ✅ Use mocks for dependencies
3. ✅ No database access
4. ✅ Fast execution
5. ✅ Test edge cases and boundaries
6. ✅ Test pure functions (same input = same output)

## 🚀 Your Current Status

### ✅ What's Good
- All API endpoint tests are in `tests/Feature/Api/` ✅
- Tests are organized by Business/Mobile separation ✅
- Using `RefreshDatabase` correctly ✅
- Testing authentication properly ✅

### 🔧 What Could Be Improved

1. **Remove Example Tests**
   ```bash
   # Remove these example files:
   tests/Feature/ExampleTest.php
   tests/Unit/ExampleTest.php
   ```

2. **Add Unit Tests for Actions**
   - Create `tests/Unit/Actions/General/EasyHashActionTest.php`
   - Test hash encoding/decoding logic

3. **Consider Unit Tests for Complex Logic**
   - If `HasHashid` or `HasMoney` traits have complex logic, add unit tests
   - If you create custom validation rules, add unit tests

## 📚 Summary

**Your current organization is CORRECT!** ✅

- **Feature Tests** (`tests/Feature/`) - For API endpoints ✅
- **Unit Tests** (`tests/Unit/`) - For isolated classes/methods (currently empty, which is fine)

For an API-focused Laravel application, **Feature Tests are the priority**. Unit tests are optional and should be added when you have:
- Complex business logic in Actions
- Utility functions that need isolated testing
- Custom validation rules
- Complex calculations or transformations

Your current test structure follows Laravel best practices! 🎉

