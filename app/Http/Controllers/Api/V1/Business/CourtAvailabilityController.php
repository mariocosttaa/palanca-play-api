<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral;
use App\Models\Court;
use Illuminate\Http\Request;

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
     * 
     * @return \Illuminate\Http\JsonResponse
     * @responseExample 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": "av1",
     *       "tenant_id": "t1",
     *       "court_id": "c1",
     *       "court_type_id": null,
     *       "day_of_week_recurring": "Monday",
     *       "specific_date": null,
     *       "start_time": "09:00",
     *       "end_time": "22:00",
     *       "is_available": true
     *     },
     *     {
     *       "id": "av2",
     *       "tenant_id": "t1",
     *       "court_id": "c1",
     *       "court_type_id": null,
     *       "day_of_week_recurring": "Tuesday",
     *       "specific_date": null,
     *       "start_time": "09:00",
     *       "end_time": "22:00",
     *       "is_available": false
     *     }
     *   ]
     * }
     */
    public function index(Request $request, $tenantId, $courtId)
    {
        try {
            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                return response()->json(['message' => 'Court not found.'], 404);
            }

            // Get effective availabilities (court-specific or fallback to court type)
            $availabilities = $court->getEffectiveAvailabilities();

            return response()->json([
                'success' => true,
                'data' => CourtAvailabilityResourceGeneral::collection($availabilities)
            ]);

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
