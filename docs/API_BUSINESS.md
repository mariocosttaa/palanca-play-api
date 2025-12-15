# Business API Documentation

**Base URL:** `/api/business/v1`

**Authentication:** Bearer Token (Sanctum - Business Guard)

---

## Authentication

### Register Business User
**POST** `/business-users/register`

**Public:** Yes

**Request Body:**
```json
{
  "name": "Jane",
  "surname": "Smith",
  "email": "jane@business.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1234567890",
  "country_id": "1"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "bu1",
      "name": "Jane",
      "surname": "Smith",
      "email": "jane@business.com",
      "language": "pt"
    },
    "token": "3|laravel_sanctum_business_token"
  },
  "message": "Business user registered successfully"
}
```

---

### Login Business User
**POST** `/business-users/login`

**Public:** Yes

**Request Body:**
```json
{
  "email": "jane@business.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "bu1",
      "name": "Jane",
      "surname": "Smith",
      "email": "jane@business.com",
      "language": "pt"
    },
    "token": "4|laravel_sanctum_business_token"
  },
  "message": "Login successful"
}
```

---

### Logout Business User
**POST** `/business-users/logout`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### Get Current Business User
**GET** `/business-users/me`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "bu1",
    "name": "Jane",
    "surname": "Smith",
    "email": "jane@business.com",
    "phone": "+1234567890",
    "language": "pt",
    "country": {
      "id": "1",
      "name": "United States"
    }
  }
}
```

---

## Profile Management

### Update Language
**PATCH** `/profile/language`

**Auth Required:** Yes

**Request Body:**
```json
{
  "language": "en"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "language": "en",
    "message": "Idioma atualizado com sucesso"
  }
}
```

**Supported Languages:** `en`, `pt`, `es`, `fr`

---

### Update Profile
**PUT** `/profile`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "Jane",
  "surname": "Smith",
  "phone": "+1234567890",
  "timezone": "America/New_York",
  "language": "en"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "bu1",
      "name": "Jane",
      "surname": "Smith",
      "phone": "+1234567890",
      "language": "en"
    },
    "message": "Perfil atualizado com sucesso"
  }
}
```

---

## Tenant Management

### List User Tenants
**GET** `/business`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "t1",
      "name": "Padel Club Downtown",
      "email": "contact@padelclub.com",
      "phone": "+1234567890",
      "address": "123 Main St",
      "subscription_plan": {
        "id": "sp1",
        "name": "Premium",
        "price": 9900
      }
    }
  ]
}
```

---

### Get Tenant Details
**GET** `/business/{tenant_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "t1",
    "name": "Padel Club Downtown",
    "email": "contact@padelclub.com",
    "phone": "+1234567890",
    "address": "123 Main St",
    "city": "New York",
    "state": "NY",
    "zip_code": "10001",
    "country": {
      "id": "1",
      "name": "United States"
    },
    "subscription_plan": {
      "id": "sp1",
      "name": "Premium"
    },
    "courts_count": 4,
    "active_bookings_count": 12
  }
}
```

---

### Update Tenant
**PUT** `/business/{tenant_id}`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "Padel Club Downtown",
  "email": "contact@padelclub.com",
  "phone": "+1234567890",
  "address": "123 Main St",
  "city": "New York",
  "auto_confirm_bookings": true
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "t1",
    "name": "Padel Club Downtown",
    "auto_confirm_bookings": true
  },
  "message": "Tenant updated successfully"
}
```

---

## Court Types

### List Court Types
**GET** `/business/{tenant_id}/court-types`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "ct1",
      "name": "Padel",
      "description": "Professional padel courts",
      "courts_count": 3
    }
  ]
}
```

---

### Create Court Type
**POST** `/business/{tenant_id}/court-types`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "Padel",
  "description": "Professional padel courts"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "id": "ct1",
    "name": "Padel",
    "description": "Professional padel courts"
  },
  "message": "Court type created successfully"
}
```

---

### Update Court Type
**PUT** `/business/{tenant_id}/court-types/{court_type_id}`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "Padel Pro",
  "description": "Professional padel courts with LED lighting"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "ct1",
    "name": "Padel Pro",
    "description": "Professional padel courts with LED lighting"
  },
  "message": "Court type updated successfully"
}
```

---

### Get Court Type Details
**GET** `/business/{tenant_id}/court-types/{court_type_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "ct1",
    "name": "Padel",
    "description": "Professional padel courts",
    "courts": [
      {
        "id": "c1",
        "name": "Court 1"
      }
    ]
  }
}
```

---

### Delete Court Type
**DELETE** `/business/{tenant_id}/court-types/{court_type_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Court type deleted successfully"
}
```

---

## Courts

### List Courts
**GET** `/business/{tenant_id}/courts`

**Auth Required:** Yes

**Query Parameters:**
- `court_type_id` (optional): Filter by court type

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "c1",
      "name": "Court 1",
      "court_type": {
        "id": "ct1",
        "name": "Padel"
      },
      "price_per_hour": 5000,
      "currency": {
        "code": "USD",
        "symbol": "$"
      },
      "opening_time": "08:00:00",
      "closing_time": "22:00:00",
      "is_active": true
    }
  ]
}
```

