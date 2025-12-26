<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\FinancialResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;

/**
 * @tags [API-BUSINESS] Financials
 */
class FinancialController extends Controller
{
    /**
     * Get current month financial report
     */
    public function currentMonth(Request $request, $tenant_id): JsonResponse
    {
        try {
            $tenant = $request->tenant;
            $now = now();
            
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
     * Retrieves a detailed financial report for a specific month and year, including a list of all bookings and a financial summary.
     */
    public function monthlyReport(Request $request, $tenant_id, $year, $month): JsonResponse
    {
        try {
            $tenant = $request->tenant;
            
            // Cast to integers
            $year = (int) $year;
            $month = (int) $month;
            
            // Validate year and month
            $validated = $this->validateYearMonth($year, $month);
            if ($validated !== true) {
                // validateYearMonth returns true or aborts
            }

            // Get start and end dates for the month
            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Validate not in future
            if ($startDate->isFuture()) {
                abort(400, 'Cannot query future months.');
            }

            // Get bookings for the month
            $bookings = Booking::forTenant($tenant->id)
                ->with(['user', 'currency'])
                ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('start_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->get();

            return response()->json(['data' => [
                'year' => (int) $year,
                'month' => (int) $month,
                'month_name' => $startDate->translatedFormat('F'),
                'bookings' => FinancialResource::collection($bookings),
                'summary' => $this->calculateMonthlySummary($bookings, $tenant),
            ]]);
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
     * Retrieves aggregated financial statistics for a specific month, including counts and amounts for different booking statuses (paid, pending, cancelled, unpaid).
     */
    public function monthlyStats(Request $request, $tenant_id, $year, $month): JsonResponse
    {
        try {
            $tenant = $request->tenant;
            
            // Cast to integers
            $year = (int) $year;
            $month = (int) $month;
            
            // Validate year and month
            $this->validateYearMonth($year, $month);

            // Get start and end dates for the month
            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Validate not in future
            if ($startDate->isFuture()) {
                abort(400, 'Cannot query future months.');
            }

            // Get bookings for the month
            $bookings = Booking::forTenant($tenant->id)
                ->with(['currency'])
                ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            $stats = $this->calculateStatistics($bookings, $tenant);

            return response()->json(['data' => [
                'year' => (int) $year,
                'month' => (int) $month,
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
     * Retrieves aggregated financial statistics for a specific year, including a monthly breakdown of revenue and bookings.
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
            $endDate = $startDate->copy()->endOfYear();

            // Validate not in future
            if ($startDate->isFuture()) {
                abort(400, 'Cannot query future years.');
            }

            // Get all bookings for the year
            $bookings = Booking::forTenant($tenant->id)
                ->with(['currency'])
                ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            // Calculate yearly statistics
            $yearlyStats = $this->calculateStatistics($bookings, $tenant);

            // Calculate monthly breakdown
            $monthlyBreakdown = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                
                $monthBookings = $bookings->filter(function ($booking) use ($monthStart, $monthEnd) {
                    $bookingDate = Carbon::parse($booking->start_date);
                    return $bookingDate->between($monthStart, $monthEnd);
                });

                $monthStats = $this->calculateStatistics($monthBookings, $tenant);
                
                $monthlyBreakdown[] = [
                    'month' => $month,
                    'month_name' => $monthStart->translatedFormat('F'),
                    'total_revenue' => $monthStats['total_revenue'],
                    'total_revenue_formatted' => $monthStats['total_revenue_formatted'],
                    'total_bookings' => $monthStats['total_bookings'],
                ];
            }

            return response()->json(['data' => [
                'year' => (int) $year,
                'statistics' => $yearlyStats,
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
        $paidCount = 0;
        $pendingCount = 0;
        $cancelledCount = 0;
        $notPresentCount = 0;
        $unpaidCount = 0;
        
        $paidAmount = 0;
        $pendingAmount = 0;
        $cancelledAmount = 0;
        $unpaidAmount = 0;

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
        $noShowRate = $totalBookings > 0 ? (float) round(($notPresentCount / $totalBookings) * 100, 2) : 0.0;
        $paymentRate = $totalBookings > 0 ? (float) round(($paidCount / $totalBookings) * 100, 2) : 0.0;
        $pendingRate = $totalBookings > 0 ? (float) round(($pendingCount / $totalBookings) * 100, 2) : 0.0;

        // Get currency symbol
        $currencySymbol = $this->getCurrencySymbol($tenant);

        return [
            'total_bookings' => $totalBookings,
            
            // Paid
            'paid_count' => $paidCount,
            'paid_amount' => $paidAmount,
            'paid_amount_formatted' => $this->formatMoney($paidAmount, $currencySymbol),
            
            // Pending
            'pending_count' => $pendingCount,
            'pending_amount' => $pendingAmount,
            'pending_amount_formatted' => $this->formatMoney($pendingAmount, $currencySymbol),
            
            // Cancelled
            'cancelled_count' => $cancelledCount,
            'cancelled_amount' => $cancelledAmount,
            'cancelled_amount_formatted' => $this->formatMoney($cancelledAmount, $currencySymbol),
            
            // Unpaid
            'unpaid_count' => $unpaidCount,
            'unpaid_amount' => $unpaidAmount,
            'unpaid_amount_formatted' => $this->formatMoney($unpaidAmount, $currencySymbol),
            
            // Not present (no-show)
            'not_present_count' => $notPresentCount,
            
            // Total revenue (only paid bookings)
            'total_revenue' => $paidAmount,
            'total_revenue_formatted' => $this->formatMoney($paidAmount, $currencySymbol),
            
            // Percentages
            'cancellation_rate' => $cancellationRate,
            'no_show_rate' => $noShowRate,
            'payment_rate' => $paymentRate,
            'pending_rate' => $pendingRate,
        ];
    }

    /**
     * Calculate monthly summary (simplified version for report view)
     */
    private function calculateMonthlySummary($bookings, $tenant)
    {
        $stats = $this->calculateStatistics($bookings, $tenant);
        
        return [
            'total_bookings' => $stats['total_bookings'],
            'total_revenue' => $stats['total_revenue'],
            'total_revenue_formatted' => $stats['total_revenue_formatted'],
            'paid_count' => $stats['paid_count'],
            'pending_count' => $stats['pending_count'],
            'cancelled_count' => $stats['cancelled_count'],
        ];
    }

    /**
     * Validate year and month parameters
     */
    private function validateYearMonth($year, $month)
    {
        // Cast to integers
        $year = (int) $year;
        $month = (int) $month;
        
        if ($year < 2000 || $year > 2100) {
            abort(400, 'Ano inválido.');
        }

        if ($month < 1 || $month > 12) {
            abort(400, 'Mês inválido.');
        }

        return true;
    }

    /**
     * Get currency symbol for tenant
     */
    private function getCurrencySymbol($tenant)
    {
        $currencyMap = [
            'usd' => '$',
            'eur' => '€',
            'gbp' => '£',
            'brl' => 'R$',
            'jpy' => '¥',
        ];

        return $currencyMap[strtolower($tenant->currency)] ?? $tenant->currency;
    }

    /**
     * Format money amount (stored in cents)
     */
    private function formatMoney($amountInCents, $currencySymbol)
    {
        $amount = $amountInCents / 100;
        return $currencySymbol . ' ' . number_format($amount, 2, '.', ',');
    }
}
