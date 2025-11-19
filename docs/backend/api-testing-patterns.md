# API Testing Patterns

## 🎯 Objective
Ensure API reliability by testing endpoints, response structures, and authentication states using Laravel's built-in testing tools.

## 🔑 Key Principles
1.  **Feature over Unit**: Prioritize Feature tests that hit actual endpoints (`getJson`, `postJson`).
2.  **Fluent JSON Assertions**: Use `assertJson(fn (AssertableJson $json) => ...)` for strict structure validation.
3.  **Refresh Database**: Always use `RefreshDatabase` trait.
4.  **Mock External Services**: Don't hit real APIs (Stripe, AWS) in tests; use Mocks or Fakes.

## 📝 Standard Pattern

### 1. Test Class Structure
Place in `tests/Feature/Api`.

```php
namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_products()
    {
        // 1. Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user); // Auth
        
        Product::factory()->count(3)->create();

        // 2. Act
        $response = $this->getJson('/api/v1/products');

        // 3. Assert
        $response->assertOk()
            ->assertJson(fn ($json) => $json
                ->has('data', 3)
                ->has('data.0', fn ($item) => $item
                    ->has('id')
                    ->has('name')
                    ->has('price_fmt')
                    ->etc()
                )
                ->has('meta.current_page')
            );
    }
}
```

### 2. Testing Error States
Always test the "Sad Path".

```php
public function test_cannot_create_product_with_invalid_data()
{
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/products', [
        'name' => '', // Invalid
    ]);

    $response->assertUnprocessable() // 422
        ->assertJsonValidationErrors(['name']);
}
```

### 3. Testing Authorization (403)
```php
public function test_regular_user_cannot_delete_product()
{
    $user = User::factory()->create(['role' => 'viewer']);
    Sanctum::actingAs($user);
    
    $product = Product::factory()->create();

    $this->deleteJson("/api/v1/products/{$product->id}")
         ->assertForbidden(); // 403
}
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `assertJson(['id' => 1])` (Exact match) | `assertJsonPath('id', 1)` or Fluent JSON |
| Creating data in `setUp` | Create data inside each test method (Keep tests isolated) |
| Hardcoded IDs in assertions | Use model properties `$product->id` |
| Hitting real DB without Refresh | Use `RefreshDatabase` trait |

