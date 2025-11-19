# Migration Patterns

## 🎯 Objective
Define database schema changes in a version-controlled, reversible way that maintains data integrity and follows best practices for development and production environments.

## 🔑 Key Principles
1. **Complete Migrations**: In development, create complete migrations with all columns, indexes, and constraints from the start.
2. **Modify, Don't Add**: In development, modify existing migration files directly rather than creating new migrations to fix or add columns.
3. **Dependency Order**: Migrations must run in dependency order (create referenced tables before foreign keys).
4. **Reversible**: All migrations must have proper `down()` methods for rollback.
5. **Indexes First**: Add indexes for foreign keys and frequently queried columns.

## 📝 Standard Pattern

### 1. Basic Table Creation
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

### 2. Foreign Key Relationships
Always define foreign keys with appropriate cascade behavior:
```php
// Cascade delete (child deleted when parent deleted)
$table->foreignId('tenant_id')
    ->constrained('tenants')
    ->cascadeOnDelete();

// Null on delete (set to null when parent deleted)
$table->foreignId('country_id')
    ->nullable()
    ->constrained('countries')
    ->nullOnDelete();

// Restrict delete (prevent deletion if children exist)
$table->foreignId('subscription_plan_id')
    ->constrained('subscription_plans')
    ->restrictOnDelete();
```

### 3. Junction/Pivot Tables
For many-to-many relationships:
```php
Schema::create('business_users_tenants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('business_user_id')
        ->constrained('business_users')
        ->cascadeOnDelete();
    $table->foreignId('tenant_id')
        ->constrained('tenants')
        ->cascadeOnDelete();
    $table->string('role')->nullable();
    $table->timestamps();

    // Prevent duplicate relationships
    $table->unique(['business_user_id', 'tenant_id']);
    
    // Indexes for lookups
    $table->index('business_user_id');
    $table->index('tenant_id');
});
```

### 4. Soft Deletes
Add soft deletes for tables that need logical deletion:
```php
$table->softDeletes(); // Adds `deleted_at` column

// In Model, use: use Illuminate\Database\Eloquent\SoftDeletes;
```

### 5. Composite Indexes
For queries filtering on multiple columns:
```php
// For availability checks
$table->index(['court_id', 'start_date', 'start_time']);

// For geographic queries
$table->index(['latitude', 'longitude']);

// For filtering by status within a type
$table->index(['court_type_id', 'status']);
```

### 6. Enum Types
Use enums for fixed value sets:
```php
$table->enum('type', ['padel', 'tennis', 'squash', 'badminton', 'other'])
    ->default('padel');
```

### 7. JSON Columns
For flexible data structures:
```php
$table->json('breaks')->nullable(); // Array of break periods
$table->json('metadata')->nullable(); // Additional flexible data
```

### 8. Money/Price Storage
Store money as integers (cents) or decimals:
```php
// Option 1: Integer (cents) - recommended for exact precision
$table->bigInteger('price'); // Store in cents

// Option 2: Decimal (if needed)
$table->decimal('price', 10, 2); // 10 digits, 2 decimal places
```

## 🔄 Development Environment Workflow

### ✅ DO: Modify Existing Migrations
In development, if you need to change a migration:
1. **Edit the migration file directly**
2. **Drop and recreate the database**:
   ```bash
   php artisan migrate:fresh
   ```
3. **Or rollback and re-run**:
   ```bash
   php artisan migrate:rollback --step=1
   php artisan migrate
   ```

### ❌ DON'T: Create Fix Migrations
In development, avoid creating new migrations to:
- Add columns to existing tables
- Fix column types
- Add indexes
- Modify constraints

Instead, modify the original migration file.

## 📋 Migration Naming Convention

Follow Laravel's timestamp-based naming:
```
YYYY_MM_DD_HHMMSS_descriptive_name.php
```

Example:
```
2024_01_01_000000_create_countries_table.php
2024_01_01_000001_create_subscription_plans_table.php
2024_01_01_000002_create_users_table.php
```

## 🔗 Dependency Order

Migrations must run in dependency order. Example for our schema:

1. **No dependencies**: `countries`, `subscription_plans`
2. **Depends on #1**: `users`, `business_users`, `tenants`
3. **Depends on #2**: `business_users_tenants`, `courts_type`
4. **Depends on #3**: `court`, `courts_availabilities`
5. **Depends on #4**: `courts_images`, `bookings`, `invoices`

## 📊 Common Column Types

| Use Case | Column Type | Example |
|----------|-------------|---------|
| Primary Key | `id()` | Auto-incrementing bigint |
| Foreign Key | `foreignId('user_id')` | References `users.id` |
| String (short) | `string('name')` | VARCHAR(255) |
| String (long) | `string('address', 512)` | VARCHAR(512) |
| Text | `text('description')` | TEXT |
| Boolean | `boolean('is_active')` | TINYINT(1) |
| Integer | `integer('count')` | INT |
| Big Integer | `bigInteger('price')` | BIGINT (for cents) |
| Decimal | `decimal('price', 10, 2)` | DECIMAL(10,2) |
| Date | `date('start_date')` | DATE |
| DateTime | `dateTime('published_at')` | DATETIME |
| Time | `time('start_time')` | TIME |
| JSON | `json('metadata')` | JSON |
| Enum | `enum('type', [...])` | ENUM |

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Creating new migration to add column in dev | Modify existing migration file |
| `$table->string('email')` without unique | `$table->string('email')->unique()` |
| Missing foreign key constraints | Always use `constrained()` |
| No indexes on foreign keys | Add `$table->index('foreign_key')` |
| Hardcoding table names in foreign keys | Use `constrained('table_name')` |
| Missing `down()` method | Always implement rollback |
| Creating tables in wrong order | Check dependencies first |
| Using `float` for money | Use `bigInteger` (cents) or `decimal` |
| Missing nullable for optional fields | Use `->nullable()` |
| No default values for booleans | `->default(true)` or `->default(false)` |

## 🔍 Index Strategy

### Always Index:
- Foreign keys (`tenant_id`, `user_id`, etc.)
- Unique columns (`email`, `slug`)
- Frequently filtered columns (`status`, `is_active`)
- Date columns used in WHERE clauses (`start_date`, `created_at`)

### Consider Composite Indexes For:
- Multi-column WHERE clauses
- Common query patterns (e.g., `(tenant_id, start_date)`)
- Sorting + filtering combinations

### Don't Over-Index:
- Columns rarely used in WHERE clauses
- Low cardinality columns (unless frequently filtered)
- Every column (causes write performance issues)

## 🎯 Multi-Tenancy Pattern

All tenant-specific tables must include:
```php
$table->foreignId('tenant_id')
    ->constrained('tenants')
    ->cascadeOnDelete();

$table->index('tenant_id'); // Always index tenant_id
```

This ensures:
- Data isolation per tenant
- Efficient tenant-scoped queries
- Automatic cleanup when tenant is deleted

## 📝 Example: Complete Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Foreign keys (in dependency order)
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('court_id')
                ->constrained('court')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            
            // Date/time columns
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            
            // Money (stored in cents)
            $table->bigInteger('price');
            
            // Boolean flags
            $table->boolean('is_pending')->default(true);
            $table->boolean('is_cancelled')->default(false);
            $table->boolean('is_paid')->default(false);
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('tenant_id');
            $table->index('court_id');
            $table->index('user_id');
            $table->index(['court_id', 'start_date', 'start_time']); // Composite for availability checks
            $table->index(['tenant_id', 'start_date']); // Composite for tenant queries
            $table->index('is_cancelled');
            $table->index('is_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
```

