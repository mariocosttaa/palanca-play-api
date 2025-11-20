-- Padel Booking API - Complete Database Schema
-- Generated from all migrations

-- ============================================
-- Core Laravel Tables
-- ============================================

-- Countries table (must be created first as it's referenced by other tables)
CREATE TABLE IF NOT EXISTS `countries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `capital_city` VARCHAR(255) NULL,
    `code` VARCHAR(10) NOT NULL,
    `calling_code` VARCHAR(10) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `surname` VARCHAR(255) NULL,
    `email` VARCHAR(255) NOT NULL,
    `google_login` TINYINT(1) NOT NULL DEFAULT 0,
    `country_id` BIGINT UNSIGNED NULL,
    `calling_code` VARCHAR(255) NULL,
    `phone` VARCHAR(255) NULL,
    `timezone` VARCHAR(255) NULL,
    `password` VARCHAR(255) NULL,
    `email_verified_at` TIMESTAMP NULL,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_country_id_index` (`country_id`),
    CONSTRAINT `users_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens table
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Business users table
CREATE TABLE IF NOT EXISTS `business_users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `surname` VARCHAR(255) NULL,
    `email` VARCHAR(255) NOT NULL,
    `google_login` TINYINT(1) NOT NULL DEFAULT 0,
    `country_id` BIGINT UNSIGNED NULL,
    `calling_code` VARCHAR(255) NULL,
    `phone` VARCHAR(255) NULL,
    `timezone` VARCHAR(255) NULL,
    `password` VARCHAR(255) NULL,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `business_users_email_unique` (`email`),
    KEY `business_users_country_id_index` (`country_id`),
    CONSTRAINT `business_users_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenants table
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `address` VARCHAR(512) NULL,
    `latitude` DECIMAL(10, 7) NULL,
    `longitude` DECIMAL(10, 7) NULL,
    `auto_confirm_bookings` TINYINT(1) NOT NULL DEFAULT 0,
    `booking_interval_minutes` INT NOT NULL,
    `buffer_between_bookings_minutes` INT NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `tenants_latitude_longitude_index` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Business users tenants pivot table
CREATE TABLE IF NOT EXISTS `business_users_tenants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_user_id` BIGINT UNSIGNED NOT NULL,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `business_users_tenants_business_user_id_tenant_id_unique` (`business_user_id`, `tenant_id`),
    KEY `business_users_tenants_business_user_id_index` (`business_user_id`),
    KEY `business_users_tenants_tenant_id_index` (`tenant_id`),
    CONSTRAINT `business_users_tenants_business_user_id_foreign` FOREIGN KEY (`business_user_id`) REFERENCES `business_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `business_users_tenants_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courts type table
CREATE TABLE IF NOT EXISTS `courts_type` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('football', 'basketball', 'tennis', 'squash', 'badminton', 'padel', 'other') NOT NULL DEFAULT 'padel',
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `interval_time_minutes` INT NOT NULL,
    `buffer_time_minutes` INT NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `courts_type_type_index` (`type`),
    KEY `courts_type_tenant_id_index` (`tenant_id`),
    CONSTRAINT `courts_type_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courts table
CREATE TABLE IF NOT EXISTS `courts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `court_type_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NULL,
    `number` VARCHAR(255) NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `courts_tenant_id_index` (`tenant_id`),
    KEY `courts_court_type_id_index` (`court_type_id`),
    KEY `courts_status_index` (`status`),
    CONSTRAINT `courts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `courts_court_type_id_foreign` FOREIGN KEY (`court_type_id`) REFERENCES `courts_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courts images table
CREATE TABLE IF NOT EXISTS `courts_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `court_id` BIGINT UNSIGNED NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `alt` VARCHAR(255) NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `courts_images_court_id_index` (`court_id`),
    KEY `courts_images_court_id_is_primary_index` (`court_id`, `is_primary`),
    CONSTRAINT `courts_images_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courts availabilities table
CREATE TABLE IF NOT EXISTS `courts_availabilities` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `court_id` BIGINT UNSIGNED NULL,
    `court_type_id` BIGINT UNSIGNED NULL,
    `day_of_week_recurring` VARCHAR(20) NULL,
    `specific_date` DATE NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `breaks` JSON NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `courts_availabilities_tenant_id_index` (`tenant_id`),
    KEY `courts_availabilities_court_id_index` (`court_id`),
    KEY `courts_availabilities_court_type_id_index` (`court_type_id`),
    KEY `courts_availabilities_specific_date_index` (`specific_date`),
    KEY `courts_availabilities_day_of_week_recurring_index` (`day_of_week_recurring`),
    KEY `courts_availabilities_tenant_id_specific_date_index` (`tenant_id`, `specific_date`),
    CONSTRAINT `courts_availabilities_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `courts_availabilities_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `courts_availabilities_court_type_id_foreign` FOREIGN KEY (`court_type_id`) REFERENCES `courts_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings table
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `court_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `price` BIGINT NOT NULL COMMENT 'Stored in cents',
    `is_pending` TINYINT(1) NOT NULL DEFAULT 1,
    `is_cancelled` TINYINT(1) NOT NULL DEFAULT 0,
    `is_paid` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `bookings_tenant_id_index` (`tenant_id`),
    KEY `bookings_court_id_index` (`court_id`),
    KEY `bookings_user_id_index` (`user_id`),
    KEY `bookings_court_id_start_date_start_time_index` (`court_id`, `start_date`, `start_time`),
    KEY `bookings_tenant_id_start_date_index` (`tenant_id`, `start_date`),
    KEY `bookings_is_cancelled_index` (`is_cancelled`),
    KEY `bookings_is_pending_index` (`is_pending`),
    CONSTRAINT `bookings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `bookings_court_id_foreign` FOREIGN KEY (`court_id`) REFERENCES `courts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscription plan table
CREATE TABLE IF NOT EXISTS `subscription_plan` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `courts` INT NOT NULL,
    `price` INT NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `subscription_plan_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices table
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `period` VARCHAR(50) NULL,
    `date_start` DATETIME NOT NULL,
    `date_end` DATETIME NOT NULL,
    `price` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `invoices_tenant_id_index` (`tenant_id`),
    KEY `invoices_status_index` (`status`),
    KEY `invoices_date_start_index` (`date_start`),
    KEY `invoices_date_end_index` (`date_end`),
    CONSTRAINT `invoices_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Laravel Framework Tables
-- ============================================

-- Personal access tokens table (Sanctum)
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255) NOT NULL,
    `tokenable_id` BIGINT UNSIGNED NOT NULL,
    `name` TEXT NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `abilities` TEXT NULL,
    `last_used_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
    KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`),
    KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache table
CREATE TABLE IF NOT EXISTS `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache locks table
CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs table
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Job batches table
CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL,
    `cancelled_at` INT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed jobs table
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


