# Database Schema Documentation

This document describes the complete database schema for the Padel Booking API system.

## Overview

The system manages a multi-tenant court booking platform where:
- **Tenants** (organizations/businesses) own and manage courts
- **Business Users** manage tenants and their courts
- **Users** (customers) make bookings for courts
- **Subscription Plans** define tenant capabilities and billing
- **Courts** are organized by type and have availability schedules

## Entity Relationship Diagram

The database consists of 12 main tables with the following relationships:
- Multi-tenant architecture with `tenants` as the central entity
- User management split between `users` (customers) and `business_users` (administrators)
- Flexible court management with types, individual courts, and availability rules
- Subscription and invoicing system for tenant billing

---

## Tables

### 1. `countries`

Stores country information used for user location and phone number formatting.

**Columns:**
- `id` (bigint, PK) - Primary key
- `name` (varchar(255)) - Country name
- `capital_city` (varchar(255), nullable) - Capital city name
- `code` (varchar(10)) - Country code (ISO)
- `calling_code` (varchar(10)) - International calling code
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp

**Relationships:**
- One-to-many with `users` (a country can have many users)
- One-to-many with `business_users` (a country can have many business users)

---

### 2. `users`

Stores information about end users (customers) who make court bookings.

**Columns:**
- `id` (bigint, PK) - Primary key
- `name` (varchar) - User's first name
- `surname` (varchar, nullable) - User's last name
- `email` (varchar) - User's email address (unique)
- `google_login` (tinyint(1)) - Whether user uses Google authentication
- `country_id` (bigint, nullable, FK) - Foreign key to `countries.id`
- `calling_code` (varchar, nullable) - Phone calling code
- `phone` (varchar, nullable) - Phone number
- `timezone` (varchar, nullable) - User's timezone
- `password` (varchar, nullable) - Hashed password (nullable if Google login)
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp
- `deleted_at` (datetime, nullable) - Soft delete timestamp

**Relationships:**
- Many-to-one with `countries` (a user belongs to one country)
- One-to-many with `bookings` (a user can make multiple bookings)

**Indexes:**
- `email` should be unique
- `country_id` should be indexed for foreign key lookups

---

### 3. `business_users`

Stores information about users who manage tenants (court administrators, managers).

**Columns:**
- `id` (bigint, PK) - Primary key
- `name` (varchar) - Business user's first name
- `surname` (varchar, nullable) - Business user's last name
- `email` (varchar) - Business user's email address (unique)
- `google_login` (tinyint(1)) - Whether user uses Google authentication
- `country_id` (bigint, nullable, FK) - Foreign key to `countries.id`
- `calling_code` (varchar, nullable) - Phone calling code
- `phone` (varchar, nullable) - Phone number
- `timezone` (varchar, nullable) - User's timezone
- `password` (varchar, nullable) - Hashed password (nullable if Google login)
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp
- `deleted_at` (datetime, nullable) - Soft delete timestamp

**Relationships:**
- Many-to-one with `countries` (a business user belongs to one country)
- Many-to-many with `tenants` via `business_users_tenants` (a business user can manage multiple tenants)

**Indexes:**
- `email` should be unique
- `country_id` should be indexed for foreign key lookups

---

### 4. `tenants`

Represents organizations or businesses that own and manage courts. This is the central entity in the multi-tenant architecture.

**Columns:**
- `id` (bigint, PK) - Primary key
- `name` (varchar(255)) - Tenant/organization name
- `address` (varchar(512), nullable) - Physical address
- `latitude` (decimal(10,7), nullable) - Geographic latitude
- `longitude` (decimal(10,7), nullable) - Geographic longitude
- `auto_confirm_bookings` (tinyint(1)) - Whether bookings are automatically confirmed
- `booking_interval_minutes` (int) - Minimum time interval between bookings
- `buffer_between_bookings_minutes` (int) - Buffer time required between consecutive bookings
- `subscription_plan_id` (bigint, nullable, FK) - Foreign key to `subscription_plans.id`
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp
- `deleted_at` (datetime, nullable) - Soft delete timestamp

