# API Authentication Patterns

## 🎯 Objective
Secure API endpoints using Laravel Sanctum (Token-based authentication) with a focus on statelessness and security.

## 🔑 Key Principles
1.  **Stateless**: APIs should not rely on session cookies; use Bearer tokens.
2.  **Revocable**: Tokens should be revocable (Logout = delete token).
3.  **Scoped**: Use token abilities (`read`, `write`) for granular permissions.
4.  **Secure**: Always use HTTPS. Never store tokens in `localStorage` (use `httpOnly` cookies or secure memory).

## 📝 Auth Controller Patterns

### Login (Issue Token)
```php
public function login(Request $request)
{
    $request->validate(['email' => 'required|email', 'password' => 'required']);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages(['email' => ['Invalid credentials']]);
    }

    // Create plain text token
    $token = $user->createToken($request->device_name ?? 'api-client')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => new UserResourceSpecific($user),
    ]);
}
```

### Logout (Revoke Token)
Revoke only the token used for the current request.
```php
public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();
    return response()->json(null, 204);
}
```

### Register
```php
public function register(RegisterRequest $request)
{
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    $token = $user->createToken('api-client')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => new UserResourceSpecific($user),
    ], 201);
}
```

## 🛡️ Middleware Pattern

### Standard Protection
Apply `auth:sanctum` to all protected routes.
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $r) => $r->user());
    Route::post('/orders', [OrderController::class, 'store']);
});
```

### Token Abilities Check
Check permissions inside controllers or middleware.
```php
if ($user->tokenCan('orders:create')) {
    // Allowed
}
```

## 🏢 Multi-Tenant Auth
If using multi-tenancy, validate tenant access *after* password check.
```php
if (!$user->hasAccessToTenant($request->tenant_id)) {
    abort(403, 'No access to this tenant');
}
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Storing passwords in plain text | Always `Hash::make()` |
| Long-lived non-expiring tokens | Implement expiration/refresh flow |
| Return all user data on login | Return `UserResource` (filtered data) |
| GET request for Logout | POST request for Logout (state change) |
