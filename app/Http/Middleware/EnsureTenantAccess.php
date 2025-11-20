<?php

namespace App\Http\Middleware;

use App\Actions\General\EasyHashAction;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * Decodes the tenant ID from route parameter, verifies it exists,
     * checks if the authenticated business user has access to it,
     * and merges the decoded tenant ID into the request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get tenant ID from route parameter (expected to be hashed)
        $tenantHashId = $request->route('tenant_id') ?? $request->route('tenant');

        if (!$tenantHashId) {
            abort(404, 'Tenant ID is required.');
        }

        // Decode the hashed tenant ID
        $tenantId = EasyHashAction::decode($tenantHashId, 'tenant-id');

        if (!$tenantId) {
            abort(404, 'Invalid tenant ID.');
        }

        // Verify tenant exists
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }

        // Get authenticated business user
        $businessUser = $request->user('business');

        if (!$businessUser) {
            abort(401, 'Unauthenticated.');
        }

        // Check if business user has access to this tenant
        $hasAccess = $businessUser->tenants()->where('tenants.id', $tenantId)->exists();

        if (!$hasAccess) {
            abort(403, 'You do not have access to this tenant.');
        }

        // Merge the decoded tenant ID into the request
        $request->merge(['tenant_id' => $tenantId]);
        $request->merge(['tenantId' => $tenantHashId]);

        $request->merge(['tenant' => $tenant]);

        return $next($request);
    }
}