**Relationships:**
- Many-to-one with `subscription_plans` (a tenant has one subscription plan)
- Many-to-many with `business_users` via `business_users_tenants` (a tenant can have many business users)
- One-to-many with `courts_type` (a tenant can define many court types)
- One-to-many with `courts_availabilities` (a tenant has many availability rules)
- One-to-many with `bookings` (a tenant receives many bookings)
- One-to-many with `invoices` (a tenant has many invoices)

**Indexes:**
- `subscription_plan_id` should be indexed for foreign key lookups
- Consider composite index on `latitude` and `longitude` for geographic queries

---

### 5. `business_users_tenants`

Junction table managing the many-to-many relationship between business users and tenants. Also stores role information for the relationship.

**Columns:**
- `id` (bigint, PK) - Primary key
- `business_user_id` (bigint, FK) - Foreign key to `business_users.id`
- `tenant_id` (bigint, FK) - Foreign key to `tenants.id`
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp

**Relationships:**
- Many-to-one with `business_users`
- Many-to-one with `tenants`

**Indexes:**
- Composite unique index on `(business_user_id, tenant_id)` to prevent duplicates
- Index on `business_user_id` for lookups
- Index on `tenant_id` for lookups

---

### 6. `subscription_plans`

Defines different subscription plans available for tenants, including pricing and feature limits.

**Columns:**
- `id` (bigint, PK) - Primary key
- `name` (varchar) - Plan name (e.g., "Basic", "Premium")
- `slug` (varchar) - URL-friendly identifier
- `max_courts` (int) - Maximum number of courts allowed
- `price` (decimal) - Monthly/annual subscription price
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp

**Relationships:**
- One-to-many with `tenants` (a subscription plan can be used by many tenants)
- One-to-many with `invoices` (a subscription plan can be associated with many invoices)

**Indexes:**
- `slug` should be unique

---

### 7. `invoices`

Stores invoice details for tenant subscriptions and extra court charges.

**Columns:**
- `id` (bigint, PK) - Primary key
- `tenant_id` (bigint, FK) - Foreign key to `tenants.id`
- `subscription_plan_id` (bigint, nullable, FK) - Foreign key to `subscription_plans.id`
- `period` (varchar(50), nullable) - Billing period (e.g., "monthly", "annual")
- `date_start` (datetime) - Invoice period start date
- `date_end` (datetime) - Invoice period end date
- `price` (decimal(10,2)) - Invoice amount
- `is_extra_court` (tinyint(1)) - Whether this invoice is for extra courts beyond plan limit
- `status` (varchar(50)) - Invoice status (e.g., "pending", "paid", "cancelled")
- `metadata` (json, nullable) - Additional invoice metadata
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp

**Relationships:**
- Many-to-one with `tenants` (an invoice belongs to one tenant)
- Many-to-one with `subscription_plans` (an invoice is for one subscription plan)

**Indexes:**
- `tenant_id` should be indexed for foreign key lookups
- `subscription_plan_id` should be indexed for foreign key lookups
- Consider index on `status` for filtering
- Consider index on `date_start` and `date_end` for period queries

---

### 8. `courts_type`

Defines different types of courts that a tenant can offer (e.g., "Padel Court", "Tennis Court", "Squash Court").

**Columns:**
- `id` (bigint, PK) - Primary key
- `tenant_id` (bigint, FK) - Foreign key to `tenants.id`
- `name` (varchar(255)) - Court type name
- `description` (text, nullable) - Detailed description
- `interval_time_minutes` (int) - Default booking interval for this court type
- `buffer_time_minutes` (int) - Default buffer time between bookings
- `status` (tinyint(1)) - Whether this court type is active
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp
- `deleted_at` (datetime, nullable) - Soft delete timestamp

**Relationships:**
- Many-to-one with `tenants` (a court type belongs to one tenant)
- One-to-many with `court` (a court type can have many individual courts)
- One-to-many with `courts_availabilities` (a court type can have many availability rules)

