<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;
        
        // Priority 1: User's saved locale preference (if authenticated and set)
        $user = $request->user();
        if ($user && !empty($user->locale)) {
            // If locale is an enum instance (due to casting), get the value
            $locale = $user->locale instanceof \App\Enums\LocaleEnum ? $user->locale->value : $user->locale;
        }
        
        // Priority 2: Accept-Language header (if no user preference)
        if (!$locale) {
            $acceptLanguage = $request->header('Accept-Language');
            if ($acceptLanguage) {
                // If header contains multiple languages (e.g. "en-US,en;q=0.9"), take the first one
                $locale = substr($acceptLanguage, 0, 2);
            }
        }

        // Fallback to default locale if not supported
        if (!in_array($locale, ['en', 'pt'])) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
