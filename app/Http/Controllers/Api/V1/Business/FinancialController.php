<?php
namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\MoneyAction;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\FinancialResource;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Financials
 */
class FinancialController extends Controller
{
    /**
     * Get current month financial report
     */
    public function currentMonth(Request $request, $tenant_id): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $tenant = $request->tenant;
            $now    = now();

            return $this->monthlyReport($request, $tenant_id, $now->year, $now->month);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve current month report.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve current month report.');
        }
    }

    /**
     * Get monthly financial report
     *
     * Retrieves a paginated list of bookings for a specific month and year.
     * Use the monthlyStats endpoint to get statistics for the same period.
     *
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int optional Items per page (max 20). Default: 20. Example: 20
     */
    public function monthlyReport(Request $request, $tenant_id, $year, $month): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $tenant = $request->tenant;

            // Cast to integers
            $year  = (int) $year;
            $month = (int) $month;

            // Validate year and month
            $this->validateYearMonth($year, $month);

            // Validate and limit per_page to max 20
            $perPage = min((int) $request->input('per_page', 20), 20);
            if ($perPage < 1) {
                $perPage = 20;
            }

            // Get start and end dates for the month
            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = $startDate->copy()->endOfMonth();

            // Validate not in future
            if ($startDate->isFuture()) {
                abort(400, 'Cannot query future months.');
            }

            // Get paginated bookings
            $bookings = Booking::forTenant($tenant->id)
                ->with(['user', 'currency'])
                ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('start_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->paginate($perPage);

            return FinancialResource::collection($bookings);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve monthly report.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve monthly report.');
        }
    }

    /**
     * Get monthly statistics
     *
     * Returns aggregated financial statistics for a specific month and year.
     * Includes counts, amounts, and percentages for all booking statuses (paid, pending, cancelled, unpaid, no-show).
     *
     * @urlParam year int required Year (YYYY). Example: 2024
     * @urlParam month int required Month (1-12). Example: 12
     */
    public function monthlyStats(Request $request, $tenant_id, $year, $month): JsonResponse
    {
        try {
            $tenant = $request->tenant;

            // Cast to integers
            $year  = (int) $year;
            $month = (int) $month;

            // Validate year and month
            $this->validateYearMonth($year, $month);

            // Get start and end dates for the month
            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = $startDate->copy()->endOfMonth();

            // Validate not in future
            if ($startDate->isFuture()) {
                abort(400, 'Cannot query future months.');
            }

            // Get bookings for the month (only load currency relation needed for calculations)
            $bookings = Booking::forTenant($tenant->id)
                ->with(['currency'])
                ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            $stats = $this->calculateStatistics($bookings, $tenant);

            return response()->json(['data' => [
                'year'       => (int) $year,
                'month'      => (int) $month,
                'month_name' => $startDate->translatedFormat('F'),
                'statistics' => $stats,
            ]]);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve monthly statistics.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve monthly statistics.');
        }
    }

    /**
     * Get yearly statistics
     *
     * Returns aggregated financial statistics for a specific year.
     * Includes yearly totals, percentages, and a monthly breakdown with revenue and booking counts for each month.
     *
     * @urlParam year int required Year (YYYY). Example: 2024
     *
     */
    public function yearlyStats(Request $request, $tenant_id, $year): JsonResponse
    {
        try {
            $tenant = $request->tenant;

            // Cast to integer
            $year = (int) $year;

            // Validate year
            if ($year < 2000 || $year > 2100) {
                abort(400, 'Invalid year.');
            }

            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate   = $startDate->copy()->endOfYear();

            // Validate not in future
            if ($startDate->isFuture()) {
                abort(400, 'Cannot query future years.');
            }

            // Get all bookings for the year (only load currency relation needed for calculations)
            // Single query approach is more efficient than multiple queries per month
            $bookings = Booking::forTenant($tenant->id)
                ->with(['currency'])
                ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            // Calculate yearly statistics from all bookings
            $yearlyStats = $this->calculateStatistics($bookings, $tenant);

            // Calculate monthly breakdown (filter in-memory for efficiency with already loaded data)
            $monthlyBreakdown = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $monthEnd   = $monthStart->copy()->endOfMonth();

                // Filter bookings by month from already loaded collection (more efficient than separate queries)
                $monthBookings = $bookings->filter(function ($booking) use ($monthStart, $monthEnd) {
                    $bookingDate = Carbon::parse($booking->start_date);
                    return $bookingDate->between($monthStart, $monthEnd);
                });

                $monthStats = $this->calculateStatistics($monthBookings, $tenant);

                $monthlyBreakdown[] = [
                    'month'                   => $month,
                    'month_name'              => $monthStart->translatedFormat('F'),
                    'total_revenue'           => $monthStats['total_revenue'],
                    'total_revenue_formatted' => $monthStats['total_revenue_formatted'],
                    'total_bookings'          => $monthStats['total_bookings'],
                ];
            }

            return response()->json(['data' => [
                'year'              => (int) $year,
                'statistics'        => $yearlyStats,
                'monthly_breakdown' => $monthlyBreakdown,
            ]]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve yearly statistics.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve yearly statistics.');
        }
    }

    /**
     * Calculate statistics for a collection of bookings
     */
    private function calculateStatistics($bookings, $tenant)
    {
        $totalBookings = $bookings->count();

        // Initialize counters
        $paidCount       = 0;
        $pendingCount    = 0;
        $cancelledCount  = 0;
        $notPresentCount = 0;
        $unpaidCount     = 0;

        $paidAmount      = 0;
        $pendingAmount   = 0;
        $cancelledAmount = 0;
        $unpaidAmount    = 0;

        foreach ($bookings as $booking) {
            if ($booking->status === BookingStatusEnum::CANCELLED) {
                $cancelledCount++;
                $cancelledAmount += $booking->price;
            } elseif ($booking->payment_status === PaymentStatusEnum::PAID) {
                $paidCount++;
                $paidAmount += $booking->price;
            } elseif ($booking->status === BookingStatusEnum::PENDING) {
                $pendingCount++;
                $pendingAmount += $booking->price;
            } else {
                // Not cancelled, not paid, not pending = unpaid (Confirmed but not paid)
                $unpaidCount++;
                $unpaidAmount += $booking->price;
            }

            // Check for no-shows (not present)
            if ($booking->present === false) {
                $notPresentCount++;
            }
        }

        // Calculate percentages (ensure float type)
        $cancellationRate = $totalBookings > 0 ? (float) round(($cancelledCount / $totalBookings) * 100, 2) : 0.0;
        $noShowRate       = $totalBookings > 0 ? (float) round(($notPresentCount / $totalBookings) * 100, 2) : 0.0;
        $paymentRate      = $totalBookings > 0 ? (float) round(($paidCount / $totalBookings) * 100, 2) : 0.0;
        $pendingRate      = $totalBookings > 0 ? (float) round(($pendingCount / $totalBookings) * 100, 2) : 0.0;

        // Get currency code from tenant
        $currencyCode = strtolower($tenant->currency ?? 'eur');

        return [
            'total_bookings'             => $totalBookings,

            // Paid
            'paid_count'                 => $paidCount,
            'paid_amount'                => $paidAmount,
            'paid_amount_formatted'      => MoneyAction::format($paidAmount, null, $currencyCode, true),

            // Pending
            'pending_count'              => $pendingCount,
            'pending_amount'             => $pendingAmount,
            'pending_amount_formatted'   => MoneyAction::format($pendingAmount, null, $currencyCode, true),

            // Cancelled
            'cancelled_count'            => $cancelledCount,
            'cancelled_amount'           => $cancelledAmount,
            'cancelled_amount_formatted' => MoneyAction::format($cancelledAmount, null, $currencyCode, true),

            // Unpaid
            'unpaid_count'               => $unpaidCount,
            'unpaid_amount'              => $unpaidAmount,
            'unpaid_amount_formatted'    => MoneyAction::format($unpaidAmount, null, $currencyCode, true),

            // Not present (no-show)
            'not_present_count'          => $notPresentCount,

            // Total revenue (only paid bookings)
            'total_revenue'              => $paidAmount,
            'total_revenue_formatted'    => MoneyAction::format($paidAmount, null, $currencyCode, true),

            // Percentages
            'cancellation_rate'          => $cancellationRate,
            'no_show_rate'               => $noShowRate,
            'payment_rate'               => $paymentRate,
            'pending_rate'               => $pendingRate,
        ];
    }

    /**
     * Validate year and month parameters
     */
    private function validateYearMonth($year, $month)
    {
        // Cast to integers
        $year  = (int) $year;
        $month = (int) $month;

        if ($year < 2000 || $year > 2100) {
            abort(400, 'Ano inválido.');
        }

        if ($month < 1 || $month > 12) {
            abort(400, 'Mês inválido.');
        }

        return true;
    }
}
