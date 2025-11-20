# Seeder Patterns

## 🎯 Objective
Populate the database with initial configuration data and dummy test data using reproducible, idempotent scripts.

## 🔑 Key Principles
1.  **Idempotent**: Seeders must be safe to run multiple times (check existence before inserting).
2.  **Factories**: Use Model Factories for generating dummy data, not manual `DB::insert`.
3.  **Production Safe**: configuration seeders (Roles, Settings) must separate from dummy seeders (Fake Users).
4.  **Performance**: Use `createMany` or `insert` for bulk operations to avoid N+1 db calls.

## 📝 Standard Pattern

### 1. Configuration Seeder (Production)
Use for fixed data like Roles, Permissions, or Countries. Place these in `database/seeders/Default/`.

```php
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = json_decode(file_get_contents(__DIR__ . '/CountrySeeder.json'), true);

        foreach ($countries as $country) {
            Country::firstOrCreate(['code' => $country['code']], $country);
        }
    }
}
```

### 2. Test Data Seeder (Development/Testing)
Use for generating fake data for local development or testing. Place these in `database/seeders/Test/`.

```php
class UserTestSeeder extends Seeder
{
    public function run(int $count = 10): void
    {
        User::factory($count)->create();
    }
}
```

### 3. Main DatabaseSeeder
The `DatabaseSeeder` should only run **default configuration** seeders that are safe for production.

```php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Always run config first
        $this->call([
            CountrySeeder::class,
            // Other default seeders...
        ]);
    }
}
```

### 4. TestSeeder (Master Test Seeder)
The `TestSeeder` orchestrates all test seeders. It is located at `database/seeders/TestSeeder.php`.

```php
class TestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed business users
        $this->call(BusinessUserTestSeeder::class, false, ['count' => 10]);

        // 2. Seed app users
        $this->call(UserTestSeeder::class, false, ['count' => 10]);

        // ... other test seeders
    }
}
```

## 🚀 Running Seeders

### Default Seeding (Production/Setup)
Runs only the default configuration seeders (defined in `DatabaseSeeder`).

```bash
php artisan db:seed
```

### Test Seeding (Development)
Runs the test data seeders (defined in `TestSeeder`). This command is a custom extension.

```bash
php artisan db:seed --test
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Hardcoding IDs (`id => 1`) | Let DB handle IDs, use `firstOrCreate` by unique key |
| Running `User::truncate()` | Never truncate in seeders (danger for prod) |
| Nested `foreach` loops for relations | Use Factory states: `User::factory()->hasPosts(5)` |
| Seeding sensitive real data | Use `faker` for PII (Personally Identifiable Information) |
| Mixing test data in `DatabaseSeeder` | Keep `DatabaseSeeder` for config only; use `TestSeeder` for fake data |
