<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;

class MobileCourtAvailabilityController extends Controller
{
    /**
     * Get available dates for a court within a date range
     */
    public function getDates(Request $request, string $tenantIdHashId, string $courtIdHashId)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            
            $court = Court::forTenant($tenantId)
                ->active()
                ->findOrFail($courtId);

            $dates = $court->getAvailableDates($request->start_date, $request->end_date);

            return $this->dataResponse([
                'dates' => $dates,
                'count' => count($dates),
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar datas disponíveis', $e->getMessage(), 500);
        }
    }

    /**
     * Get available time slots for a specific date
     * Respects existing bookings and buffer times
     */
    public function getSlots(Request $request, string $tenantIdHashId, string $courtIdHashId, string $date)
    {
        try {
            // Validate date format
            $validator = \Illuminate\Support\Facades\Validator::make(['date' => $date], [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), status: 422);
            }

            $tenantId = EasyHashAction::decode($tenantIdHashId, 'tenant-id');
            $courtId = EasyHashAction::decode($courtIdHashId, 'court-id');
            
            $court = Court::forTenant($tenantId)
                ->active()
                ->with('tenant')
                ->findOrFail($courtId);

            $slots = $court->getAvailableSlots($date);

            return $this->dataResponse([
                'date' => $date,
                'slots' => $slots,
                'count' => $slots->count(),
                'interval_minutes' => $court->tenant->booking_interval_minutes ?? 60,
                'buffer_minutes' => $court->tenant->buffer_between_bookings_minutes ?? 0,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao buscar horários disponíveis', $e->getMessage(), 500);
        }
    }
}
