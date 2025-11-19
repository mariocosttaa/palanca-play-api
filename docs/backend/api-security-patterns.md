# API Security Patterns

## 🎯 Objective
Protect the API from abuse, unauthorized access, and common web vulnerabilities using standard middleware and headers.

## 🔑 Key Principles
1.  **Rate Limiting**: Enforce limits per user/IP to prevent DDoS and brute-force.
2.  **CORS**: Strictly define allowed origins; do not use `*` in production.
3.  **Sanitization**: Never trust input; validate everything (covered in Request Patterns).
4.  **Headers**: Use standard security headers (HSTS, X-Content-Type-Options).

## 📝 Standard Pattern

### 1. Rate Limiting (`RouteServiceProvider` or `app.php`)
Define limits in `boot()` method.
```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

Apply in Routes:
```php
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // ...
});
```

### 2. CORS Configuration (`config/cors.php`)
Strictly limit origins for production credentials.
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('ALLOWED_ORIGINS', 'http://localhost:3000')), // Strict list
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Required for Sanctum Cookies
];
```

### 3. Security Headers
Ensure these headers are sent (via Nginx/Apache or Middleware).
*   **X-Content-Type-Options**: `nosniff` (Prevents MIME sniffing)
*   **X-Frame-Options**: `DENY` (Prevents clickjacking)
*   **Strict-Transport-Security**: `max-age=31536000; includeSubDomains` (Force HTTPS)

### 4. Sensitive Data Exposure
Never return full Exception traces in production (`APP_DEBUG=false`).
Never return `password`, `token`, `secret` fields in API Resources.

```php
// User model
protected $hidden = [
    'password',
    'remember_token',
    'two_factor_secret',
];
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| `allowed_origins => ['*']` with Credentials | Explicit origins (`https://app.com`) |
| No Rate Limits on Login endpoints | Strict throttling (`throttle:5,1` - 5 tries/min) |
| Returning Database IDs | Use Hashids (See [Hashids Patterns](hashids-patterns.md)) |
| Disabled CSRF for Sanctum | Keep CSRF enabled for SPA authentication |

