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
Use for fixed data like Roles, Permissions, or Countries.
```php
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['admin', 'manager', 'editor', 'viewer'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role], ['guard_name' => 'web']);
        }
    }
}
```

### 2. Dummy Data Seeder (Development)
Use Factories to generate relationships.
```php
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create 1 Main Admin
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create 50 random users with posts
        User::factory()
            ->count(50)
            ->hasPosts(3) // Factory relationship
            ->create();
    }
}
```

### 3. Main DatabaseSeeder
Control execution order.
```php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Always run config first
        $this->call([
            RoleSeeder::class,
            CountrySeeder::class,
        ]);

        // Only run dummy data in local/staging
        if (app()->isLocal()) {
            $this->call([
                UserSeeder::class,
                ProductSeeder::class,
            ]);
        }
    }
}
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Hardcoding IDs (`id => 1`) | Let DB handle IDs, use `firstOrCreate` by unique key |
| Running `User::truncate()` | Never truncate in seeders (danger for prod) |
| Nested `foreach` loops for relations | Use Factory states: `User::factory()->hasPosts(5)` |
| Seeding sensitive real data | Use `faker` for PII (Personally Identifiable Information) |
