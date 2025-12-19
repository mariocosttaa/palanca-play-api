<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;

/**
 * @tags [API-BUSINESS] Court Availability
 */
class CourtAvailabilityController extends Controller
{
    public function index(Request $request, $tenantId, $courtId)
    {
        try {
            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                return response()->json(['message' => 'Court not found.'], 404);
            }

            return response()->json(['data' => $court->availabilities]);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve availabilities.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve availabilities.'], 500);
        }
    }

    public function store(Request $request, $tenantId, $courtId)
    {
        try {
            $this->beginTransactionSafe();

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Court not found.'], 404);
            }

            $validated = $request->validate([
                'day_of_week_recurring' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'specific_date' => 'nullable|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'is_available' => 'boolean',
                'price_modifier' => 'nullable|numeric',
                'reason' => 'nullable|string',
            ]);

            $availability = $court->availabilities()->create(array_merge($validated, [
                'tenant_id' => $request->tenant->id
            ]));

            $this->commitSafe();

            return response()->json(['data' => $availability], 201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to create availability.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create availability.'], 500);
        }
    }

    public function update(Request $request, $tenantId, $courtId, $availabilityId)
    {
        try {
            $this->beginTransactionSafe();

            $courtId = EasyHashAction::decode($courtId, 'court-id');
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

            $validated = $request->validate([
                'day_of_week_recurring' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'specific_date' => 'nullable|date',
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i|after:start_time',
                'is_available' => 'boolean',
                'price_modifier' => 'nullable|numeric',
                'reason' => 'nullable|string',
            ]);

            $availability->update($validated);

            $this->commitSafe();

            return response()->json(['data' => $availability]);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to update availability.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update availability.'], 500);
        }
    }

    public function destroy(Request $request, $tenantId, $courtId, $availabilityId)
    {
        try {
            $this->beginTransactionSafe();

            $courtId = EasyHashAction::decode($courtId, 'court-id');
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
            \Log::error('Failed to delete availability.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete availability.'], 500);
        }
    }

    public function getDates(Request $request, $tenantId, $courtId)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                return response()->json(['message' => 'Court not found.'], 404);
            }

            $dates = $court->getAvailableDates($request->start_date, $request->end_date);

            return response()->json(['data' => $dates]);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve available dates.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve available dates.'], 500);
        }
    }

    public function getSlots(Request $request, $tenantId, $courtId, $date)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make(['date' => $date], [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Invalid date format.', 'errors' => $validator->errors()->first()], 422);
            }

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                return response()->json(['message' => 'Court not found.'], 404);
            }

            $slots = $court->getAvailableSlots($date);

            return response()->json(['data' => $slots]);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve available slots.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve available slots.'], 500);
        }
    }
}
