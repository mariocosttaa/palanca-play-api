# API Request Patterns

## 🎯 Objective
Handle validation, authorization, and data normalization before data reaches the controller.

## 🔑 Key Principles
1.  **PHPDoc is Mandatory**: Document all inputs, route params, and magic methods for IDE support.
2.  **Decode IDs First**: Decode hashed IDs (Hashids) in `prepareForValidation`.
3.  **NO Tenant ID**: Never validate `tenant_id` in requests. It must come from Middleware/Controller.
4.  **Explicit Rules**: Use `Rule::exists` and `Rule::unique` with Model classes, not strings.
5.  **Normalize Data**: Sanitize inputs (trim strings, format money/dates) before validation.

## 📝 Standard Pattern

### Full Example
```php
namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Actions\General\EasyHashAction;
use App\Models\Product;

/**
 * @property string $name
 * @property int $category_id
 * @property float $price
 * @method mixed route(string $key = null)
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        // 1. Decode Hashed IDs
        if ($this->category_id) {
            $this->merge([
                'category_id' => EasyHashAction::decode($this->category_id, 'category-id'),
            ]);
        }

        // 2. Normalize Data
        $this->merge([
            'name' => trim($this->name),
            'price' => (int) ($this->price * 100), // Convert to cents
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists(\App\Models\Category::class, 'id')],
            'price' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

## 🛡️ Validation Rules

### Database Validation
Always use Model class constants.
```php
// ✅ Good
Rule::exists(\App\Models\Category::class, 'id')
Rule::unique(\App\Models\User::class, 'email')

// ❌ Bad
'exists:categories,id'
'unique:users,email'
```

### Unique on Update
Ignore the current record.
```php
$id = EasyHashAction::decode($this->route('product'), 'product-id');
Rule::unique(Product::class, 'name')->ignore($id)
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Validating `tenant_id` in rules | Handle `tenant_id` in Controller via Attributes |
| Accepting raw IDs (`123`) | Decode Hashids (`Xy7z`) in `prepareForValidation` |
| Missing PHPDocs | Full `@property` documentation |
| Complex logic in `authorize()` | Use Policy classes (`$this->user()->can()`) |
