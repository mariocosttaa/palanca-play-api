<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\General\InvoiceResourceGeneral;
use App\Http\Resources\General\SubscriptionDetailsResource;
use App\Models\Court;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function indexInvoices(Request $request)
    {
        $tenant = $request->tenant;
        $invoices = Invoice::forTenant($tenant->id)->latest()->get();

        return $this->dataResponse(InvoiceResourceGeneral::collection($invoices)->resolve());
    }

    public function current(Request $request)
    {
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

        return $this->dataResponse(SubscriptionDetailsResource::make($data)->resolve());
    }
}