---

### Create Court
**POST** `/business/{tenant_id}/courts`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "Court 1",
  "court_type_id": "ct1",
  "description": "Professional padel court",
  "price_per_hour": 5000,
  "currency_id": "1",
  "opening_time": "08:00:00",
  "closing_time": "22:00:00"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "id": "c1",
    "name": "Court 1",
    "price_per_hour": 5000
  },
  "message": "Court created successfully"
}
```

---

### Update Court
**PUT** `/business/{tenant_id}/courts/{court_id}`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "Court 1 - Premium",
  "price_per_hour": 6000,
  "opening_time": "07:00:00",
  "closing_time": "23:00:00"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "c1",
    "name": "Court 1 - Premium",
    "price_per_hour": 6000
  },
  "message": "Court updated successfully"
}
```

---

### Get Court Details
**GET** `/business/{tenant_id}/courts/{court_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "c1",
    "name": "Court 1",
    "description": "Professional padel court",
    "court_type": {
      "id": "ct1",
      "name": "Padel"
    },
    "price_per_hour": 5000,
    "currency": {
      "code": "USD",
      "symbol": "$"
    },
    "opening_time": "08:00:00",
    "closing_time": "22:00:00",
    "images": [
      {
        "id": "img1",
        "url": "https://example.com/images/court1.jpg",
        "is_primary": true
      }
    ]
  }
}
```

---

### Delete Court
**DELETE** `/business/{tenant_id}/courts/{court_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Court deleted successfully"
}
```

---

## Court Images

### Upload Court Image
**POST** `/business/{tenant_id}/courts/{court_id}/images`

**Auth Required:** Yes

**Request Body (multipart/form-data):**
```
image: [file]
is_primary: true
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "id": "img1",
    "url": "https://example.com/images/court1.jpg",
    "is_primary": true
  },
  "message": "Image uploaded successfully"
}
```

---

### Delete Court Image
**DELETE** `/business/{tenant_id}/courts/{court_id}/images/{image_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Image deleted successfully"
}
```

---

## Court Availability

### List Court Availabilities
**GET** `/business/{tenant_id}/courts/{court_id}/availabilities`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "av1",
      "day_of_week_recurring": "monday",
      "specific_date": null,
      "start_time": "08:00:00",
      "end_time": "22:00:00",
      "is_available": true,
      "price_modifier": null,
      "reason": null
    }
  ]
}
```

---

### Create Court Availability
**POST** `/business/{tenant_id}/courts/{court_id}/availabilities`

**Auth Required:** Yes

**Request Body:**
```json
{
  "day_of_week_recurring": "monday",
  "specific_date": "2025-12-25",
  "start_time": "08:00:00",
  "end_time": "22:00:00",
  "is_available": true,
  "price_modifier": 10.50,
  "reason": "Holiday pricing"
}
```
*Note: Provide either `day_of_week_recurring` OR `specific_date`.*

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "id": "av1",
    "day_of_week_recurring": "monday",
    "start_time": "08:00:00",
    "end_time": "22:00:00"
  },
  "message": "Availability created successfully"
}
```

---

### Update Court Availability
**PUT** `/business/{tenant_id}/courts/{court_id}/availabilities/{availability_id}`

**Auth Required:** Yes

**Request Body:**
```json
{
  "start_time": "07:00:00",
  "end_time": "23:00:00",
  "is_available": true
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Availability updated successfully"
}
```

---

### Delete Court Availability
**DELETE** `/business/{tenant_id}/courts/{court_id}/availabilities/{availability_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Availability deleted successfully"
}
```

---

### Get Available Dates
**GET** `/business/{tenant_id}/courts/{court_id}/availability/dates`

**Auth Required:** Yes

**Query Parameters:**
- `start_date` (required): Start date (YYYY-MM-DD)
- `end_date` (required): End date (YYYY-MM-DD)

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    "2025-12-16",
    "2025-12-17",
    "2025-12-18"
  ]
}
```

---

### Get Available Slots
**GET** `/business/{tenant_id}/courts/{court_id}/availability/{date}/slots`

**Auth Required:** Yes

**Path Parameters:**
- `date` (required): Date (YYYY-MM-DD)

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "start_time": "08:00",
      "end_time": "09:00",
      "price": 5000,
      "is_available": true
    },
    {
      "start_time": "09:00",
      "end_time": "10:00",
      "price": 5000,
      "is_available": false
    }
  ]
}
```

