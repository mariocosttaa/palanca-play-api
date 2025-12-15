# Palanca Play API

A comprehensive Laravel API for managing padel/tennis court bookings with separate endpoints for mobile applications and web management dashboards.

## 🚀 Features

### Core Functionality
- ✅ **Dual API Architecture** - Separate Mobile and Business APIs
- ✅ **Multi-tenant System** - Support for multiple court facilities
- ✅ **Court Management** - Court types, courts, images, and availability
- ✅ **Booking System** - Create, update, cancel bookings with QR codes
- ✅ **User Management** - Mobile users and business users with role-based access
- ✅ **Client Management** - Business users can manage their clients
- ✅ **Financial Reports** - Monthly and yearly revenue reports with statistics
- ✅ **Subscription Management** - Tenant subscription plans and invoicing

### Communication & Notifications
- ✅ **In-App Notifications** - Real-time notifications for booking events
- ✅ **Email System** - Professional HTML emails with queue support
- ✅ **Password Recovery** - Email-based password reset with 6-digit codes
- ✅ **Multi-language Support** - Business users can set language preference (en, pt, es, fr)

### Email Features
- ✅ **Booking Emails** - Confirmation, update, and cancellation emails
- ✅ **Queue System** - Async email processing with status tracking
- ✅ **Email History** - Track all sent emails with status (pending/sent/failed)
- ✅ **Professional Templates** - Clean white and green design with Palanca Play branding

### Advanced Features
- ✅ **QR Code System** - Generate and verify booking QR codes
- ✅ **Booking History** - Track past bookings with presence status
- ✅ **Auto-confirmation** - Optional automatic booking confirmation per tenant
- ✅ **HashID Security** - All IDs are hashed for security

---

## 🏗️ API Architecture

This project uses **separated API routes** for two distinct client types:

### 📱 Mobile API (`/api/mobile/v1/*`)
**For:** Regular users accessing via mobile apps (iOS, Android)

- **Route File:** `routes/api-mobile.php`
- **Base URL:** `/api/mobile/v1`
- **Authentication Guard:** `auth:sanctum` (default)
- **User Model:** `App\Models\User`
- **Documentation:** [`docs/API_MOBILE.md`](docs/API_MOBILE.md)

**Key Features:**
- User authentication (register, login, logout)
- Password reset via email
- Browse courts and availability (public)
- Create and manage bookings
- In-app notifications
- Booking statistics and history

### 🌐 Business API (`/api/business/v1/*`)
**For:** Business users/managers accessing via web dashboard

- **Route File:** `routes/api-business.php`
- **Base URL:** `/api/business/v1`
- **Authentication Guard:** `auth:business`
- **User Model:** `App\Models\BusinessUser`
- **Documentation:** [`docs/API_BUSINESS.md`](docs/API_BUSINESS.md)

**Key Features:**
- Business user authentication
- Multi-language profile management
- Tenant management
- Court types and courts CRUD
- Court images and availability management
- Booking management with QR verification
- Client management
- Financial reports (monthly/yearly)
- Subscription and invoice management

---

## 📂 Project Structure

```
routes/
├── api-mobile.php          # Mobile API routes (regular users)
└── api-business.php        # Business API routes (managers/web)

app/
├── Http/Controllers/Api/V1/
│   ├── Mobile/             # Mobile API controllers
│   │   ├── Auth/
│   │   ├── MobileBookingController.php
│   │   ├── NotificationController.php
│   │   └── PasswordResetController.php
│   └── Business/           # Business API controllers
│       ├── Auth/
│       ├── TenantController.php
│       ├── CourtController.php
│       ├── BookingController.php
│       ├── ClientController.php
│       ├── FinancialController.php
│       └── BookingVerificationController.php
├── Models/
│   ├── User.php
│   ├── BusinessUser.php
│   ├── Tenant.php
│   ├── Court.php
│   ├── Booking.php
│   ├── Notification.php
│   ├── EmailSent.php
│   └── PasswordResetCode.php
├── Services/
│   ├── NotificationService.php
│   └── EmailService.php
├── Jobs/
│   └── SendEmailJob.php
└── Mail/
    ├── BookingCreated.php
    ├── BookingUpdated.php
    ├── BookingCancelled.php
    └── PasswordResetCode.php

resources/views/emails/
├── booking-created.blade.php
├── booking-updated.blade.php
├── booking-cancelled.blade.php
└── password-reset-code.blade.php

docs/
├── API_MOBILE.md           # Mobile API documentation
├── API_BUSINESS.md         # Business API documentation
└── MAILHOG_SETUP.md        # Email testing setup
```

---

## � Getting Started

### Prerequisites
- PHP 8.1+
- Composer
- SQLite (for development) or MySQL/PostgreSQL (for production)
- Docker (optional, for Mailhog email testing)

### Installation

1. **Clone the repository**
```bash
git clone <repository-url>
cd palanca-play-api
```

2. **Install dependencies**
```bash
composer install
```

3. **Configure environment**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database**
```env
DB_CONNECTION=sqlite
# Or for MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=palanca_play
# DB_USERNAME=root
# DB_PASSWORD=
```

5. **Run migrations**
```bash
php artisan migrate
```

6. **Start development server**
```bash
php artisan serve
```

---

## 📧 Email Testing with Mailhog

For local email testing, we use Mailhog to capture all outgoing emails.