**Indexes:**
- `tenant_id` should be indexed for foreign key lookups
- Consider index on `status` for filtering active types

---

### 9. `court`

Represents individual courts available for booking. Each court belongs to a court type.

**Columns:**
- `id` (bigint, PK) - Primary key
- `court_type_id` (bigint, FK) - Foreign key to `courts_type.id`
- `type` (enum) - Court type classification
- `name` (varchar, nullable) - Court name/identifier
- `number` (varchar, nullable) - Court number
- `status` (tinyint(1)) - Whether this court is available for booking
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp
- `deleted_at` (datetime, nullable) - Soft delete timestamp

**Relationships:**
- Many-to-one with `courts_type` (a court is of one type)
- One-to-many with `courts_images` (a court can have many images)
- One-to-many with `courts_availabilities` (a court can have many availability rules)
- One-to-many with `bookings` (a court can have many bookings)

**Indexes:**
- `court_type_id` should be indexed for foreign key lookups
- Consider index on `status` for filtering available courts
- Consider composite index on `(court_type_id, status)` for common queries

---

### 10. `courts_images`

Stores image information for courts, including primary image designation.

**Columns:**
- `id` (bigint, PK) - Primary key
- `court_id` (bigint, FK) - Foreign key to `court.id`
- `path` (varchar) - File path or URL to the image
- `alt` (varchar, nullable) - Alt text for accessibility
- `is_primary` (tinyint(1)) - Whether this is the primary image for the court
- `created_at` (datetime, nullable) - Record creation timestamp

**Relationships:**
- Many-to-one with `court` (an image belongs to one court)

**Indexes:**
- `court_id` should be indexed for foreign key lookups
- Consider composite index on `(court_id, is_primary)` for finding primary images

**Constraints:**
- Consider unique constraint on `(court_id, is_primary)` where `is_primary = 1` to ensure only one primary image per court

---

### 11. `courts_availabilities`

Defines availability schedules for courts or court types. Supports both recurring (day of week) and specific date availability rules.

**Columns:**
- `id` (bigint, PK) - Primary key
- `tenant_id` (bigint, FK) - Foreign key to `tenants.id`
- `court_id` (bigint, nullable, FK) - Foreign key to `court.id` (null if applies to all courts of a type)
- `court_type_id` (bigint, nullable, FK) - Foreign key to `courts_type.id` (null if applies to specific court)
- `day_of_week_recurring` (varchar(20), nullable) - Day of week for recurring availability (e.g., "monday", "tuesday")
- `specific_date` (date, nullable) - Specific date for one-time availability rules
- `start_time` (time) - Availability start time
- `end_time` (time) - Availability end time
- `breaks` (json, nullable) - JSON array of break periods (e.g., lunch breaks)
- `is_available` (tinyint(1)) - Whether this time slot is available (can be used for blackout periods)
- `created_at` (datetime, nullable) - Record creation timestamp
- `updated_at` (datetime, nullable) - Record update timestamp

**Relationships:**
- Many-to-one with `tenants` (an availability record belongs to one tenant)
- Many-to-one with `court` (an availability record can be for a specific court)
- Many-to-one with `courts_type` (an availability record can be for a specific court type)

**Indexes:**
- `tenant_id` should be indexed for foreign key lookups
- `court_id` should be indexed (nullable foreign key)
- `court_type_id` should be indexed (nullable foreign key)
- Consider index on `specific_date` for date-based queries
- Consider index on `day_of_week_recurring` for recurring availability queries
- Consider composite index on `(tenant_id, specific_date)` for tenant-specific date queries

**Constraints:**
- Either `court_id` OR `court_type_id` should be set (not both, not neither)
- Either `day_of_week_recurring` OR `specific_date` should be set (not both, not neither)

---

### 12. `bookings`

Records individual court bookings made by users. Tracks booking status, payment, and scheduling information.

