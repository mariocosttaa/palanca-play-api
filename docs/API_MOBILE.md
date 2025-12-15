# Mobile API Documentation

**Base URL:** `/api/mobile/v1`

**Authentication:** Bearer Token (Sanctum)

---

## Authentication

### Register User
**POST** `/users/register`

**Public:** Yes

**Request Body:**
```json
{
  "name": "John",
  "surname": "Doe",
  "email": "john@example.com",
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
      "id": "abc123",
      "name": "John",
      "surname": "Doe",
      "email": "john@example.com",
      "phone": "+1234567890"
    },
    "token": "1|laravel_sanctum_token_here"
  },
  "message": "User registered successfully"
}
```

---

### Login User
**POST** `/users/login`

**Public:** Yes

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "abc123",
      "name": "John",
      "surname": "Doe",
      "email": "john@example.com"
    },
    "token": "2|laravel_sanctum_token_here"
  },
  "message": "Login successful"
}
```

---

### Logout User
**POST** `/users/logout`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### Get Current User
**GET** `/users/me`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "abc123",
    "name": "John",
    "surname": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "country": {
      "id": "1",
      "name": "United States"
    }
  }
}
```

---

## Password Reset

### Request Password Reset Code
**POST** `/password/forgot`

**Public:** Yes

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Código de recuperação enviado para seu email"
}
```

---

### Verify Code and Reset Password
**POST** `/password/verify`

**Public:** Yes

**Request Body:**
```json
{
  "email": "john@example.com",
  "code": "123456",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Senha redefinida com sucesso"
}
```

---

### Check Code Validity
**GET** `/password/verify/{code}`

**Public:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "email": "john@example.com"
  }
}
```

---

## Court Types (Public)

### List Court Types
**GET** `/tenants/{tenant_id}/court-types`

**Public:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "ct1",
      "name": "Padel",
      "description": "Professional padel courts"
    },
    {
      "id": "ct2",
      "name": "Tennis",
      "description": "Standard tennis courts"
    }
  ]
}
```

---

### Get Court Type Details
**GET** `/tenants/{tenant_id}/court-types/{court_type_id}`

**Public:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "ct1",
    "name": "Padel",
    "description": "Professional padel courts",
    "courts_count": 4
  }
}
```

---

## Courts (Public)

### List Courts
**GET** `/tenants/{tenant_id}/courts`

**Public:** Yes

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
      "primary_image": {
        "id": "img1",
        "url": "https://example.com/images/court1.jpg"
      },
      "price_per_hour": 5000,
      "currency": {
        "id": "1",
        "code": "USD",
        "symbol": "$"
      }
    }
  ]
}
```

---

### Get Court Details
**GET** `/tenants/{tenant_id}/courts/{court_id}`

**Public:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "c1",
    "name": "Court 1",
    "description": "Professional padel court with LED lighting",
    "court_type": {
      "id": "ct1",
      "name": "Padel"
    },
    "images": [
      {
        "id": "img1",
        "url": "https://example.com/images/court1.jpg",
        "is_primary": true
      }
    ],
    "price_per_hour": 5000,
    "currency": {
      "id": "1",
      "code": "USD",
      "symbol": "$"
    },
    "opening_time": "08:00:00",
    "closing_time": "22:00:00"
  }
}
```

---

## Court Availability (Public)

### Get Available Dates
**GET** `/tenants/{tenant_id}/courts/{court_id}/availability/dates`

**Public:** Yes

**Query Parameters:**
- `start_date` (optional): Start date (Y-m-d)
- `end_date` (optional): End date (Y-m-d)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "available_dates": [
      "2025-12-16",
      "2025-12-17",
      "2025-12-18"
    ]
  }
}
```

---

### Get Available Time Slots
**GET** `/tenants/{tenant_id}/courts/{court_id}/availability/{date}/slots`

**Public:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "date": "2025-12-16",
    "slots": [
      {
        "start_time": "08:00:00",
        "end_time": "09:00:00",
        "available": true,
        "price": 5000
      },
      {
        "start_time": "09:00:00",
        "end_time": "10:00:00",
        "available": false,
        "price": 5000
      }
    ]
  }
}
```

---

## Notifications