### Quick Setup

1. **Start Mailhog**
```bash
docker-compose up -d
```

2. **Configure .env**
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@palancaplay.com"
MAIL_FROM_NAME="Palanca Play"
```

3. **Clear config cache**
```bash
php artisan config:clear
```

4. **Test emails**
```bash
# Test all email types
php artisan email:test all

# Test specific email
php artisan email:test password-reset
php artisan email:test booking-created
php artisan email:test booking-cancelled
```

5. **View emails**
Open http://localhost:8025 in your browser

**For detailed setup instructions, see [`docs/MAILHOG_SETUP.md`](docs/MAILHOG_SETUP.md)**

---

## 🔄 Queue System

Emails are processed asynchronously using Laravel queues for better performance.

### Running Queue Worker

**Development:**
```bash
# Process all jobs and stop
php artisan queue:work --stop-when-empty

# Process one job
php artisan queue:work --once

# Keep worker running
php artisan queue:work
```

**Production:**
Use Laravel Horizon or Supervisor to manage queue workers.

---

## 🌐 CORS Configuration

The API uses **separate CORS configurations** for Mobile and Business APIs.

### Environment Variables

```env
# Mobile API CORS - For mobile apps (iOS, Android, React Native, etc.)
MOBILE_CORS_ORIGINS=http://localhost:5173,http://localhost:3000,http://localhost:19006

# Business API CORS - For web dashboard (React, Vue, Angular, etc.)
BUSINESS_CORS_ORIGINS=http://localhost:5173,http://localhost:3000,http://localhost:8080
```

### Default Allowed Origins

**Mobile API:**
- `http://localhost:5173` (Vite)
- `http://localhost:3000` (React/Next.js)
- `http://localhost:19006` (Expo/React Native)

**Business API:**
- `http://localhost:5173` (Vite)
- `http://localhost:3000` (React/Next.js)
- `http://localhost:8080` (Vue)

### Adding New Origins

1. Update `.env` with comma-separated URLs
2. Run `php artisan config:clear`

**For detailed CORS setup, testing, and troubleshooting, see [`docs/CORS_SETUP.md`](docs/CORS_SETUP.md)**

---

## 🧪 Testing

Tests are organized by API type:

```bash
# Run all tests
php artisan test

# Run mobile API tests
php artisan test tests/Feature/Api/Mobile

# Run business API tests
php artisan test tests/Feature/Api/Business

# Run specific test
php artisan test --filter=UserAuthTest
```

---

## 📚 Documentation

### API Documentation
- **[Mobile API](docs/API_MOBILE.md)** - Complete mobile API reference with examples
- **[Business API](docs/API_BUSINESS.md)** - Complete business API reference with examples

### Setup Guides
- **[Mailhog Setup](docs/MAILHOG_SETUP.md)** - Email testing configuration
- **[CORS Setup](docs/CORS_SETUP.md)** - CORS configuration for Mobile and Business APIs

### System Documentation
- **General Patterns:** See `docs/backend/` for API patterns and conventions
- **System Configuration:** See `docs/system-config/` for project-specific structure
- **Testing:** See `docs/tests/` for testing patterns and checklists

---

## 🔐 Authentication

### Mobile API
Uses Laravel Sanctum with default guard:
```http
Authorization: Bearer {token}
```

### Business API
Uses Laravel Sanctum with `business` guard:
```http
Authorization: Bearer {token}
```

---

## 💾 Database Schema

### Core Tables
- `users` - Mobile app users
- `business_users` - Business/manager users
- `tenants` - Court facilities (multi-tenant)
- `courts` - Individual courts
- `court_types` - Court type categories (Padel, Tennis, etc.)
- `bookings` - Court reservations
- `notifications` - In-app notifications
- `emails_sent` - Email history with status tracking
- `password_reset_codes` - Password recovery codes

### Key Features
- Multi-tenant architecture
- HashID for external IDs
- Soft deletes where applicable
- Timestamps on all tables

---

## 🌍 Multi-language Support

Business users can set their preferred language:
- `en` - English
- `pt` - Portuguese (default)
- `es` - Spanish
- `fr` - French

Update language via:
```http
PATCH /api/business/v1/profile/language
{
  "language": "en"
}
```

---

## 💰 Pricing

All prices are stored in **cents** to avoid floating-point issues:
- Database: `5000` (cents)
- Display: `$50.00` (divide by 100)

---

## 🎨 Email Templates

Professional HTML email templates with:
- Clean white and green design (#2d5f3f)
- Palanca Play branding
- Mobile-friendly responsive layouts
- Clear call-to-actions
- Security warnings where appropriate

---

## 📊 Financial Reports

Business users can access:
- **Current Month Report** - Real-time revenue and booking stats
- **Monthly Reports** - Historical monthly data with daily breakdown
- **Monthly Statistics** - Average booking value, percentages, busiest days
- **Yearly Statistics** - Annual overview with monthly breakdown

---

## 🔔 Notifications

In-app notifications are automatically created for:
- ✅ Booking created
- ✅ Booking cancelled
- ⏳ Booking updated (when implemented)

Notifications include:
- Subject and message
- Read/unread status
- Timestamps
- User and tenant relationships

---

## 📝 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🤝 Contributing

Contributions are welcome! Please follow Laravel coding standards and include tests for new features.

---

## 📞 Support

For issues and questions, please open an issue on the repository.
