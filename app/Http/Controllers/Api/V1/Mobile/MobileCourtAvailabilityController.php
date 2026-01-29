<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;

/**
 * @tags [API-MOBILE] Court Availability
 */
class MobileCourtAvailabilityController extends Controller
{
    /**
     * Get available dates
     * 
     * Get available dates for a court within a date range.
     * Maximum date range is 90 days.
     * 
     * @urlParam court_id string required The HashID of the court. Example: crt_abc123
     * @queryParam start_date string required The start date (Y-m-d). Example: 2025-12-01
     * @queryParam end_date string required The end date (Y-m-d). Example: 2025-12-31
     * 
     * @response array{data: array{dates: array<int, string>, count: int}}
     */
    public function getDates(Request $request, string $courtIdHashId): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            // Validate date range doesn't exceed 90 days
            $startDate = \Carbon\Carbon::parse($validated['start_date']);
            $endDate = \Carbon\Carbon::parse($validated['end_date']);
            
            if ($startDate->diffInDays($endDate) > 90) {
                abort(422, 'O intervalo de datas não pode exceder 90 dias.');
            }

            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            
            $court = Court::active()
                ->findOrFail($courtId);

            $dates = $court->getAvailableDates($validated['start_date'], $validated['end_date']);

            return response()->json([
                'data' => [
                    'dates' => $dates,
                    'count' => count($dates),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Quadra não encontrada.');
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar datas disponíveis', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar datas disponíveis');
        }
    }

    /**
     * Get available slots
     * 
     * Get available time slots for a specific date.
     * Respects existing bookings and buffer times.
     * 
     * @urlParam court_id string required The HashID of the court. Example: crt_abc123
     * @urlParam date string required The date (Y-m-d). Example: 2025-12-25
     * 
     * @response array{data: array{date: string, slots: array<int, string>, count: int, interval_minutes: int, buffer_minutes: int}}
     */
    public function getSlots(Request $request, string $courtIdHashId, string $date): \Illuminate\Http\JsonResponse
    {
        try {
            // Validate date format
            $validator = \Illuminate\Support\Facades\Validator::make(['date' => $date], [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                abort(422, $validator->errors()->first());
            }

            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            
            $court = Court::active()
                ->with('tenant')
                ->findOrFail($courtId);

            // Pass the authenticated user ID to allow them to see slots that would 
            // otherwise be blocked by their own booking buffers.
            $userId = $request->user()?->id;
            $slots = $court->getAvailableSlots($date, null, $userId);

            return response()->json([
                'data' => [
                    'date' => $date,
                    'slots' => $slots,
                    'count' => $slots->count(),
                    'interval_minutes' => $court->courtType->interval_time_minutes ?? $court->tenant->booking_interval_minutes ?? 60,
                    'buffer_minutes' => $court->courtType->buffer_time_minutes ?? 0,
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Quadra não encontrada.');
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar horários disponíveis', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar horários disponíveis');
        }
    }
}
