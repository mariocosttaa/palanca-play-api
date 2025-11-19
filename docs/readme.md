# API Patterns Documentation

## 📚 Overview
This directory contains the official patterns for **separated API applications** (Laravel Backend + Frontend).

## 📁 Documentation Index

### Core Patterns
- **[Controllers](backend/api-controller-patterns.md)**: Naming, RESTful actions, response structure.
- **[Requests](backend/api-request-patterns.md)**: Validation, authorization, data normalization.
- **[Resources](backend/api-resource-patterns.md)**: JSON transformation, General vs Specific resources.
- **[Models](backend/model-patterns.md)**: Data structure, relationships, and business logic.
- **[Authentication](backend/api-authentication-patterns.md)**: Token handling, middleware, policies.
- **[Hashids](backend/hashids-patterns.md)**: ID obfuscation logic and encoding/decoding.

### Feature Patterns
- **[Error Handling](backend/api-error-handling-patterns.md)**: Standardized error responses and codes.
- **[Pagination](backend/api-pagination-patterns.md)**: Collection pagination and meta data.
- **[File Uploads](backend/api-file-upload-patterns.md)**: Multipart handling, S3/Storage, and secure access.
- **[Migrations](backend/migration-patterns.md)**: Database schema changes and version control.
- **[Seeders](backend/seeder-patterns.md)**: Database population patterns.
- **[Email](backend/email-patterns.md)**: Email templates and Mailable classes.
- **[PDF](backend/pdf-patterns.md)**: PDF generation service and templates.

### Architecture & Quality
- **[Security](backend/api-security-patterns.md)**: Rate limiting, CORS, headers, and data protection.
- **[Testing](backend/api-testing-patterns.md)**: Feature tests and JSON assertions.
- **[Versioning](backend/api-versioning-patterns.md)**: URL versioning strategy (V1/V2).

## 🏆 The Golden Rules

1.  **Always Return Resources**: Never return raw Eloquent models; always use API Resources.
2.  **Hash All IDs**: External IDs must be hashed (e.g., `user-id` -> `Xy7z...`). Decode them in Requests.
3.  **No Tenant ID in Requests**: `tenant_id` comes from middleware/attributes, never from user input.
4.  **Validate via Classes**: Use `FormRequest` classes for all validation.
5.  **Standardized Responses**: Follow the consistent JSON structure (`data`, `meta`, etc.).
6.  **MANDATORY TESTS**: Every new API endpoint MUST have corresponding Feature tests. No endpoint is complete without tests. See [Testing Patterns](backend/api-testing-patterns.md).

## 🏗️ Project-Specific Structure

This project uses **separated API routes** for mobile and business endpoints. See project-specific documentation:

### System Configuration
- **[Mobile API Structure](system-config/mobile-api-structure.md)**: Mobile API endpoints for regular users (iOS, Android apps)
- **[Business API Structure](system-config/business-api-structure.md)**: Business API endpoints for managers/web dashboard
- **[Database Schema](system-config/database-schema.md)**: Complete database structure documentation

### Key Rules for This Project

1. **Separate Route Files**: 
   - Mobile endpoints → `routes/api-mobile.php` under `/api/v1/` prefix
   - Business endpoints → `routes/api-business.php` under `/business/v1/` prefix

2. **Separate Controller Directories**: 
   - Mobile controllers → `app/Http/Controllers/Api/V1/Mobile/`
   - Business controllers → `app/Http/Controllers/Api/V1/Business/`

3. **Separate Test Directories**: 
   - Mobile tests → `tests/Feature/Api/Mobile/`
   - Business tests → `tests/Feature/Api/Business/`

4. **Guard Usage**:
   - Mobile routes: Use `auth:sanctum` middleware (default)
   - Business routes: Use `auth:business` middleware

5. **Testing with Sanctum**:
   - Mobile tests: `Sanctum::actingAs($user)`
   - Business tests: `Sanctum::actingAs($businessUser, [], 'business')`

For detailed structure information, see the [system-config](system-config/) documentation files.
