<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Booking History
 */
class BookingHistoryController extends Controller
{
    /**
     * Get past bookings with pagination
     * Filter by date range and presence status
     *
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int optional Items per page. Example: 20
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Business\V1\Specific\BookingResource>
     */
    public function index(Request $request, string $tenantId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $perPage = $request->input('per_page', 20);
            
            // Get past bookings (start_date or start_time in the past)
            $query = Booking::forTenant($tenantId)
                ->with(['court.courtType', 'court.primaryImage', 'user', 'currency'])
                ->where(function($q) {
                    $q->where('start_date', '<', now()->format('Y-m-d'))
                      ->orWhere(function($subQ) {
                          $subQ->where('start_date', '=', now()->format('Y-m-d'))
                               ->where('start_time', '<', now());
                      });
                });

            // Filter by presence status if provided
            if ($request->has('present')) {
                if ($request->present === 'true' || $request->present === '1') {
                    $query->where('present', true);
                } elseif ($request->present === 'false' || $request->present === '0') {
                    $query->where('present', false);
                } elseif ($request->present === 'null') {
                    $query->whereNull('present');
                }
            }

            // Filter by date range if provided
            if ($request->has('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->where('start_date', '<=', $request->end_date);
            }

            $bookings = $query->latest('start_date')
                ->latest('start_time')
                ->paginate($perPage);

            return BookingResource::collection($bookings);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar histórico de reservas', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar histórico de reservas');
        }
    }
}
