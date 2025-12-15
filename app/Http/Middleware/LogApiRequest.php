<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    /**
     * Handle an incoming request and log it.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate unique request ID
        $requestId = Str::uuid()->toString();
        $request->attributes->set('request_id', $requestId);
        
        // Record start time
        $startTime = microtime(true);
        
        // Process the request
        $response = $next($request);
        
        // Calculate response time
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        
        // Log the request asynchronously (don't block the response)
        try {
            ApiRequestLog::create([
                'request_id' => $requestId,
                'user_id' => $request->user()?->id ?? $request->user('business')?->id,
                'ip_address' => $request->ip(),
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'response_time' => $responseTime,
            ]);
        } catch (\Exception $e) {
            // Silently fail - logging should not break the application
            \Log::warning('Failed to log API request', [
                'error' => $e->getMessage(),
                'request_id' => $requestId,
            ]);
        }
        
        // Add request ID to response headers for tracking
        $response->headers->set('X-Request-ID', $requestId);
        
        return $response;
    }
}
