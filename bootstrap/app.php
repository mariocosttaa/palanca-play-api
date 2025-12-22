<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Mobile API with Mobile CORS and Request Logging
            Route::middleware(['api', 'mobile.cors', 'api.log'])
                ->prefix('api')
                ->group(base_path('routes/api-mobile.php'));
            
            // Business API with Business CORS and Request Logging
            Route::middleware(['api', 'business.cors', 'api.log'])
                ->prefix('api/business')
                ->group(base_path('routes/api-business.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.show' => \App\Http\Middleware\EnsureTenantAccess::class,
            'mobile.cors' => \App\Http\Middleware\MobileCorsMiddleware::class,
            'business.cors' => \App\Http\Middleware\BusinessCorsMiddleware::class,
            'api.log' => \App\Http\Middleware\LogApiRequests::class,
            'verified.api' => \App\Http\Middleware\EnsureEmailIsVerifiedApi::class,
        ]);

        $middleware->append(\App\Http\Middleware\SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
