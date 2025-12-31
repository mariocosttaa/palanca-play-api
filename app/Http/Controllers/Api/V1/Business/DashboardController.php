<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\UserResourceSpecific;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;

/**
 * @tags [API-BUSINESS] Dashboard
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard statistics including cards, lists, and charts
     * 
     * Retrieves a comprehensive set of statistics for the dashboard, including revenue, bookings, client activity, and charts.
     * 
    /**
     * Get dashboard statistics including cards, lists, and charts
     * 
     * Retrieves a comprehensive set of statistics for the dashboard, including revenue, bookings, client activity, and charts.
     */
    public function index(Request $request, $tenantId): \App\Http\Resources\Business\V1\Specific\DashboardResource
    {
        try {
            $tenant = $request->tenant;
            $now = now();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();

            // 1. CARDS DATA

            // Total Revenue (Current Month) - Only paid bookings
            $totalRevenue = (int) (Booking::forTenant($tenant->id)
                ->whereMonth('start_date', $now->month)
                ->whereYear('start_date', $now->year)
                ->where('payment_status', PaymentStatusEnum::PAID)
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->sum('price') ?? 0);

            // Total Open Bookings (Upcoming from today onwards)
            $totalOpenBookings = Booking::forTenant($tenant->id)
                ->where('start_date', '>=', $now->format('Y-m-d'))
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->count();

            // Total Clients (Users who have at least one booking with this tenant)
            // Note: This might be heavy if many users. Optimized approach:
            $totalClients = Booking::forTenant($tenant->id)
                ->distinct('user_id')
                ->count('user_id');

            // Total Court Usage (Hours in Current Month)
            // We need to calculate duration for each booking.
            // Since start_time and end_time are Time strings, we can do this in PHP or DB.
            // DB is faster but depends on database engine (MySQL/Postgres/SQLite).
            // Let's do PHP for compatibility and safety, fetching only necessary fields.
            $monthBookings = Booking::forTenant($tenant->id)
                ->whereMonth('start_date', $now->month)
                ->whereYear('start_date', $now->year)
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->get(['start_time', 'end_time']);

            $totalHours = 0;
            foreach ($monthBookings as $booking) {
                $start = Carbon::parse($booking->start_time);
                $end = Carbon::parse($booking->end_time);
                $diff = $end->diffInMinutes($start);
                $totalHours += abs($diff) / 60;
            }
            $totalHours = round($totalHours, 1);


            // 2. LISTS DATA

            // 5 Recent Bookings
            $recentBookings = Booking::forTenant($tenant->id)
                ->with(['user', 'court'])
                ->latest()
                ->take(5)
                ->get();

            // 5 Most Active Clients (Most bookings all time)
            $activeClientsData = Booking::forTenant($tenant->id)
                ->select('user_id', DB::raw('count(*) as total_bookings'))
                ->groupBy('user_id')
                ->orderByDesc('total_bookings')
                ->take(5)
                ->get();
            
            $activeClientIds = $activeClientsData->pluck('user_id');
            $activeClients = User::whereIn('id', $activeClientIds)->get();
            
            // Map count to user objects
            $activeClientsWithStats = $activeClients->map(function ($user) use ($activeClientsData) {
                $stat = $activeClientsData->firstWhere('user_id', $user->id);
                $user->total_bookings_count = $stat ? $stat->total_bookings : 0;
                return array_merge(
                    (new UserResourceSpecific($user))->resolve(),
                    ['total_bookings_count' => $user->total_bookings_count]
                );
            })->sortByDesc('total_bookings_count')->values();


            // Most Reserved Courts (All time)
            $popularCourtsData = Booking::forTenant($tenant->id)
                ->select('court_id', DB::raw('count(*) as total_bookings'))
                ->groupBy('court_id')
                ->orderByDesc('total_bookings')
                ->take(5)
                ->get();

            $popularCourtIds = $popularCourtsData->pluck('court_id');
            $popularCourts = Court::whereIn('id', $popularCourtIds)->get();

            $popularCourtsWithStats = $popularCourts->map(function ($court) use ($popularCourtsData) {
                $stat = $popularCourtsData->firstWhere('court_id', $court->id);
                $court->total_bookings_count = $stat ? $stat->total_bookings : 0;
                return array_merge(
                    $court->toArray(),
                    ['total_bookings_count' => $court->total_bookings_count]
                );
            })->sortByDesc('total_bookings_count')->values();


            // 3. CHART DATA

            // Daily Revenue (Current Month)
            $dailyRevenueBookings = Booking::forTenant($tenant->id)
                ->whereMonth('start_date', $now->month)
                ->whereYear('start_date', $now->year)
                ->where('payment_status', PaymentStatusEnum::PAID)
                ->where('status', '!=', BookingStatusEnum::CANCELLED)
                ->get();

            // Fill missing days with 0
            $dailyRevenue = [];
            $daysInMonth = $now->daysInMonth;
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = $startOfMonth->copy()->day($day)->format('Y-m-d');
                
                // Filter bookings for this day
            $dayRevenue = $dailyRevenueBookings->filter(function ($booking) use ($date) {
                return Carbon::parse($booking->start_date)->format('Y-m-d') === $date;
            })->sum('price');
                
                $dailyRevenue[] = [
                    'date' => $date,
                    'day' => $day,
                    'revenue' => $dayRevenue,
                    'revenue_formatted' => $this->formatMoney($dayRevenue, $tenant),
                ];
            }


            return new \App\Http\Resources\Business\V1\Specific\DashboardResource([
                'total_revenue' => $totalRevenue,
                'total_revenue_formatted' => $this->formatMoney($totalRevenue, $tenant),
                'total_open_bookings' => $totalOpenBookings,
                'total_clients' => $totalClients,
                'total_court_usage_hours' => $totalHours,
                'recent_bookings' => $recentBookings,
                'active_clients' => $activeClientsWithStats,
                'popular_courts' => $popularCourtsWithStats,
                'daily_revenue' => $dailyRevenue,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve dashboard statistics.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve dashboard statistics.');
        }
    }

    private function formatMoney($amountInCents, $tenant)
    {
        $currencyMap = [
            'usd' => '$',
            'eur' => '€',
            'gbp' => '£',
            'brl' => 'R$',
            'jpy' => '¥',
        ];
        $symbol = $currencyMap[strtolower($tenant->currency)] ?? $tenant->currency;
        
        $amount = $amountInCents / 100;
        return $symbol . ' ' . number_format($amount, 2, '.', ',');
    }
}
