# CORS Configuration Guide

## Overview

Palanca Play API uses **separate CORS configurations** for Mobile and Business APIs to allow different frontend applications to access each API independently.

---

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Mobile API CORS - For mobile apps (iOS, Android, React Native, etc.)
MOBILE_CORS_ORIGINS=http://localhost:5173,http://localhost:3000,http://localhost:19006

# Business API CORS - For web dashboard (React, Vue, Angular, etc.)
BUSINESS_CORS_ORIGINS=http://localhost:5173,http://localhost:3000,http://localhost:8080
```

### Allowed Origins

**Mobile API (`MOBILE_CORS_ORIGINS`):**
- `http://localhost:5173` - Vite default
- `http://localhost:3000` - React/Next.js default
- `http://localhost:19006` - Expo/React Native default

**Business API (`BUSINESS_CORS_ORIGINS`):**
- `http://localhost:5173` - Vite default
- `http://localhost:3000` - React/Next.js default
- `http://localhost:8080` - Vue default

---

## How It Works

### Middleware

Two separate CORS middleware handle requests:

1. **`MobileCorsMiddleware`** - Applied to `/api/mobile/*` routes
2. **`BusinessCorsMiddleware`** - Applied to `/api/business/*` routes

### Route Configuration

Routes are configured in `bootstrap/app.php`:

```php
// Mobile API with Mobile CORS
Route::middleware(['api', 'mobile.cors'])
    ->prefix('api/mobile')
    ->group(base_path('routes/api-mobile.php'));

// Business API with Business CORS
Route::middleware(['api', 'business.cors'])
    ->prefix('api/business')
    ->group(base_path('routes/api-business.php'));
```

---

## CORS Headers

Both middleware set the following headers:

- `Access-Control-Allow-Origin`: Matched origin from allowed list
- `Access-Control-Allow-Methods`: `GET, POST, PUT, DELETE, PATCH, OPTIONS`
- `Access-Control-Allow-Headers`: `Content-Type, Authorization, X-Requested-With, Accept, Origin`
- `Access-Control-Allow-Credentials`: `true` (for Sanctum authentication)
- `Access-Control-Max-Age`: `86400` (24 hours)

---

## Adding New Origins

### Development

1. **Edit `.env`:**
```env
MOBILE_CORS_ORIGINS=http://localhost:5173,http://localhost:3000,http://localhost:19006,http://localhost:4000
BUSINESS_CORS_ORIGINS=http://localhost:5173,http://localhost:3000,http://localhost:8080,http://localhost:4000
```

2. **Clear config cache:**
```bash
php artisan config:clear
```

### Production

1. **Update environment variables** on your server
2. **Restart the application** to apply changes

**Example production configuration:**
```env
MOBILE_CORS_ORIGINS=https://app.palancaplay.com,https://mobile.palancaplay.com
BUSINESS_CORS_ORIGINS=https://dashboard.palancaplay.com,https://admin.palancaplay.com
```

---

## Testing CORS

### Test Mobile API CORS

```bash
curl -X OPTIONS http://localhost:8000/api/mobile/v1/users/login \
  -H "Origin: http://localhost:5173" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization" \
  -v
```

**Expected Response Headers:**
```
Access-Control-Allow-Origin: http://localhost:5173
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS
Access-Control-Allow-Credentials: true
```

### Test Business API CORS

```bash
curl -X OPTIONS http://localhost:8000/api/business/v1/business-users/login \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization" \
  -v
```

---

## Frontend Configuration

### Mobile App (React Native / Expo)

```javascript
// API configuration
const API_URL = 'http://localhost:8000/api/mobile/v1';

// Fetch example
fetch(`${API_URL}/users/login`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  credentials: 'include', // Important for Sanctum
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
});
```

### Business Dashboard (React / Vue / Angular)

```javascript
// API configuration
const API_URL = 'http://localhost:8000/api/business/v1';

// Axios example
import axios from 'axios';

const api = axios.create({
  baseURL: API_URL,
  withCredentials: true, // Important for Sanctum
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  }
});

// Login request
api.post('/business-users/login', {
  email: 'manager@example.com',
  password: 'password123'
});
```

---

## Troubleshooting

### CORS Error: "No 'Access-Control-Allow-Origin' header"

**Cause:** Origin not in allowed list

**Solution:**
1. Check your frontend URL matches exactly (including protocol and port)
2. Add the origin to the appropriate env variable
3. Clear config cache: `php artisan config:clear`

### CORS Error: "Credentials flag is true"

**Cause:** Frontend not sending credentials

**Solution:**
- **Fetch API:** Add `credentials: 'include'`
- **Axios:** Add `withCredentials: true`

### Preflight Request Failing

**Cause:** OPTIONS request not handled properly

**Solution:**
- Ensure middleware is registered in `bootstrap/app.php`
- Check that routes are using the correct middleware

---

## Security Best Practices

1. **Never use `*` in production:**
```env
# ❌ Bad - allows any origin
MOBILE_CORS_ORIGINS=*

# ✅ Good - specific origins only
MOBILE_CORS_ORIGINS=https://app.palancaplay.com
```

2. **Use HTTPS in production:**
```env
# ❌ Bad - insecure
MOBILE_CORS_ORIGINS=http://app.palancaplay.com

# ✅ Good - secure
MOBILE_CORS_ORIGINS=https://app.palancaplay.com
```

3. **Separate origins by environment:**
```env
# Development
MOBILE_CORS_ORIGINS=http://localhost:5173,http://localhost:3000

# Production
MOBILE_CORS_ORIGINS=https://app.palancaplay.com
```

---

## File Locations

- **Mobile CORS Middleware:** `app/Http/Middleware/MobileCorsMiddleware.php`
- **Business CORS Middleware:** `app/Http/Middleware/BusinessCorsMiddleware.php`
- **Route Configuration:** `bootstrap/app.php`
- **Environment Config:** `.env` and `.env.example`

---

## Additional Resources

- [MDN CORS Documentation](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Laravel Middleware Documentation](https://laravel.com/docs/middleware)
