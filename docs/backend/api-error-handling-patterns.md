# API Error Handling Patterns

## 🎯 Objective
Provide consistent, machine-readable error responses for all API failures.

## 🔑 Key Principles
1.  **Consistent JSON**: All errors must follow the same structure (`message`, `errors`?, `code`?).
2.  **Correct Status Codes**: Use 400, 401, 403, 404, 422, 500 appropriately.
3.  **No Stack Traces**: Never expose stack traces in production (`debug: false`).
4.  **Validation First**: Rely on Laravel's automatic 422 response for validation errors.

## 📝 Standard Responses

### 1. Validation Error (422)
*Automatically handled by Laravel FormRequests.*
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email has already been taken."]
    }
}
```

### 2. Not Found (404)
```php
// Automatic via findOrFail()
User::findOrFail($id);

// Manual
abort(404, 'Resource not found.');
```

### 3. Unauthorized (401) vs Forbidden (403)
- **401**: "Who are you?" (Not logged in)
- **403**: "You can't do that." (Logged in but no permission)

```php
// 403
if ($user->role !== 'admin') {
    abort(403, 'Admin access required.');
}
```

### 4. Server Error (500)
Wrap critical logic in try-catch to control the output.
```php
try {
    // ... logic
} catch (\Exception $e) {
    Log::error('Server error: ' . $e->getMessage());
    return response()->json(['message' => 'Server error occurred'], 500);
}
```

## ⚙️ Global Handler Customization
In `bootstrap/app.php` (Laravel 11) or `app/Exceptions/Handler.php`:

```php
$exceptions->render(function (NotFoundHttpException $e, Request $request) {
    if ($request->is('api/*')) {
        return response()->json(['message' => 'Record not found.'], 404);
    }
});
```

## ⚠️ Anti-Patterns

| ❌ Bad Pattern | ✅ Good Pattern |
|----------------|-----------------|
| Returning 200 OK for errors | Return 4xx/5xx status codes |
| "Something went wrong" (Too vague) | "Unable to process payment" (Specific but safe) |
| Exposing DB column names in errors | Use Validation attributes/messages |
