# Frontend Guide: Booking Availability Updates

## Overview

The booking system now properly validates slot contiguity and handles availability checks during updates. This guide explains how to integrate with these changes.

---

## Key Changes

### 1. Booking ID Parameter for Availability Endpoints

When **editing/updating** an existing booking, pass the `booking_id` parameter to availability endpoints so the current booking is excluded from availability checks.

### 2. Slot Contiguity Validation

Slots **must be contiguous** (no gaps between them). Non-contiguous slots will be rejected with a validation error.

### 3. Sequential Booking Support

Users can book sequential time slots without buffer time gaps (e.g., 10:00-11:00, then 11:00-12:00).

---

## API Endpoints

### Mobile API

#### Get Available Dates
```
GET /api/v1/courts/{court_id}/availability/{date}/slots?booking_id={booking_id}
```

**Query Parameters:**
- `booking_id` (optional): Hash ID of the booking being edited

**Example:**
```typescript
// When editing a booking
const response = await api.get(
  `/api/v1/courts/${courtId}/availability/${date}/slots`,
  {
    params: {
      booking_id: bookingId  // Include when editing
    }
  }
);
```

#### Get Available Slots
```
GET /api/v1/courts/{court_id}/availability/dates?start_date={date}&end_date={date}&booking_id={booking_id}
```

### Business API

#### Get Available Dates
```
GET /api/business/v1/business/{tenant_id}/courts/{court_id}/availability/dates?month={month}&year={year}&booking_id={booking_id}
```

**Query Parameters:**
- `month` (optional): Month (1-12)
- `year` (optional): Year (YYYY)
- `booking_id` (optional): Hash ID of the booking being edited

#### Get Available Slots
```
GET /api/business/v1/business/{tenant_id}/courts/{court_id}/availability/{date}/slots?booking_id={booking_id}
```

**Example:**
```typescript
// When editing a booking
const response = await api.get(
  `/api/business/v1/business/${tenantId}/courts/${courtId}/availability/${date}/slots`,
  {
    params: {
      booking_id: bookingId  // Include when editing
    }
  }
);
```

---

## Creating/Updating Bookings

### Slot Format

Slots must be an array of contiguous time periods:

```typescript
interface Slot {
  start: string;  // Format: "HH:mm" (e.g., "14:00")
  end: string;    // Format: "HH:mm" (e.g., "15:00")
}
```

> [!IMPORTANT]
> **Price Calculation**: The backend **always** calculates the booking price from the slots. The price is computed as:
> ```
> price = court_type.price_per_interval × number_of_slots
> ```
> **Do not send the `price` field** in your requests - it will be ignored and recalculated on the server.

### ✅ Valid Examples

```typescript
// Single slot
{
  slots: [
    { start: "14:00", end: "15:00" }
  ]
}

// Multiple contiguous slots
{
  slots: [
    { start: "14:00", end: "15:00" },
    { start: "15:00", end: "16:00" },
    { start: "16:00", end: "17:00" }
  ]
}
```

### ❌ Invalid Examples

```typescript
// Non-contiguous slots (GAP between 15:00 and 19:10)
{
  slots: [
    { start: "14:00", end: "15:00" },
    { start: "19:10", end: "20:10" }  // ❌ Gap detected
  ]
}

// Wrong order
{
  slots: [
    { start: "15:00", end: "16:00" },
    { start: "14:00", end: "15:00" }  // ❌ Not sequential
  ]
}
```

---

## Error Handling

### Contiguity Error (422)

```json
{
  "message": "Os horários devem ser contíguos (sem intervalos entre eles)",
  "errors": {
    "slots": [
      "Os horários devem ser contíguos (sem intervalos entre eles)"
    ]
  }
}
```

**Frontend Action:**
- Display error message to user
- Highlight the non-contiguous slots
- Suggest selecting consecutive time slots

### Availability Error (422)

```json
{
  "message": "Este horário já está reservado (14:00 - 16:00). (Incluindo intervalo de manutenção de 10 min).",
  "errors": {
    "slots.0": [
      "Este horário já está reservado (14:00 - 16:00). (Incluindo intervalo de manutenção de 10 min)."
    ]
  }
}
```

**Frontend Action:**
- Display the conflict message
- Show which specific slot has the conflict
- Refresh available slots

---

## Implementation Checklist

### Creating a New Booking

- [ ] Fetch available slots: `GET /courts/{court_id}/availability/{date}/slots`
- [ ] Allow user to select **contiguous** time slots
- [ ] Validate slots are contiguous on frontend (for better UX)
- [ ] Send slots in request body
- [ ] Handle validation errors (422)

### Updating an Existing Booking

- [ ] Pass `booking_id` when fetching availability
- [ ] Fetch available dates: `GET .../availability/dates?booking_id={id}`
- [ ] Fetch available slots: `GET .../availability/{date}/slots?booking_id={id}`
- [ ] Allow user to select **contiguous** time slots
- [ ] Validate slots are contiguous on frontend
- [ ] Send slots in request body
- [ ] Handle validation errors (422)

---

## Best Practices

### 1. Always Pass booking_id When Editing

```typescript
// ❌ BAD - Will show current slot as unavailable
const slots = await fetchSlots(courtId, date);

// ✅ GOOD - Excludes current booking from availability
const slots = await fetchSlots(courtId, date, bookingId);
```

### 2. Validate Contiguity on Frontend (Optional but Recommended)

```typescript
function validateSlots(slots: Slot[]): boolean {
  for (let i = 0; i < slots.length - 1; i++) {
    if (slots[i].end !== slots[i + 1].start) {
      return false; // Gap detected
    }
  }
  return true;
}
```

### 3. Sort Slots Before Sending

Ensure slots are in chronological order:

```typescript
const sortedSlots = selectedSlots.sort((a, b) => 
  a.start.localeCompare(b.start)
);
```

### 4. Show Buffer Time Information

If a user tries to book immediately after another user's booking, show why it's blocked:

```
❌ 11:00 - 12:00 unavailable
   (Reserved 10:00-11:00 + 10min buffer)
```

---

## Testing Scenarios

### Scenario 1: Update Booking to Same Time
- Open edit dialog for existing booking
- See current time slot as **available**
- Save without changes → Success

### Scenario 2: Update Booking to Different Time
- Open edit dialog for existing booking
- See current time slot as **available**
- Select different available slot
- Save → Success

### Scenario 3: Non-Contiguous Slots
- Try to select slots with gaps (e.g., 10:00-11:00 and 14:00-15:00)
- Get validation error: "Os horários devem ser contíguos"

### Scenario 4: Sequential Bookings (Same User)
- User has booking at 10:00-11:00
- User can immediately book 11:00-12:00 (no buffer)
- Different user sees 11:10-12:10 as first available (with buffer)

---

## Questions?

If you encounter issues:
1. Check that `booking_id` is included in availability requests when editing
2. Verify slots are contiguous
3. Check error response for specific validation messages
4. Review the test files for examples:
   - `tests/Feature/Api/V1/Mobile/UpdateBookingAvailabilityTest.php`
   - `tests/Feature/Api/Business/BookingSlotsTest.php`
