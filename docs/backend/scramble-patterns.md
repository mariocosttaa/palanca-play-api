# Scramble Documentation Patterns

This guide outlines the best practices for documenting API endpoints using Scramble in our Laravel application. Following these patterns ensures that the generated OpenAPI documentation is accurate and consistent.

## General Strategy: Minimalist Default

**Rule #1:** Keep docblocks as simple as possible.

1.  **Summary Only:** By default, ONLY add a short description (summary) to the docblock.
2.  **No Tags:** Do NOT add `@return`, `@response`, `@tags`, or other annotations unless absolutely necessary (i.e., "last resort" when Scramble completely fails or the user explicitly requests it).
3.  **Rely on Code:** Trust Scramble to infer the response from the controller method's return type (e.g., `JsonResource`, `ResourceCollection`).
4.  **Exceptions:** Explicit tags are allowed ONLY if automatic inference is impossible (e.g., complex `JsonResponse` without a Resource) AND it is deemed critical. Otherwise, prefer simplicity.

## 1. Resource Responses

When an endpoint returns a single resource (e.g., `show`, `store`, `update`), return the Resource class directly. Scramble will infer the schema from the Resource class.

**Best Practice:**
- Return the Resource instance directly.
- Use `@return` to specify the Resource class.
- Use `@response` to specify the status code (especially for 201 Created) and the Resource class.

```php
/**
 * Create a new booking
 *
 * @return \App\Http\Resources\Business\V1\Specific\BookingResource
 * @response 201 \App\Http\Resources\Business\V1\Specific\BookingResource
 */
public function store(CreateBookingRequest $request): BookingResource
{
    // ... logic ...
    return new BookingResource($booking);
}
```

**Avoid:**
- Wrapping the resource in `response()->json(...)` if you want Scramble to automatically infer the schema.
- Returning `JsonResponse` without explicit `@response` tags pointing to the Resource.

## 2. Collection Responses

When an endpoint returns a list of resources (e.g., `index`), use the `collection` method.

**Best Practice:**
- Return `Resource::collection(...)`.
- Use `@return \Illuminate\Http\Resources\Json\AnonymousResourceCollection`.

```php
/**
 * List bookings
 *
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function index(Request $request): AnonymousResourceCollection
{
    $bookings = Booking::paginate();
    return BookingResource::collection($bookings);
}
```

## 3. Error Responses

Document error responses explicitly using `@response` tags with example JSON. This provides clear feedback to API consumers about potential failure states.

**Best Practice:**
- Use `@response status_code {"key": "value"}`.
- Provide specific examples for different error scenarios.

```php
/**
 * ...
 * @response 400 {"message": "Horário indisponível."}
 * @response 400 {"message": "Quadra inválida."}
 * @response 404 {"message": "Agendamento não encontrado."}
 */
```

## 4. Status Codes

- **200 OK:** Default for successful GET, PUT, DELETE (if returning content).
- **201 Created:** Standard for successful POST (creation). **Must be explicitly documented** using `@response 201 ResourceClass`.
- **204 No Content:** Standard for successful DELETE (if no content returned).

## 5. Troubleshooting

If the documentation shows an unexpected type (e.g., `integer` instead of `object`):
1. Check if you have conflicting `@return` and `@response` tags.
2. Ensure you are returning the Resource class directly, or if returning `JsonResponse`, that you have a clear `@response` tag.
3. Verify that the Resource class itself is correctly defined and Scramble can parse it.

## References
- [Scramble Documentation - Responses](https://scramble.dedoc.co/usage/response)

## 6. Endpoint Descriptions & Parameters

Always provide a short description of what the endpoint does. Document query parameters, especially for pagination.

**Best Practice:**
- Use PHPDoc summary for the short description.
- Use `@query` or `@urlParam` to document parameters if not automatically inferred from FormRequest.

```php
/**
 * List bookings
 *
 * Get a paginated list of bookings.
 *
 * @queryParam page int The page number. Example: 1
 * @queryParam per_page int The number of items per page. Example: 15
 */
public function index(Request $request)
{
    // ...
}
```

## 7. Response Types: JsonResponse vs Resource

Choose the appropriate return type based on complexity:

- **Simple Responses:** For small, simple responses (e.g., status updates, simple confirmations), use `response()->json()`.
    ```php
    /**
     * @response 200 {"status": "ok"}
     */
    public function check() {
        return response()->json(['status' => 'ok']);
    }
    ```

- **Complex/Large Responses:** For full data models or large datasets, **ALWAYS** use a `JsonResource`. This ensures consistency, reusability, and complete documentation of all fields.
    ```php
    public function show(Booking $booking): BookingResource
    {
        return new BookingResource($booking);
    }
    ```

## 8. Array Shape for JsonResponse

When returning a `JsonResponse` with a complex structure (like nested arrays) where Scramble fails to infer the schema from `@response` tags, use the `@return` tag with an array shape definition. This forces Scramble to use the defined structure.

**Best Practice:**
- Use `@return array{key: type, ...}` to define the exact shape of the response.
- You can reference other resources within the array shape using their full class path.

```php
/**
 * Get monthly report
 *
 * @return array{
 *   data: array{
 *     year: int,
 *     month: int,
 *     bookings: \App\Http\Resources\BookingResource[],
 *     summary: array{
 *       total: int,
 *       revenue: string
 *     }
 *   }
 * }
 */
public function report(): JsonResponse
{
    return response()->json([...]);
}
```

## 9. JsonResource Return Types for Complex Responses

When returning complex JSON structures (especially arrays of objects) where Scramble fails to infer the schema from `JsonResponse`, create a dedicated `JsonResource` class and use it as the return type.

**Best Practice:**
- Create a Resource class that extends `JsonResource`
- Define the `toArray()` method with proper return structure
- Use the Resource class as the method's return type hint
- Use `abort()` for error responses instead of returning `JsonResponse`

**Example:**

```php
// app/Http/Resources/Business/V1/Specific/CourtSlotsResource.php
class CourtSlotsResource extends JsonResource
{
    public function toArray($request)
    {
        // Laravel automatically wraps in 'data' key
        return collect($this->resource)->map(function ($slot) {
            return [
                'start' => $slot['start'],
                'end' => $slot['end'],
            ];
        })->values()->all();
    }
}

// In Controller
/**
 * Get available slots
 *
 * @urlParam date string required Date in Y-m-d format. Example: 2025-12-22
 * @queryParam booking_id string optional Booking ID to exclude. Example: abc123
 */
public function getSlots(Request $request, $tenantId, $courtId, $date): CourtSlotsResource
{
    try {
        // Validation...
        $slots = $court->getAvailableSlots($date, $excludeBookingId);

        return new CourtSlotsResource($slots);

    } catch (\Exception $e) {
        Log::error('Failed to retrieve slots.', ['error' => $e->getMessage()]);
        abort(500, 'Failed to retrieve available slots.');
    }
}
```

**Why This Works:**
- Scramble can analyze the Resource's `toArray()` method
- Type hint ensures Scramble knows the return type
- Using `abort()` for errors maintains consistent return type
- Laravel automatically wraps Resource response in `{"data": ...}` format

**When to Use:**
- Returning arrays of objects with specific structure
- Complex nested data structures
- When `@response` tags don't work with `JsonResponse`
- Collections that aren't Eloquent models

## Key Takeaways

✅ **Use JsonResource with type hints** for complex array responses  
✅ **Use `abort()` for errors** when return type is a Resource  
✅ **Let Laravel wrap responses** - don't manually add `data` key in Resource  
✅ **Keep docblocks minimal** - rely on type hints and code structure  
✅ **Import and use short class names** instead of full namespaces
