<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * Sensitive fields that should be masked in logs
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        'secret',
        'authorization',
        'bearer',
        'access_token',
        'refresh_token',
        'client_secret',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate unique request ID for correlation
        $requestId = Str::uuid()->toString();
        $startTime = microtime(true);

        // Get the response
        $response = $next($request);

        // Only log API routes
        if (!$this->isApiRoute($request)) {
            return $response;
        }

        try {
            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $logData = [
                'request_id' => $requestId,
                'timestamp' => now()->toIso8601String(),
                'user' => $this->getUserInfo($request),
                'request' => $this->getRequestData($request),
                'response' => $this->getResponseData($response),
                'execution_time_ms' => round($executionTime, 2),
            ];

            Log::channel('api')->info('API Request', $logData);
        } catch (\Exception $e) {
            // Don't break the request if logging fails
            Log::error('Failed to log API request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $response;
    }

    /**
     * Check if the request is for an API route
     */
    private function isApiRoute(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/');
    }

    /**
     * Get user information from request
     */
    private function getUserInfo(Request $request): array
    {
        $user = $request->user();

        if (!$user) {
            return [
                'authenticated' => false,
                'type' => 'guest',
            ];
        }

        // Determine user type based on guard
        $guard = null;
        if ($request->user('business')) {
            $guard = 'business';
        } elseif ($request->user('sanctum')) {
            $guard = 'sanctum';
        }

        return [
            'authenticated' => true,
            'type' => $guard ?? 'unknown',
            'id' => $user->id,
            'email' => $user->email ?? null,
        ];
    }

    /**
     * Get sanitized request data
     */
    private function getRequestData(Request $request): array
    {
        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->sanitizeData($request->headers->all()),
            'query' => $this->sanitizeData($request->query()),
            'body' => $this->sanitizeData($request->all()),
        ];
    }

    /**
     * Get response data
     */
    private function getResponseData(Response $response): array
    {
        $content = $response->getContent();
        $decodedContent = json_decode($content, true);

        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $decodedContent ?? $content,
            'size' => strlen($content),
        ];
    }

    /**
     * Sanitize data by masking sensitive fields
     */
    private function sanitizeData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Check if key contains sensitive field names
            $isSensitive = false;
            foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
                if (str_contains($lowerKey, $sensitiveField)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '***HIDDEN***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
