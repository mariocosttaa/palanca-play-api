<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\BookingStatsResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Bookings
 */
class BookingStatsController extends Controller
{
    /**
     * Get booking statistics
     * 
     * Retrieves aggregated statistics for bookings, including counts for today, tomorrow, and this month, as well as a status breakdown.
     * 
     * @return \App\Http\Resources\Business\V1\Specific\BookingStatsResource
     */
    public function index(Request $request, string $tenantId): \App\Http\Resources\Business\V1\Specific\BookingStatsResource {
        try {
            $tenantId = $request->tenant_id;
            $now = Carbon::now();

            // Base query for tenant
            $baseQuery = Booking::forTenant($tenantId);

            // 1. All bookings count
            $totalBookings = (clone $baseQuery)->count();

            // 2. Bookings this month
            $bookingsThisMonth = (clone $baseQuery)
                ->whereMonth('start_date', $now->month)
                ->whereYear('start_date', $now->year)
                ->count();

            // 3. Bookings today
            $bookingsToday = (clone $baseQuery)
                ->onDate($now->toDateString())
                ->count();

            // 4. Bookings tomorrow
            $bookingsTomorrow = (clone $baseQuery)
                ->onDate($now->copy()->addDay()->toDateString())
                ->count();

            // 5. Status breakdown for this month
            $monthQuery = (clone $baseQuery)
                ->whereMonth('start_date', $now->month)
                ->whereYear('start_date', $now->year);

            $confirmedThisMonth = (clone $monthQuery)->confirmed()->count();
            $pendingThisMonth = (clone $monthQuery)->pending()->where('is_cancelled', false)->count();
            $canceledThisMonth = (clone $monthQuery)->cancelled()->count();

            $stats = [
                'total_bookings' => $totalBookings,
                'bookings_this_month' => $bookingsThisMonth,
                'bookings_today' => $bookingsToday,
                'bookings_tomorrow' => $bookingsTomorrow,
                'status_breakdown_this_month' => [
                    'confirmed' => $confirmedThisMonth,
                    'pending' => $pendingThisMonth,
                    'canceled' => $canceledThisMonth,
                ],
            ];

            return new BookingStatsResource($stats);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar estatísticas de agendamentos', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao buscar estatísticas de agendamentos');
        }
    }
}
