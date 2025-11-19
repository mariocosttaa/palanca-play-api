<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Padel Booking API

A Laravel API for managing padel court bookings with separate endpoints for mobile applications and web management dashboards.

## 🏗️ API Architecture

This project uses **separated API routes** for two distinct client types:

### 📱 Mobile API (`/api/v1/*`)
**For:** Regular users accessing via mobile apps (iOS, Android)

- **Route File:** `routes/api-mobile.php`
- **Base URL:** `/api/v1`
- **Authentication Guard:** `auth:sanctum` (default)
- **User Model:** `App\Models\User`
- **Controller:** `App\Http\Controllers\Api\V1\Mobile\Auth\UserAuthController`

**Example Endpoints:**
- `POST /api/v1/users/register` - User registration
- `POST /api/v1/users/login` - User login
- `POST /api/v1/users/logout` - User logout
- `GET /api/v1/users/me` - Get authenticated user profile

### 🌐 Business API (`/business/v1/*`)
**For:** Business users/managers accessing via web dashboard

- **Route File:** `routes/api-business.php`
- **Base URL:** `/business/v1`
- **Authentication Guard:** `auth:business`
- **User Model:** `App\Models\BusinessUser`
- **Controller:** `App\Http\Controllers\Api\V1\Business\Auth\BusinessUserAuthController`

**Example Endpoints:**
- `POST /business/v1/business-users/register` - Business user registration
- `POST /business/v1/business-users/login` - Business user login
- `POST /business/v1/business-users/logout` - Business user logout
- `GET /business/v1/business-users/me` - Get authenticated business user profile

## 📂 Project Structure

```
routes/
├── api-mobile.php      # Mobile API routes (regular users)
└── api-business.php    # Business API routes (managers/web)

app/Http/Controllers/Api/V1/
├── Mobile/             # Mobile API controllers
│   └── Auth/
│       └── UserAuthController.php
└── Business/           # Business API controllers
    └── Auth/
        └── BusinessUserAuthController.php

tests/Feature/Api/
├── Mobile/             # Tests for mobile API
│   └── UserAuthTest.php
└── Business/           # Tests for business API
    └── BusinessUserAuthTest.php
```

## 🧪 Testing

Tests are organized by API type:

```bash
# Run mobile API tests
php artisan test tests/Feature/Api/Mobile

# Run business API tests
php artisan test tests/Feature/Api/Business

# Run all API tests
php artisan test tests/Feature/Api
```

## 📚 Documentation

- **General Patterns:** See `docs/backend/` for API patterns and conventions
- **System Configuration:** See `docs/system-config/` for project-specific structure:
  - [Mobile API Structure](docs/system-config/mobile-api-structure.md)
  - [Business API Structure](docs/system-config/business-api-structure.md)
  - [Database Schema](docs/system-config/database-schema.md)
- **Testing:** See `docs/tests/` for testing patterns and checklists

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
