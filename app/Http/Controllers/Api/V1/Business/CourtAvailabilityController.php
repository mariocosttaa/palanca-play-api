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
                return $this->errorResponse('Court not found.', null, 404);
            }

            return $this->dataResponse($court->availabilities);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve availabilities.', $e->getMessage(), 500);
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
                return $this->errorResponse('Court not found.', null, 404);
            }

            $validated = $request->validate([
                'day_of_week_recurring' => 'nullable|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
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

            return $this->dataResponse($availability, 201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to create availability.', $e->getMessage(), 500);
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
                return $this->errorResponse('Court not found.', null, 404);
            }

            $availability = $court->availabilities()->find($availabilityId);

            if (!$availability) {
                $this->rollBackSafe();
                return $this->errorResponse('Availability not found.', null, 404);
            }

            $validated = $request->validate([
                'day_of_week_recurring' => 'nullable|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'specific_date' => 'nullable|date',
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i|after:start_time',
                'is_available' => 'boolean',
                'price_modifier' => 'nullable|numeric',
                'reason' => 'nullable|string',
            ]);

            $availability->update($validated);

            $this->commitSafe();

            return $this->dataResponse($availability);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to update availability.', $e->getMessage(), 500);
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
                return $this->errorResponse('Court not found.', null, 404);
            }

            $availability = $court->availabilities()->find($availabilityId);

            if (!$availability) {
                $this->rollBackSafe();
                return $this->errorResponse('Availability not found.', null, 404);
            }

            $availability->delete();

            $this->commitSafe();

            return $this->successResponse('Availability removed successfully.');

        } catch (\Exception $e) {
            $this->rollBackSafe();
            return $this->errorResponse('Failed to delete availability.', $e->getMessage(), 500);
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
                return $this->errorResponse('Court not found.', null, 404);
            }

            $dates = $court->getAvailableDates($request->start_date, $request->end_date);

            return $this->dataResponse($dates);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve available dates.', $e->getMessage(), 500);
        }
    }

    public function getSlots(Request $request, $tenantId, $courtId, $date)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make(['date' => $date], [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Invalid date format.', $validator->errors()->first(), 422);
            }

            $courtId = EasyHashAction::decode($courtId, 'court-id');
            $court = Court::forTenant($request->tenant->id)->find($courtId);

            if (!$court) {
                return $this->errorResponse('Court not found.', null, 404);
            }

            $slots = $court->getAvailableSlots($date);

            return $this->dataResponse($slots);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve available slots.', $e->getMessage(), 500);
        }
    }
}
