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

        // Skip logging if disabled via environment variable
        if (!config('logging.api_enabled', true)) {
            return $response;
        }

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

            // Format log message in a readable way
            $formattedLog = $this->formatLogMessage($logData);
            
            Log::channel('api')->info($formattedLog);
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

    /**
     * Format log message in a readable multi-line format
     */
    private function formatLogMessage(array $logData): string
    {
        $user = $logData['user'];
        $request = $logData['request'];
        $response = $logData['response'];
        
        $userInfo = $user['authenticated'] 
            ? "{$user['type']} (ID: {$user['id']}, Email: {$user['email']})"
            : 'Guest';

        $lines = [
            '',
            '════════════════════════════════════════════════════════════════',
            "🔵 {$request['method']} {$request['path']}",
            '════════════════════════════════════════════════════════════════',
            '',
            '📋 REQUEST INFO:',
            "   Request ID: {$logData['request_id']}",
            "   Timestamp:  {$logData['timestamp']}",
            "   User:       {$userInfo}",
            "   IP:         {$request['ip']}",
            "   User Agent: {$request['user_agent']}",
            '',
        ];

        // Add query parameters if any
        if (!empty($request['query'])) {
            $lines[] = '🔍 QUERY PARAMETERS:';
            $lines[] = '   ' . json_encode($request['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $lines[] = '';
        }

        // Add request body if any
        if (!empty($request['body'])) {
            $lines[] = '📤 REQUEST BODY:';
            $lines[] = $this->indentJson($request['body']);
            $lines[] = '';
        }

        // Add response info
        $statusEmoji = $response['status'] >= 500 ? '❌' : ($response['status'] >= 400 ? '⚠️' : '✅');
        $lines[] = "{$statusEmoji} RESPONSE:";
        $lines[] = "   Status: {$response['status']}";
        $lines[] = "   Size:   {$response['size']} bytes";
        $lines[] = "   Time:   {$logData['execution_time_ms']} ms";
        $lines[] = '';

        // Add response body (limited to avoid huge logs)
        if (!empty($response['body'])) {
            $bodyJson = is_string($response['body']) ? $response['body'] : json_encode($response['body']);
            
            // Limit response body size in logs (max 2000 chars)
            if (strlen($bodyJson) > 2000) {
                $bodyJson = substr($bodyJson, 0, 2000) . '... [truncated]';
            }
            
            $lines[] = '📥 RESPONSE BODY:';
            $lines[] = $this->indentJson(json_decode($bodyJson, true) ?? $bodyJson);
            $lines[] = '';
        }

        $lines[] = '════════════════════════════════════════════════════════════════';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Indent JSON for better readability
     */
    private function indentJson(mixed $data): string
    {
        if (is_string($data)) {
            return '   ' . $data;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $lines = explode("\n", $json);
        
        return implode("\n", array_map(fn($line) => '   ' . $line, $lines));
    }
}
