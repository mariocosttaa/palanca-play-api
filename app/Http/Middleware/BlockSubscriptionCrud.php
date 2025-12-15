<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSubscriptionCrud
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If there is a valid invoice, proceed
        if ($request->valid_invoice) {
            return $next($request);
        }

        // If no valid invoice, check if the request is a read operation
        if ($request->isMethod('get') || $request->isMethod('head') || $request->isMethod('options')) {
            return $next($request);
        }

        // Allow specific routes (exceptions)
        // e.g., updating tenant profile (tenant.update) or managing invoices (invoices.*)
        $routeName = $request->route()->getName();
        $allowedRoutes = [
            'tenant.update',
            // Add other allowed routes here, e.g., 'invoices.store', 'invoices.index' etc.
        ];

        if (in_array($routeName, $allowedRoutes)) {
            return $next($request);
        }

        // Block other write operations (POST, PUT, PATCH, DELETE)
        return response()->json([
            'message' => 'Sua subscrição expirou. Apenas leitura e atualização de perfil são permitidas. Por favor, renove sua subscrição.',
            'code' => 'SUBSCRIPTION_EXPIRED_CRUD_BLOCKED'
        ], 403);
    }
}
