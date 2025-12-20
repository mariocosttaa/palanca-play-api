<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\CourtType;
use Illuminate\Http\Request;

/**
 * @tags [API-BUSINESS] Court Type Availability
 */
class CourtTypeAvailabilityController extends Controller
{
    public function index(Request $request, $tenantId, $courtTypeId)
    {
        try {
            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($request->tenant->id)->find($courtTypeId);

            if (!$courtType) {
                return response()->json(['message' => 'Court Type not found.'], 404);
            }

            return response()->json(['data' => $courtType->availabilities]);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve availabilities.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve availabilities.'], 500);
        }
    }

    public function store(Request $request, $tenantId, $courtTypeId)
    {
        try {
            $this->beginTransactionSafe();

            $courtTypeId = EasyHashAction::decode($courtTypeId, 'court-type-id');
            $courtType = CourtType::forTenant($request->tenant->id)->find($courtTypeId);

            if (!$courtType) {
                $this->rollBackSafe();
                return response()->json(['message' => 'Court Type not found.'], 404);
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

            return response()->json(['data' => $availability], 201);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to create availability.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create availability.'], 500);
        }
    }

    public function update(Request $request, $tenantId, $courtTypeId, $availabilityId)
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

            return response()->json(['data' => $availability]);

        } catch (\Exception $e) {
            $this->rollBackSafe();
            \Log::error('Failed to update availability.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update availability.'], 500);
        }
    }

    public function destroy(Request $request, $tenantId, $courtTypeId, $availabilityId)
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
            \Log::error('Failed to delete availability.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete availability.'], 500);
        }
    }
}