### Get Recent Notifications
**GET** `/notifications/recent`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "n1",
      "subject": "Reserva Confirmada",
      "message": "Você criou uma reserva no Campo 1 no dia 16/12/2025 das 14:00 às 15:00.",
      "read_at": null,
      "created_at": "2025-12-15T15:30:00.000000Z"
    }
  ]
}
```

---

### Get All Notifications
**GET** `/notifications`

**Auth Required:** Yes

**Query Parameters:**
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 15)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "n1",
        "subject": "Reserva Confirmada",
        "message": "Você criou uma reserva no Campo 1...",
        "read_at": null,
        "created_at": "2025-12-15T15:30:00.000000Z"
      }
    ],
    "total": 25,
    "per_page": 15,
    "last_page": 2
  }
}
```

---

### Mark Notification as Read
**PATCH** `/notifications/{notification_id}/read`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notificação marcada como lida"
}
```

---

## Bookings

### List User Bookings
**GET** `/bookings`

**Auth Required:** Yes

**Query Parameters:**
- `status` (optional): Filter by status (pending, confirmed, cancelled)
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
        "court": {
          "id": "c1",
          "name": "Court 1",
          "court_type": {
            "id": "ct1",
            "name": "Padel"
          }
        },
        "start_date": "2025-12-16",
        "start_time": "14:00:00",
        "end_time": "15:00:00",
        "price": 5000,
        "currency": {
          "code": "USD",
          "symbol": "$"
        },
        "is_pending": false,
        "is_cancelled": false,
        "is_paid": false,
        "qr_code": "https://example.com/qr/booking_b1.png"
      }
    ],
    "total": 10
  }
}
```

---

### Create Booking
**POST** `/bookings`

**Auth Required:** Yes

**Request Body:**
```json
{
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
      "id": "c1",
      "name": "Court 1"
    },
    "start_date": "2025-12-16",
    "start_time": "14:00:00",
    "end_time": "15:00:00",
    "price": 5000,
    "currency": {
      "symbol": "$"
    },
    "qr_code": "https://example.com/qr/booking_b1.png"
  },
  "message": "Booking created successfully"
}
```

**Note:** Creates notification and sends confirmation email automatically.

---

### Get Booking Details
**GET** `/bookings/{booking_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "b1",
    "court": {
      "id": "c1",
      "name": "Court 1",
      "court_type": {
        "name": "Padel"
      },
      "primary_image": {
        "url": "https://example.com/images/court1.jpg"
      }
    },
    "start_date": "2025-12-16",
    "start_time": "14:00:00",
    "end_time": "15:00:00",
    "price": 5000,
    "currency": {
      "code": "USD",
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
**PUT** `/bookings/{booking_id}`

**Auth Required:** Yes

**Request Body:**
```json
{
  "start_date": "2025-12-17",
  "start_time": "15:00:00",
  "end_time": "16:00:00"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "b1",
    "start_date": "2025-12-17",
    "start_time": "15:00:00",
    "end_time": "16:00:00"
  },
  "message": "Booking updated successfully"
}
```

**Note:** Sends update notification and email (when implemented).

---

### Cancel Booking
**DELETE** `/bookings/{booking_id}`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "message": "Agendamento cancelado com sucesso"
}
```

**Note:** Creates cancellation notification and sends cancellation email automatically.

---

### Get Booking Statistics
**GET** `/bookings/stats`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "total_bookings": 25,
    "upcoming_bookings": 5,
    "completed_bookings": 18,
    "cancelled_bookings": 2
  }
}
```

---

### Get Recent Bookings
**GET** `/bookings/recent`

**Auth Required:** Yes

**Query Parameters:**
- `limit` (optional): Number of bookings (default: 5)

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "b1",
      "court": {
        "name": "Court 1"
      },
      "start_date": "2025-12-16",
      "start_time": "14:00:00"
    }
  ]
}
```

---

### Get Next Booking
**GET** `/bookings/next`

**Auth Required:** Yes

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "b1",
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
    "qr_code": "https://example.com/qr/booking_b1.png"
  }
}
```

---

## Error Responses

All endpoints may return error responses in the following format:

**Validation Error (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
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
  "message": "Internal server error",
  "error": "Error details here"
}
```

---

## Notes

- All IDs are hashed using HashIds for security
- All prices are in cents (divide by 100 for display)
- All timestamps are in UTC
- Bearer token must be included in Authorization header: `Authorization: Bearer {token}`
- Notifications and emails are sent automatically for booking events
- Emails are queued for async processing
