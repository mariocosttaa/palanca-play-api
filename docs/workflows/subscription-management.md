---
description: Workflow for managing and verifying tenant subscriptions and access control.
---

# Subscription Management Workflow

This document outlines the architecture and workflow for managing tenant subscriptions, enforcing limits, and handling access control within the Palanca Play API.

## Overview

The subscription system is built around the concept of **Invoices**. A tenant's subscription status and limits are determined by their latest *valid* invoice.

- **Source of Truth**: The `invoices` table.
- **Validity**: An invoice is valid if `status = 'paid'` and `date_end >= now()`.
- **Limits**: The `max_courts` column on the invoice determines how many courts a tenant can create.

## Middleware Architecture

Access control is enforced by two middleware layers applied to tenant-scoped business routes (`routes/api-business.php`).

### 1. `CheckTenantSubscription`
- **Purpose**: Validates subscription status and injects context.
- **Logic**:
    1. Checks for the latest valid invoice for the current tenant.
    2. Injects the invoice (or `null`) into the request as `$request->valid_invoice`.
    3. **Does NOT block** requests. It only provides the state.

### 2. `BlockSubscriptionCrud`
- **Purpose**: Enforces access control based on subscription status.
- **Logic**:
    1. If `$request->valid_invoice` exists, allow the request.
    2. If **NO** valid invoice:
        - **Allow**: Read-only operations (`GET`, `HEAD`, `OPTIONS`).
        - **Allow**: Specific "safe" routes (e.g., `tenant.update` for profile/billing management).
        - **Block**: All other write operations (`POST`, `PUT`, `DELETE`) with a `403 Forbidden` error and code `SUBSCRIPTION_EXPIRED_CRUD_BLOCKED`.

## Limit Enforcement

Resource limits (specifically Court creation) are enforced at the Controller level, using the injected invoice.

- **Controller**: `App\Http\Controllers\Api\V1\Business\CourtController`
- **Logic**:
    ```php
    $maxCourts = $request->valid_invoice->max_courts;
    if ($currentCount >= $maxCourts) {
        // Return 403 Forbidden
    }
    ```

## Verification

We have a comprehensive test suite to verify this workflow.

### Key Tests
- **`SubscriptionMiddlewareTest.php`**: Verifies that the middleware correctly allows/blocks requests based on invoice status.
- **`BlockSubscriptionCrudTest.php`**: Verifies that CRUD operations are blocked for expired subscriptions, while profile updates and reads are allowed.
- **`CourtSubscriptionTest.php`**: Verifies that court creation limits are enforced based on the invoice's `max_courts`.

### Running Tests
To verify the subscription system, run the following command:

```bash
php artisan test --filter=Subscription
```

Or run the specific test files:

```bash
php artisan test tests/Feature/Api/Business/BlockSubscriptionCrudTest.php
php artisan test tests/Feature/Api/Business/SubscriptionMiddlewareTest.php
php artisan test tests/Feature/Api/Business/CourtSubscriptionTest.php
```
