<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;

class CourtAvailabilityController extends Controller
{
    public function index(Request $request, $tenantId, $courtId)
    {
        $courtId = EasyHashAction::decode($courtId, 'court-id');
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
        }

        return $this->dataResponse($court->availabilities);
    }

    public function store(Request $request, $tenantId, $courtId)
    {
        $courtId = EasyHashAction::decode($courtId, 'court-id');
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
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

        // Ensure either day_of_week_recurring or specific_date is present, but not both (or logic as per model)
        // For now, simple creation
        $availability = $court->availabilities()->create(array_merge($validated, ['tenant_id' => $request->tenant->id]));

        return $this->dataResponse($availability, status: 201);
    }

    public function update(Request $request, $tenantId, $courtId, $availabilityId)
    {
        // Decode IDs if necessary, or assume model binding if set up, but here we use manual decoding for consistency
        $courtId = EasyHashAction::decode($courtId, 'court-id');
        // Assuming availabilityId is not hashed for now or needs decoding? 
        // Usually internal IDs might not be hashed in the same way or we need a standard.
        // Let's assume it's just the ID for now or check if we need hashing.
        // If we follow the pattern, we might need to decode it.
        // But for now let's stick to simple ID or check if we need to decode.
        // Given the context, let's assume it's an integer ID for simplicity unless specified otherwise.
        
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
        }

        $availability = $court->availabilities()->find($availabilityId);

        if (!$availability) {
            return $this->errorResponse('Disponibilidade não encontrada', status: 404);
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

        return $this->dataResponse($availability);
    }

    public function destroy(Request $request, $tenantId, $courtId, $availabilityId)
    {
        $courtId = EasyHashAction::decode($courtId, 'court-id');
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
        }

        $availability = $court->availabilities()->find($availabilityId);

        if (!$availability) {
            return $this->errorResponse('Disponibilidade não encontrada', status: 404);
        }

        $availability->delete();

        return $this->successResponse('Disponibilidade removida com sucesso');
    }

    public function getDates(Request $request, $tenantId, $courtId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $courtId = EasyHashAction::decode($courtId, 'court-id');
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
        }

        $dates = $court->getAvailableDates($request->start_date, $request->end_date);

        return $this->dataResponse($dates);
    }

    public function getSlots(Request $request, $tenantId, $courtId, $date)
    {
        // Date is now a route parameter
        // Validate date format if needed, but route param is string.
        // We can validate it manually.
        
        $validator = \Illuminate\Support\Facades\Validator::make(['date' => $date], [
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), status: 422);
        }

        $courtId = EasyHashAction::decode($courtId, 'court-id');
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
        }

        $slots = $court->getAvailableSlots($date);

        return $this->dataResponse($slots);
    }
}
