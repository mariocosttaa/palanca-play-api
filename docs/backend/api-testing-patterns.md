# API Testing Patterns

## 🎯 Objective
Ensure API reliability by testing endpoints, response structures, and authentication states using Laravel's built-in testing tools.

## 🔑 Key Principles
1.  **Feature over Unit**: Prioritize Feature tests that hit actual endpoints (`getJson`, `postJson`).
2.  **Fluent JSON Assertions**: Use `assertJson(fn (AssertableJson $json) => ...)` for strict structure validation.
3.  **Refresh Database**: Always use `RefreshDatabase` trait.
4.  **Mock External Services**: Don't hit real APIs (Stripe, AWS) in tests; use Mocks or Fakes.
5.  **MANDATORY**: Every new endpoint MUST have corresponding tests before implementation is considered complete.

## 📝 Standard Pattern

### 1. Test Class Structure
Place in `tests/Feature/Api`.

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can create resource', function () {
    // 1. Arrange
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    // 2. Act
    $response = $this->postJson('/api/v1/resource', [
        'name' => 'Test Resource',
        // ... other fields
    ]);

    // 3. Assert
    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($item) => $item
                ->has('id')
                ->where('name', 'Test Resource')
                ->etc() // Allow additional fields
            )
        );
});
```

### 2. Testing Error States
Always test the "Sad Path".

```php
test('cannot create resource with invalid data', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/resource', [
        'name' => '', // Invalid
    ]);

    $response->assertUnprocessable() // 422
        ->assertJsonValidationErrors(['name']);
});
```

### 3. Testing Authorization (403)
```php
test('regular user cannot delete resource', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    Sanctum::actingAs($user);
    
    $resource = Resource::factory()->create();

    $this->deleteJson("/api/v1/resource/{$resource->id}")
         ->assertForbidden(); // 403
});
```

### 4. Testing Authentication (401)
```php
test('unauthenticated user cannot access protected endpoint', function () {
    $response = $this->getJson('/api/v1/protected-endpoint');
    
    $response->assertStatus(401);
});
```

## 🧪 Complete Test Template

### For CRUD Endpoints

```php
<?php

use App\Models\User;
use App\Models\YourModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// CREATE
test('authenticated user can create resource', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/resources', [
        'field1' => 'value1',
        'field2' => 'value2',
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($item) => $item
                ->has('id')
                ->where('field1', 'value1')
                ->etc()
            )
        );

    $this->assertDatabaseHas('resources', [
        'field1' => 'value1',
    ]);
});

test('cannot create resource with invalid data', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/resources', [
        'field1' => '', // Invalid
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['field1']);
});

test('unauthenticated user cannot create resource', function () {
    $response = $this->postJson('/api/v1/resources', [
        'field1' => 'value1',
    ]);

    $response->assertStatus(401);
});

// READ (Index)
test('authenticated user can list resources', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    YourModel::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/resources');

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', 3)
            ->has('data.0', fn ($item) => $item
                ->has('id')
                ->etc()
            )
        );
});

// READ (Show)
test('authenticated user can view resource', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    $resource = YourModel::factory()->create();

    $response = $this->getJson("/api/v1/resources/{$resource->id}");

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($item) => $item
                ->where('id', fn ($id) => ! empty($id))
                ->etc()
            )
        );
});

// UPDATE
test('authenticated user can update resource', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    $resource = YourModel::factory()->create();

    $response = $this->putJson("/api/v1/resources/{$resource->id}", [
        'field1' => 'updated value',
    ]);

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($item) => $item
                ->where('field1', 'updated value')
                ->etc()
            )
        );
});

// DELETE
test('authenticated user can delete resource', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    $resource = YourModel::factory()->create();

    $response = $this->deleteJson("/api/v1/resources/{$resource->id}");

    $response->assertStatus(204);

    $this->assertDatabaseMissing('resources', [
        'id' => $resource->id,
    ]);
});
```

## 🔐 Authentication Test Patterns

### User Authentication Pattern
```php
test('user can register with valid data', function () {
    $response = $this->postJson('/api/v1/users/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('data.token')
            ->has('data.user', fn ($user) => $user
                ->where('email', 'john@example.com')
                ->etc()
            )
        );
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/users/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data.token')
            ->has('data.user')
        );
});