---

## Bookings

### List Bookings
**GET** `/business/{tenant_id}/bookings`

**Auth Required:** Yes

**Query Parameters:**
- `status` (optional): Filter by status
- `court_id` (optional): Filter by court
- `date` (optional): Filter by date
- `page` (optional): Page number

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "b1",
        "user": {
          "id": "u1",
          "name": "John Doe",
          "email": "john@example.com"
        },
        "court": {
          "id": "c1",
          "name": "Court 1"
        },
        "start_date": "2025-12-16",
        "start_time": "14:00:00",
        "end_time": "15:00:00",
        "price": 5000,
        "is_pending": false,
        "is_cancelled": false,
        "is_paid": false,
        "present": null
      }
    ],
    "total": 50,
    "per_page": 15
  }
}
```

---

### Create Booking
**POST** `/business/{tenant_id}/bookings`

**Auth Required:** Yes

**Request Body:**
```json
{
  "user_id": "u1",
  "court_id": "c1",
  "start_date": "2025-12-16",
  "start_time": "14:00:00",
  "end_time": "15:00:00"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "id": "b1",
    "court": {
      "name": "Court 1"
    },
    "start_date": "2025-12-16",
    "start_time": "14:00:00",
    "end_time": "15:00:00",
    "price": 5000
  },
  "message": "Booking created successfully"
}
```

---

### Get Booking Details
**GET** `/business/{tenant_id}/bookings/{booking_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "b1",
    "user": {
      "id": "u1",
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+1234567890"
    },
    "court": {
      "id": "c1",
      "name": "Court 1",
      "court_type": {
        "name": "Padel"
      }
    },
    "start_date": "2025-12-16",
    "start_time": "14:00:00",
    "end_time": "15:00:00",
    "price": 5000,
    "currency": {
      "symbol": "$"
    },
    "is_pending": false,
    "is_cancelled": false,
    "is_paid": false,
    "present": null,
    "qr_code": "https://example.com/qr/booking_b1.png"
  }
}
```

---

### Update Booking
**PUT** `/business/{tenant_id}/bookings/{booking_id}`

**Auth Required:** Yes

**Request Body:**
```json
{
  "start_date": "2025-12-17",
  "start_time": "15:00:00",
  "end_time": "16:00:00",
  "is_paid": true
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "b1",
    "start_date": "2025-12-17",
    "is_paid": true
  },
  "message": "Booking updated successfully"
}
```

---

### Confirm Presence
**PUT** `/business/{tenant_id}/bookings/{booking_id}/presence`

**Auth Required:** Yes

**Request Body:**
```json
{
  "present": true
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "b1",
    "present": true
  },
  "message": "Presence confirmed successfully"
}
```

---

### Delete Booking
**DELETE** `/business/{tenant_id}/bookings/{booking_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Booking cancelled successfully"
}
```

---

### Get Booking History
**GET** `/business/{tenant_id}/bookings/history`

**Auth Required:** Yes

**Query Parameters:**
- `present` (optional): Filter by presence (true/false/null)
- `start_date` (optional): Filter from date
- `end_date` (optional): Filter to date
- `per_page` (optional): Items per page (default: 20)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "b1",
        "user": {
          "name": "John Doe"
        },
        "court": {
          "name": "Court 1"
        },
        "start_date": "2025-12-10",
        "start_time": "14:00:00",
        "end_time": "15:00:00",
        "present": true,
        "is_paid": true
      }
    ],
    "total": 100,
    "per_page": 20
  }
}
```

---

### Verify Booking via QR Code
**POST** `/business/{tenant_id}/bookings/verify-qr`

**Auth Required:** Yes

**Request Body (multipart/form-data):**
```
qr_image: [file]
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "booking": {
      "id": "b1",
      "user": {
        "name": "John Doe"
      },
      "court": {
        "name": "Court 1"
      },
      "start_date": "2025-12-16",
      "start_time": "14:00:00",
      "end_time": "15:00:00"
    },
    "verified": true
  },
  "message": "Booking verified successfully"
}
```

---

## Clients

### List Clients
**GET** `/business/{tenant_id}/clients`

**Auth Required:** Yes

**Query Parameters:**
- `search` (optional): Search by name or email
- `page` (optional): Page number

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "u1",
        "name": "John",
        "surname": "Doe",
        "email": "john@example.com",
        "phone": "+1234567890",
        "is_app_user": true,
        "bookings_count": 15
      }
    ],
    "total": 50
  }
}
```

---

### Create Client
**POST** `/business/{tenant_id}/clients`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "John",
  "surname": "Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "password": "password123"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "id": "u1",
    "name": "John",
    "surname": "Doe",
    "email": "john@example.com",
    "is_app_user": false
  },
  "message": "Client created successfully"
}
```

