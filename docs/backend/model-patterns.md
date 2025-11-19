# Model Patterns

## 🎯 Objective
Define the data structure, relationships, and business logic encapsulation for the application, shared between API and Backend.

## 🔑 Key Principles
1.  **Fat Models, Skinny Controllers**: Encapsulate query logic (Scopes) and data mutation (Mutators) in the Model.
2.  **Explicit Casting**: Always define `$casts` for Dates, Booleans, and Money.
3.  **Guarded by Default**: Use `$fillable` to whitelist mass-assignable attributes.
4.  **Money as Integers**: Store currency values in cents (integer), format via Accessors.

## 📝 Standard Pattern

### 1. Basic Structure
```php
namespace App\Models;

use App\Traits\HasHashid;
use App\Traits\HasMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasMoney, HasHashid;

    // 1. Configuration
    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'price', // Stored in cents
        'is_active',
        'published_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'integer',
        'published_at' => 'datetime',
    ];

    // 2. Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // 3. Scopes (Query Logic)
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // 4. Accessors (Formatters)
    public function getPriceFmtAttribute(): string
    {
        return '$' . number_format($this->price / 100, 2);
    }
}
```

### 2. Boot Methods (Auto-logic)
Handle automatic ID generation or defaults.
```php
protected static function boot()
{
    parent::boot();

    static::creating(function ($model) {
        if (!$model->uuid) {
            $model->uuid = (string) \Illuminate\Support\Str::uuid();
        }
    });
}
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `$guarded = []` (Unsafe) | `$fillable = ['name', ...]` (Explicit whitelist) |
| Formatting dates in Controllers | Use `$casts = ['date' => 'datetime']` |
| Storing Money as `float` (Drift issues) | Store as `integer` (cents) |
| Complex queries in Controller | Use Scopes (`Product::active()->get()`) |
