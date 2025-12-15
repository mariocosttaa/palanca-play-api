<?php

namespace App\Http\Middleware;

use App\Actions\General\EasyHashAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure the authenticated user can access private tenant files.
 */
class TenantAuthPrivateFiles
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $tenantIdHashed = $request->route('tenantIdHashed');
        $tenantId = EasyHashAction::decode($tenantIdHashed, 'tenant-id');
        $user = $request->user();

        // Check if user is authenticated and belongs to the tenant
        if (!$user || $user->tenant_id != $tenantId) {
            abort(403, 'Unauthorized access to tenant files.');
        }

        return $next($request);
    }
}