---

### Get Client Details
**GET** `/business/{tenant_id}/clients/{client_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "u1",
    "name": "John",
    "surname": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "is_app_user": true,
    "bookings": [
      {
        "id": "b1",
        "court": {
          "name": "Court 1"
        },
        "start_date": "2025-12-16"
      }
    ]
  }
}
```

---

### Update Client
**PUT** `/business/{tenant_id}/clients/{client_id}`

**Auth Required:** Yes

**Request Body:**
```json
{
  "name": "John",
  "surname": "Doe",
  "phone": "+1234567890"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "u1",
    "name": "John",
    "surname": "Doe"
  },
  "message": "Client updated successfully"
}
```

**Note:** Cannot edit clients where `is_app_user` is `true`.

---

## Financial Reports

### Get Current Month Report
**GET** `/business/{tenant_id}/financials/current`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "year": 2025,
    "month": 12,
    "total_revenue": 150000,
    "total_bookings": 30,
    "paid_bookings": 25,
    "pending_bookings": 5,
    "cancelled_bookings": 2,
    "currency": {
      "code": "USD",
      "symbol": "$"
    },
    "daily_breakdown": [
      {
        "date": "2025-12-01",
        "revenue": 5000,
        "bookings": 1
      }
    ]
  }
}
```

---

### Get Monthly Report
**GET** `/business/{tenant_id}/financials/{year}/{month}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "year": 2025,
    "month": 11,
    "total_revenue": 200000,
    "total_bookings": 40,
    "paid_bookings": 35,
    "pending_bookings": 3,
    "cancelled_bookings": 2,
    "currency": {
      "symbol": "$"
    },
    "daily_breakdown": [
      {
        "date": "2025-11-01",
        "revenue": 5000,
        "bookings": 1
      }
    ]
  }
}
```

---

### Get Monthly Statistics
**GET** `/business/{tenant_id}/financials/{year}/{month}/stats`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "year": 2025,
    "month": 11,
    "total_revenue": 200000,
    "total_bookings": 40,
    "average_booking_value": 5000,
    "paid_percentage": 87.5,
    "cancelled_percentage": 5.0,
    "busiest_day": "2025-11-15",
    "busiest_court": {
      "id": "c1",
      "name": "Court 1",
      "bookings_count": 15
    }
  }
}
```

---

### Get Yearly Statistics
**GET** `/business/{tenant_id}/financials/{year}/stats`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "year": 2025,
    "total_revenue": 2400000,
    "total_bookings": 480,
    "average_monthly_revenue": 200000,
    "paid_percentage": 88.0,
    "monthly_breakdown": [
      {
        "month": 1,
        "revenue": 180000,
        "bookings": 36
      },
      {
        "month": 2,
        "revenue": 200000,
        "bookings": 40
      }
    ]
  }
}
```

---

## Subscriptions

### Get Current Subscription
**GET** `/business/{tenant_id}/subscriptions/current`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "sub1",
    "plan": {
      "id": "sp1",
      "name": "Premium",
      "price": 9900
    },
    "status": "active",
    "current_period_start": "2025-12-01",
    "current_period_end": "2026-01-01",
    "auto_renew": true
  }
}
```

---

### List Invoices
**GET** `/business/{tenant_id}/subscriptions/invoices`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "inv1",
      "amount": 9900,
      "currency": {
        "symbol": "$"
      },
      "status": "paid",
      "invoice_date": "2025-12-01",
      "due_date": "2025-12-15",
      "paid_at": "2025-12-05"
    }
  ]
}
```

---

## Error Responses

All endpoints may return error responses in the following format:

**Validation Error (422):**
```json
{
  "success": false,
  "message": "Dados inválidos",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email has already been taken."]
  }
}
```

**Unauthorized (401):**
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

**Forbidden (403):**
```json
{
  "success": false,
  "message": "You do not have access to this tenant"
}
```

**Not Found (404):**
```json
{
  "success": false,
  "message": "Resource not found"
}
```

**Server Error (500):**
```json
{
  "success": false,
  "message": "Erro ao processar requisição",
  "error": "Error details here"
}
```

---

## Notes

- All IDs are hashed using HashIds for security
- All prices are in cents (divide by 100 for display)
- All timestamps are in UTC
- Bearer token must be included in Authorization header: `Authorization: Bearer {token}`
- Tenant access is validated via middleware
- Subscription status is checked for all tenant-scoped routes
- Cannot edit clients where `is_app_user` is `true` (registered via mobile app)
- Financial reports show data in tenant's currency
