<?php

namespace App\Http\Middleware;

use App\Models\Invoice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->tenant;

        if (!$tenant) {
            // Should be handled by previous middleware, but just in case
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        // Check for the latest valid invoice
        $validInvoice = Invoice::forTenant($tenant->id)
            ->valid()
            ->latest('date_end')
            ->first();

        // Inject the valid invoice into the request for downstream use
        // If null, it means no valid subscription
        $request->merge(['valid_invoice' => $validInvoice]);

        // Inject the valid invoice into the request for downstream use
        $request->merge(['valid_invoice' => $validInvoice]);

        return $next($request);
    }
}
