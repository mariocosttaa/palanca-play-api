<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\CourtSlotsResource;
use App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @tags [API-BUSINESS] Court Availability
 */
class CourtAvailabilityController extends Controller
{
    /**
     * Get availabilities for a court
     * 
     * Returns court-specific availabilities if they exist,
     * otherwise returns court type availabilities,
     * otherwise returns empty array.
     */
    public function index(Request $request, $tenantId, $courtId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                abort(404, 'Court not found.');
            }

            // Get effective availabilities (court-specific or fallback to court type)
            $availabilities = $court->getEffectiveAvailabilities();

            return \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral::collection($availabilities);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve availabilities.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve availabilities.');
        }
    }

    /**
     * Create a new availability for a court
     * 
     * Adds a new availability slot or rule for a specific court.
     */
    public function store(Request $request, $tenantId, $courtId): \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                $this->rollBackSafe();
                abort(404, 'Court not found.');
            }

            $data = $request->all();
            if (isset($data['is_available']) && $data['is_available'] === false) {
                $data['start_time'] = $data['start_time'] ?? '09:00';
                $data['end_time'] = $data['end_time'] ?? '19:00';
                $request->merge($data);
            }

            $validated = $request->validate([
                'day_of_week_recurring' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'specific_date' => 'nullable|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'is_available' => 'boolean',
                'price_modifier' => 'nullable|numeric',
                'reason' => 'nullable|string',
            ]);

            $availability = $court->availabilities()->create(array_merge($validated, [
                'tenant_id' => $request->tenant->id
            ]));

            $this->commitSafe();

            return new \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral($availability);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Failed to create availability.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to create availability.');
        }
    }

    /**
     * Update an existing availability
     * 
     * Modifies an existing availability rule for a court.
     */
    public function update(Request $request, $tenantId, $courtId, $availabilityId): \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $availabilityId = EasyHashAction::decode($availabilityId, 'court-availability-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                $this->rollBackSafe();
                abort(404, 'Court not found.');
            }

            $availability = $court->availabilities()->find($availabilityId);

            if (!$availability) {
                $this->rollBackSafe();
                abort(404, 'Availability not found.');
            }

            $data = $request->all();
            if (isset($data['is_available']) && $data['is_available'] === false) {
                 // Only set defaults if they are not provided or null
                if (!isset($data['start_time'])) $data['start_time'] = '09:00';
                if (!isset($data['end_time'])) $data['end_time'] = '19:00';
                $request->merge($data);
            }

            $validated = $request->validate([
                'day_of_week_recurring' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'specific_date' => 'nullable|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'is_available' => 'boolean',
                'price_modifier' => 'nullable|numeric',
                'reason' => 'nullable|string',
            ]);

            $availability->update($validated);

            $this->commitSafe();

            return new \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral($availability);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Failed to update availability.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to update availability.');
        }
    }

    /**
     * Delete an availability
     * 
     * Removes an availability rule from a court.
     */
    public function destroy(Request $request, $tenantId, $courtId, $availabilityId): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $availabilityId = EasyHashAction::decode($availabilityId, 'court-availability-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Court not found.'], 404);
            }

            $availability = $court->availabilities()->find($availabilityId);

            if (!$availability) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Availability not found.'], 404);
            }

            $availability->delete();

            $this->commitSafe();

            return response()->json(['message' => 'Availability removed successfully.']);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            Log::error('Failed to delete availability.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete availability.'], 500);
        }
    }

    /**
     * Get available dates for a court
     * 
     * Returns a list of dates (Y-m-d format) that have available slots for the specified month.
     * If month and year are not provided, defaults to the current month and year.
     * 
     * When updating a booking, pass the booking_id to exclude that booking from availability checks,
     * allowing the booking's current date to appear as available.
     * 
     * @queryParam month integer optional Month (1-12). Defaults to current month. Example: 12
     * @queryParam year integer optional Year (YYYY). Defaults to current year. Example: 2025
     * @queryParam booking_id string optional Booking ID to exclude from availability checks (for updates). Example: mGbnVK9ryOK1y4Y6XlQgJ
     * 
     */
    public function getDates(Request $request, $tenantId, $courtId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100',
                'booking_id' => 'nullable|string',
            ]);

            // Default to current month and year if not provided
            $month = $validated['month'] ?? now()->month;
            $year = $validated['year'] ?? now()->year;

            // Calculate start and end dates for the month
            try {
                $requestedDate = \Carbon\Carbon::create($year, $month, 1);
                $startDate = $requestedDate->copy()->startOfMonth();
                $endDate = $requestedDate->copy()->endOfMonth();

                // Ensure we don't return past dates
                $today = now()->startOfDay();

                if ($endDate->lt($today)) {
                    return response()->json(['data' => []]);
                }

                if ($startDate->lt($today)) {
                    $startDate = $today;
                }

                $startDate = $startDate->format('Y-m-d');
                $endDate = $endDate->format('Y-m-d');
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid month or year provided.'], 422);
            }

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                return response()->json(['message' => 'Court not found.'], 404);
            }

            $excludeBookingId = null;
            if (isset($validated['booking_id'])) {
                $excludeBookingId = EasyHashAction::decode($validated['booking_id'], 'booking-id');
            }

            $dates = $court->getAvailableDates($startDate, $endDate, $excludeBookingId);

            return response()->json(['data' => $dates]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve available dates.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve available dates.'], 500);
        }
    }

    /**
     * Get available slots for a court on a specific date
     * 
     * Returns a list of available time slots for a court on a given date.
     * Each slot includes a start and end time in H:i format.
     * 
     * When updating a booking, pass the booking_id to exclude that booking from availability checks,
     * allowing the booking's current time slot to appear as available.
     * 
     * @urlParam date string required Date in Y-m-d format. Example: 2025-12-22
     * @queryParam booking_id string optional Booking ID to exclude from availability checks (for updates). Example: mGbnVK9ryOK1y4Y6XlQgJ
     */
    public function getSlots(Request $request, $tenantId, $courtId, $date): CourtSlotsResource
    {
        try {
            // Validate date from URL parameter
            $dateValidator = Validator::make(['date' => $date], [
                'date' => 'required|date',
            ]);

            if ($dateValidator->fails()) {
                abort(422, 'Invalid date format.');
            }

            // Validate query parameters
            $validated = $request->validate([
                'booking_id' => 'nullable|string',
            ]);

            // Decode and find court
            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                abort(404, 'Court not found.');
            }

            // Decode booking ID if provided
            $excludeBookingId = isset($validated['booking_id'])
                ? EasyHashAction::decode($validated['booking_id'], 'booking-id')
                : null;

            // Get available slots
            $slots = $court->getAvailableSlots($date, $excludeBookingId);

            return new CourtSlotsResource($slots);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve available slots.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve available slots.');
        }
    }
}
