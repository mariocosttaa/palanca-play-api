<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Business\V1\Specific\SubscriptionResourceSpecific;
use App\Http\Resources\Business\V1\General\InvoiceResourceGeneral;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Subscriptions
 */
class SubscriptionController extends Controller
{
    /**
     * Get a list of invoices for the tenant
     * 
     * @queryParam page int optional Page number. Example: 1
     * @queryParam per_page int Number of items per page. Example: 15
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexInvoices(Request $request, string $tenantId): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $tenant = $request->tenant;
            $perPage = $request->input('per_page', 15);
            
            $invoices = Invoice::forTenant($tenant->id)
                ->with('tenant')
                ->latest()
                ->paginate($perPage);

            return InvoiceResourceGeneral::collection($invoices);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve invoices.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve invoices.');
        }
    }

    /**
     * Get current subscription status
     * 
     * @return SubscriptionResourceSpecific
     */
    public function current(Request $request, string $tenantId): SubscriptionResourceSpecific
    {
        try {
            $tenant = $request->tenant;
            
            // The middleware CheckTenantSubscription already injects the valid invoice
            // But we want to get the latest invoice regardless of validity to show status
            $latestInvoice = Invoice::forTenant($tenant->id)
                ->latest('date_end')
                ->first();

            $currentCourts = Court::where('tenant_id', $tenant->id)->count();
            
            $status = 'none';
            $maxCourts = 0;
            $dateEnd = null;
            $daysRemaining = 0;

            if ($latestInvoice) {
                $dateEnd = $latestInvoice->date_end;
                $maxCourts = $latestInvoice->max_courts;
                
                if ($latestInvoice->status === 'paid') {
                    if ($dateEnd->isFuture()) {
                        $status = 'active';
                        $daysRemaining = (int) now()->diffInDays($dateEnd);
                    } else {
                        $status = 'expired';
                    }
                } else {
                    $status = $latestInvoice->status; // pending, etc.
                }
            }

            $data = [
                'status' => $status,
                'max_courts' => $maxCourts,
                'current_courts' => $currentCourts,
                'date_end' => $dateEnd,
                'days_remaining' => $daysRemaining,
                'invoice' => $latestInvoice,
            ];

            return new SubscriptionResourceSpecific($data);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve subscription details.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve subscription details.');
        }
    }
}
