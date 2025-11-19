# API Testing Documentation

This directory contains the Postman collection for testing the Padel Booking API.

## 🚨 MANDATORY: Testing Requirements

**Every new API endpoint MUST have corresponding Feature tests before implementation is considered complete.**

### Testing Workflow:
1. **Implement endpoint** in Controller
2. **Immediately create tests** in `tests/Feature/Api/`
3. **Follow test patterns** from `docs/backend/api-testing-patterns.md`
4. **Run tests**: `php artisan test --filter YourTest`
5. **Ensure all tests pass** before committing

### Test Requirements:
- ✅ Happy path (successful request)
- ✅ Validation errors (422)
- ✅ Authentication (401 for protected routes)
- ✅ Authorization (403 if applicable)
- ✅ Database assertions
- ✅ Response structure validation

See [API Testing Patterns](../../backend/api-testing-patterns.md) for complete examples.

## 📁 Files

- **`api-test-collection.json`** - Postman/Hoppscotch collection (importable)

## 🚀 Quick Start

### Import into Hoppscotch/Postman

1. **Hoppscotch:**
   - Open [Hoppscotch](https://hoppscotch.io)
   - Click **Collections** → **Import**
   - Select **Postman Collection**
   - Upload `api-test-collection.json`

2. **Postman:**
   - Open Postman
   - Click **Import**
   - Select `api-test-collection.json`

### Configure Variables

After importing, update the `base_url` variable:
- Local: `http://localhost:8000`
- Staging: `https://staging-api.example.com`
- Production: `https://api.example.com`

## 🔄 Auto-Update Collection

When you add new API endpoints, automatically update the Postman collection:

```bash
php artisan generate:postman-collection
```

This command:
- Scans all API routes in `routes/api.php`
- Generates requests for each endpoint
- Adds authentication headers automatically
- Auto-saves tokens for login/register endpoints
- Updates `docs/tests/api-test-collection.json`

### How It Works

The generator:
1. Reads all routes from `routes/api.php`
2. Groups endpoints by prefix (e.g., `users`, `business-users`)
3. Creates Postman requests with:
   - Correct HTTP methods
   - Authentication headers (if route uses `auth:sanctum`)
   - Token auto-save scripts (for login/register)
   - Proper URL structure

### Manual Updates

If you need to manually update the collection:
1. Edit `docs/tests/api-test-collection.json`
2. Or run the generator: `php artisan generate:postman-collection`

## 📝 Current Endpoints

### Authentication
- **User Authentication:**
  - `POST /api/v1/users/register` - Register new user
  - `POST /api/v1/users/login` - Login user
  - `GET /api/v1/users/me` - Get current user (protected)
  - `POST /api/v1/users/logout` - Logout user (protected)

- **Business User Authentication:**
  - `POST /api/v1/business-users/register` - Register business user
  - `POST /api/v1/business-users/login` - Login business user
  - `GET /api/v1/business-users/me` - Get current business user (protected)
  - `POST /api/v1/business-users/logout` - Logout business user (protected)

## 🔑 Features

- ✅ **Auto-save tokens**: Tokens are automatically saved after login/register
- ✅ **Pre-configured variables**: `base_url`, `user_token`, `business_user_token`
- ✅ **Authentication headers**: Automatically added for protected routes
- ✅ **Auto-update**: Run generator command when adding new endpoints

## 📊 Testing Flow

1. **Register/Login** → Token auto-saved to variable
2. **Use protected endpoints** → Token automatically included in headers
3. **Logout** → Token revoked

## ⚠️ Notes

- The collection is auto-generated from routes
- Manual edits will be overwritten when running the generator
- Always run `php artisan generate:postman-collection` after adding new endpoints
- Tokens are stored in collection variables and persist across requests

## 🔧 Troubleshooting

### Collection not updating?
- Make sure routes are in `routes/api.php`
- Check route names and methods are correct
- Run `php artisan route:list` to verify routes

### Tokens not saving?
- Check response structure matches: `{ data: { token: "..." } }`
- Verify endpoint returns 200/201 status
- Check browser console for JavaScript errors (in Hoppscotch)

### 401 Unauthorized?
- Make sure you've logged in first
- Check token variable is set: `{{user_token}}` or `{{business_user_token}}`
- Verify token hasn't expired