**Columns:**
- `id` (bigint, PK) - Primary key
- `tenant_id` (bigint, FK) - Foreign key to `tenants.id`
- `court_id` (bigint, FK) - Foreign key to `court.id`
- `user_id` (bigint, FK) - Foreign key to `users.id`
- `start_date` (date) - Booking start date
- `end_date` (date) - Booking end date
- `start_time` (time) - Booking start time
- `end_time` (time) - Booking end time
- `price` (bigint) - Booking price (stored as integer, likely in cents)
- `is_pending` (tinyint(1)) - Whether booking is pending confirmation
- `is_cancelled` (tinyint(1)) - Whether booking has been cancelled
- `is_paid` (tinyint(1)) - Whether booking has been paid

**Relationships:**
- Many-to-one with `tenants` (a booking belongs to one tenant)
- Many-to-one with `court` (a booking is for one court)
- Many-to-one with `users` (a booking is made by one user)

**Indexes:**
- `tenant_id` should be indexed for foreign key lookups
- `court_id` should be indexed for foreign key lookups
- `user_id` should be indexed for foreign key lookups
- Consider composite index on `(court_id, start_date, start_time)` for availability checks
- Consider composite index on `(tenant_id, start_date)` for tenant booking queries
- Consider index on `is_cancelled` and `is_pending` for filtering

**Constraints:**
- `end_time` should be after `start_time`
- `end_date` should be >= `start_date`
- If `end_date` equals `start_date`, then `end_time` should be > `start_time`

---

## Key Relationships Summary

### Multi-Tenant Architecture
- All tenant-specific data (courts, bookings, availabilities) links to `tenants.id`
- Business users can manage multiple tenants via `business_users_tenants`
- Subscription plans define tenant capabilities

### User Management
- **Users**: End customers who make bookings
- **Business Users**: Administrators who manage tenants
- Both user types share similar structure but serve different purposes

### Court Hierarchy
```
Tenant
  └── Court Types (courts_type)
       └── Individual Courts (court)
            └── Images (courts_images)
            └── Bookings (bookings)
  └── Availabilities (courts_availabilities) [can be per type or per court]
```

### Booking Flow
1. User selects a court (filtered by tenant)
2. System checks `courts_availabilities` for available time slots
3. System checks existing `bookings` for conflicts
4. Booking is created with pending status
5. Booking is confirmed (if auto-confirm) or manually confirmed
6. Payment is processed and `is_paid` is updated

---

## Design Patterns

### Soft Deletes
The following tables use soft deletes (`deleted_at`):
- `users`
- `business_users`
- `tenants`
- `courts_type`
- `court`

### Timestamps
All tables include `created_at` and `updated_at` timestamps (except `courts_images` which only has `created_at`).

### Multi-Tenancy
All tenant-specific data includes `tenant_id` to ensure data isolation:
- `bookings`
- `courts_type`
- `courts_availabilities`
- `invoices`

### Flexible Availability
The `courts_availabilities` table supports:
- Recurring schedules (day of week)
- One-time schedules (specific dates)
- Court-specific or court-type-specific rules
- Break periods stored as JSON

---

## Notes for Implementation

1. **ID Obfuscation**: Per API patterns, all external IDs should be hashed using Hashids before being returned to clients.

2. **Tenant Isolation**: All queries must filter by `tenant_id` (typically from middleware) to ensure proper data isolation.

3. **Booking Validation**: When creating bookings, validate:
   - Court availability based on `courts_availabilities`
   - No conflicts with existing `bookings`
   - Respect `booking_interval_minutes` and `buffer_between_bookings_minutes` from tenant settings
   - Respect court type and court-specific interval/buffer settings

4. **Subscription Limits**: Enforce `max_courts` from `subscription_plans` when tenants create new courts.

5. **Geographic Queries**: Use `latitude` and `longitude` from `tenants` for location-based searches.

6. **Image Storage**: The `path` in `courts_images` should follow the file upload patterns (likely stored in S3 or local storage).

