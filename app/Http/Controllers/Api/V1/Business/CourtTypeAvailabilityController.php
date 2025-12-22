<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\CourtType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * @tags [API-BUSINESS] Court Type Availability
 */
class CourtTypeAvailabilityController extends Controller
{
    /**
     * Get availabilities for a court type
     * 
     * Returns the availability rules defined for a specific court type.
     */
    public function index(Request $request, $tenantId, $courtTypeId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($request->tenant->id)->find($courtTypeId);

            if (!$courtType) {
                abort(404, 'Court Type not found.');
            }

            return \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral::collection($courtType->availabilities);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve availabilities.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve availabilities.');
        }
    }

    /**
     * Create a new availability for a court type
     * 
     * Adds a new availability rule for a court type.
     */
    public function store(Request $request, $tenantId, $courtTypeId): \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($request->tenant->id)->find($courtTypeId);

            if (!$courtType) {
                $this->rollBackSafe();
                abort(404, 'Court Type not found.');
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
            ]);

            $availability = $courtType->availabilities()->create(array_merge($validated, [
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
     * Update an existing availability for a court type
     * 
     * Modifies an existing availability rule for a court type.
     */
    public function update(Request $request, $tenantId, $courtTypeId, $availabilityId): \App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral
    {
        try {
            $this->beginTransactionSafe();

            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($request->tenant->id)->find($courtTypeId);

            if (!$courtType) {
                $this->rollBackSafe();
                abort(404, 'Court Type not found.');
            }

            $availability = $courtType->availabilities()->find($availabilityId);

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
     * Delete an availability for a court type
     * 
     * Removes an availability rule from a court type.
     */
    public function destroy(Request $request, $tenantId, $courtTypeId, $availabilityId): JsonResponse
    {
        try {
            $this->beginTransactionSafe();

            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($request->tenant->id)->find($courtTypeId);

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Court Type not found.'], 404);
            }

            $availability = $courtType->availabilities()->find($availabilityId);

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
}
