<?php

namespace App\Http\Middleware;

use App\Services\TimezoneService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetUserTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If no user found on default guard, check specific guards
        if (! $user) {
            if (Auth::guard('business')->check()) {
                $user = Auth::guard('business')->user();
            } elseif (Auth::guard('sanctum')->check()) {
                $user = Auth::guard('sanctum')->user();
            } elseif (Auth::guard('web')->check()) {
                $user = Auth::guard('web')->user();
            }
        }
        if ($user && method_exists($user, 'timezone') && $user->timezone) {
            // Assuming the relationship is 'timezone' and it has a 'name' attribute
            $timezoneName = $user->timezone->name;
            app(TimezoneService::class)->setContextTimezone($timezoneName);
        }

        return $next($request);
    }
}
