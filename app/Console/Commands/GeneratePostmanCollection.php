<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class GeneratePostmanCollection extends Command
{
    protected $signature = 'generate:postman-collection';
    protected $description = 'Generate Postman collection from API routes';

    public function handle()
    {
        $this->info('Generating Postman collection from API routes...');

        $collection = [
            'info' => [
                'name' => 'Padel Booking API - Complete Collection',
                'description' => 'Complete API collection for Padel Booking API. Auto-generated from routes.',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                'version' => '1.0.0',
            ],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => 'http://localhost:8000',
                    'type' => 'string',
                ],
                [
                    'key' => 'user_token',
                    'value' => '',
                    'type' => 'string',
                ],
                [
                    'key' => 'business_user_token',
                    'value' => '',
                    'type' => 'string',
                ],
            ],
            'item' => [],
        ];

        // Get all API routes (both mobile /api/ and business /business/)
        $apiRoutes = collect(Route::getRoutes())->filter(function ($route) {
            $uri = $route->uri();
            return Str::startsWith($uri, 'api/') || Str::startsWith($uri, 'business/');
        });

        // Group routes by prefix/controller
        $groupedRoutes = [];
        foreach ($apiRoutes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $method = ! in_array('HEAD', $methods) ? $methods[0] : 'GET';
            $action = $route->getAction();

            // Skip if not a controller action
            if (! isset($action['controller'])) {
                continue;
            }

            $controller = $action['controller'];
            $parts = explode('@', $controller);
            $controllerName = class_basename($parts[0] ?? '');
            $methodName = $parts[1] ?? '';

            // Extract group name from URI
            // For mobile: api/v1/users -> users
            // For business: business/v1/business/{tenant_id}/courts -> courts
            $uriParts = explode('/', $uri);

            // Determine if it's mobile or business API
            $isBusiness = Str::startsWith($uri, 'business/');

            if ($isBusiness) {
                // For business routes, group by the resource name (e.g., courts, court-types)
                // business/v1/business/{tenant_id}/courts -> courts
                // business/v1/business/{tenant_id}/courts/{court_id} -> courts
                // business/v1/business/{tenant_id}/court-types -> court-types
                $lastPart = $uriParts[count($uriParts) - 1] ?? '';
                $secondLastPart = $uriParts[count($uriParts) - 2] ?? '';

                // If last part is a parameter (starts with {), use second-to-last as group
                if (Str::startsWith($lastPart, '{')) {
                    $groupName = $secondLastPart;
                    $endpointName = $lastPart;
                } else {
                    $groupName = $lastPart;
                    $endpointName = $lastPart;
                }
            } else {
                // For mobile routes
                $groupName = $uriParts[count($uriParts) - 2] ?? 'Other';
                $endpointName = $uriParts[count($uriParts) - 1] ?? 'index';
            }

            if (! isset($groupedRoutes[$groupName])) {
                $groupedRoutes[$groupName] = [];
            }

            $groupedRoutes[$groupName][] = [
                'name' => $this->formatEndpointName($method, $endpointName, $methodName),
                'method' => $method,
                'uri' => $uri,
                'controller' => $controllerName,
                'middleware' => $route->middleware(),
            ];
        }

        // Separate mobile and business routes
        $mobileGroups = [];
        $businessGroups = [];

        foreach ($groupedRoutes as $groupName => $routes) {
            $items = [];
            $isBusinessGroup = false;

            foreach ($routes as $route) {
                $isBusinessGroup = Str::startsWith($route['uri'], 'business/');
                $item = $this->buildRequestItem($route);
                if ($item) {
                    $items[] = $item;
                }
            }

            if (! empty($items)) {
                if ($isBusinessGroup) {
                    $businessGroups[$groupName] = $items;
                } else {
                    $mobileGroups[$groupName] = $items;
                }
            }
        }

        // Add Mobile API section
        if (!empty($mobileGroups)) {
            $mobileItems = [];
            foreach ($mobileGroups as $groupName => $items) {
                $mobileItems[] = [
                    'name' => $this->formatGroupName($groupName),
                    'item' => $items,
                ];
            }
            $collection['item'][] = [
                'name' => 'Mobile API',
                'item' => $mobileItems,
            ];
        }

        // Add Business API section
        if (!empty($businessGroups)) {
            $businessItems = [];
            foreach ($businessGroups as $groupName => $items) {
                $businessItems[] = [
                    'name' => $this->formatGroupName($groupName),
                    'item' => $items,
                ];
            }
            $collection['item'][] = [
                'name' => 'Business API',
                'item' => $businessItems,
            ];
        }

        // Write to file
        $filePath = base_path('docs/tests/api-test-collection.json');
        file_put_contents($filePath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Postman collection generated successfully at: {$filePath}");
        $this->info('Total endpoints: ' . count($apiRoutes));

        return 0;
    }

    private function formatGroupName(string $name): string
    {
        return Str::title(str_replace('-', ' ', $name));
    }

    private function formatEndpointName(string $method, string $endpoint, string $controllerMethod): string
    {
        $name = Str::title(str_replace('-', ' ', $endpoint));
        return "{$method} {$name}";
    }

    private function buildRequestItem(array $route): ?array
    {
        $uri = $route['uri'];
        $method = $route['method'];
        $name = $route['name'];
        $middleware = $route['middleware'];

        $isAuth = in_array('auth:sanctum', $middleware) || in_array('sanctum', $middleware);

        $item = [
            'name' => $name,
            'request' => [
                'method' => $method,
                'header' => [
                    [
                        'key' => 'Accept',
                        'value' => 'application/json',
                        'type' => 'text',
                    ],
                ],
                'url' => [
                    'raw' => '{{base_url}}/' . $uri,
                    'host' => ['{{base_url}}'],
                    'path' => explode('/', $uri),
                ],
            ],
            'response' => [],
        ];

        // Add Authorization header if protected
        if ($isAuth) {
            // Determine token type based on route
            $isBusinessRoute = Str::startsWith($uri, 'business/');
            $tokenVar = $isBusinessRoute ? 'business_user_token' : 'user_token';
            $item['request']['header'][] = [
                'key' => 'Authorization',
                'value' => "Bearer {{$tokenVar}}",
                'type' => 'text',
            ];
        }

        // Add Content-Type for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            array_unshift($item['request']['header'], [
                'key' => 'Content-Type',
                'value' => 'application/json',
                'type' => 'text',
            ]);

            // Add body for POST/PUT/PATCH
            $item['request']['body'] = [
                'mode' => 'raw',
                'raw' => $this->getExampleBody($uri, $method),
            ];
        }

        // Add token auto-save for login/register endpoints
        if (Str::contains($uri, 'login') || Str::contains($uri, 'register')) {
            $isBusinessRoute = Str::startsWith($uri, 'business/');
            $tokenVar = $isBusinessRoute ? 'business_user_token' : 'user_token';
            $item['event'] = [
                [
                    'listen' => 'test',
                    'script' => [
                        'exec' => [
                            "if (pm.response.code === 200 || pm.response.code === 201) {",
                            "    const jsonData = pm.response.json();",
                            "    if (jsonData.data && jsonData.data.token) {",
                            "        pm.collectionVariables.set('{$tokenVar}', jsonData.data.token);",
                            "    }",
                            "}",
                        ],
                        'type' => 'text/javascript',
                    ],
                ],
            ];
        }

        return $item;
    }

    private function getExampleBody(string $uri, string $method): string
    {
        // Example bodies for known endpoints
        $examples = [
            // Mobile API
            'api/v1/users/register' => json_encode([
                'name' => 'John Doe',
                'surname' => 'Doe',
                'email' => 'john.doe@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'device_name' => 'Postman/Hoppscotch',
            ], JSON_PRETTY_PRINT),
            'api/v1/users/login' => json_encode([
                'email' => 'john.doe@example.com',
                'password' => 'password123',
                'device_name' => 'Postman/Hoppscotch',
            ], JSON_PRETTY_PRINT),
            // Business API
            'business/v1/business-users/register' => json_encode([
                'name' => 'Jane Business',
                'surname' => 'Smith',
                'email' => 'jane.business@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'device_name' => 'Postman/Hoppscotch',
            ], JSON_PRETTY_PRINT),
            'business/v1/business-users/login' => json_encode([
                'email' => 'jane.business@example.com',
                'password' => 'password123',
                'device_name' => 'Postman/Hoppscotch',
            ], JSON_PRETTY_PRINT),
            // Court Types
            'business/v1/business/{tenant_id}/court-types' => json_encode([
                'type' => 'padel',
                'name' => 'Court Type Example',
                'description' => 'Example court type description',
                'interval_time_minutes' => 60,
                'buffer_time_minutes' => 0,
                'status' => true,
            ], JSON_PRETTY_PRINT),
            // Courts
            'business/v1/business/{tenant_id}/courts' => json_encode([
                'court_type_id' => '{{court_type_id_hash}}',
                'name' => 'Court Example',
                'number' => '1',
                'status' => true,
            ], JSON_PRETTY_PRINT),
        ];

        return $examples[$uri] ?? '{}';
    }
}