test('user cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/users/login', [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
```

## 📋 Test Checklist

When implementing a new endpoint, ensure you test:

- [ ] **Happy Path**: Successful request with valid data
- [ ] **Validation Errors**: Invalid data returns 422
- [ ] **Authentication**: Unauthenticated requests return 401
- [ ] **Authorization**: Unauthorized users return 403 (if applicable)
- [ ] **Database**: Verify data is created/updated/deleted correctly
- [ ] **Response Structure**: Verify JSON structure matches API Resource
- [ ] **Edge Cases**: Empty data, null values, boundary conditions

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `assertJson(['id' => 1])` (Exact match) | `assertJsonPath('id', 1)` or Fluent JSON with `etc()` |
| Creating data in `setUp` | Create data inside each test method (Keep tests isolated) |
| Hardcoded IDs in assertions | Use model properties `$resource->id` |
| Hitting real DB without Refresh | Use `RefreshDatabase` trait |
| Skipping tests for "simple" endpoints | **ALL endpoints MUST have tests** |
| Testing only happy path | Test both success and failure scenarios |

## 🚨 MANDATORY RULE

**Every new API endpoint MUST have corresponding Feature tests before the implementation is considered complete.**

### Workflow:
1. Write the test first (TDD) OR
2. Implement endpoint, then immediately write tests
3. Run tests: `php artisan test --filter YourTest`
4. Ensure all tests pass before committing

### Test File Naming:
- Controller: `UserController.php`
- Test File: `UserTest.php` or `UserAuthTest.php`
- Location: `tests/Feature/Api/`

## 📋 Complete Test Template

Copy and adapt this template when creating tests for new endpoints:

```php
<?php

use App\Models\User;
use App\Models\YourModel; // Replace with your model
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ============================================
// CREATE ENDPOINT TESTS
// ============================================

test('authenticated user can create resource', function () {
    // Arrange
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Act
    $response = $this->postJson('/api/v1/resources', [
        'field1' => 'value1',
        'field2' => 'value2',
        // Add all required fields
    ]);

    // Assert
    $response->assertStatus(201)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($item) => $item
                ->has('id')
                ->where('field1', 'value1')
                ->etc() // Allow additional fields
            )
        );

    // Verify database
    $this->assertDatabaseHas('resources', [
        'field1' => 'value1',
    ]);
});

test('cannot create resource with invalid data', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/resources', [
        'field1' => '', // Invalid
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['field1']);
});

test('unauthenticated user cannot create resource', function () {
    $response = $this->postJson('/api/v1/resources', [
        'field1' => 'value1',
    ]);

    $response->assertStatus(401);
});

// ============================================
// READ ENDPOINT TESTS (Index)
// ============================================

test('authenticated user can list resources', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    YourModel::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/resources');

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', 3)
            ->has('data.0', fn ($item) => $item
                ->has('id')
                ->etc()
            )
        );
});

test('unauthenticated user cannot list resources', function () {
    $response = $this->getJson('/api/v1/resources');
    
    $response->assertStatus(401);
});

// ============================================
// READ ENDPOINT TESTS (Show)
// ============================================

test('authenticated user can view resource', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    $resource = YourModel::factory()->create();

    $response = $this->getJson("/api/v1/resources/{$resource->id}");

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($item) => $item
                ->where('id', fn ($id) => ! empty($id))
                ->etc()
            )
        );
});

test('unauthenticated user cannot view resource', function () {
    $resource = YourModel::factory()->create();
    
    $response = $this->getJson("/api/v1/resources/{$resource->id}");
    
    $response->assertStatus(401);
});

// ============================================
// UPDATE ENDPOINT TESTS
// ============================================

test('authenticated user can update resource', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    $resource = YourModel::factory()->create();

    $response = $this->putJson("/api/v1/resources/{$resource->id}", [
        'field1' => 'updated value',
    ]);

    $response->assertStatus(200)
        ->assertJson(fn ($json) => $json
            ->has('data', fn ($item) => $item
                ->where('field1', 'updated value')
                ->etc()
            )
        );
});

test('cannot update resource with invalid data', function () {
    Sanctum::actingAs(User::factory()->create());
    
    $resource = YourModel::factory()->create();

    $response = $this->putJson("/api/v1/resources/{$resource->id}", [
        'field1' => '', // Invalid
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['field1']);
});

// ============================================
// DELETE ENDPOINT TESTS
// ============================================

test('authenticated user can delete resource', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    
    $resource = YourModel::factory()->create();

    $response = $this->deleteJson("/api/v1/resources/{$resource->id}");

    $response->assertStatus(204);

    $this->assertDatabaseMissing('resources', [
        'id' => $resource->id,
    ]);
});

test('unauthenticated user cannot delete resource', function () {
    $resource = YourModel::factory()->create();
    
    $response = $this->deleteJson("/api/v1/resources/{$resource->id}");
    
    $response->assertStatus(401);
});
```

## 📚 Examples

See existing test files:
- `tests/Feature/Api/UserAuthTest.php` - Authentication tests
- `tests/Feature/Api/BusinessUserAuthTest.php` - Business user authentication tests
