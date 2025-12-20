# API Localization & Language Patterns

## Overview

This API supports multi-language responses using Laravel's localization system with a simple, developer-friendly approach where **English messages serve as translation keys**.

## Supported Languages

- **English (en)** - Default
- **Portuguese (pt)**
- **Spanish (es)** - Prepared (translations needed)
- **French (fr)** - Prepared (translations needed)

## Language Detection Priority

The API determines the response language using the following priority order:

1. **User's Saved Locale Preference** (if authenticated and set in database)
2. **Accept-Language Header** (from HTTP request)
3. **Default Locale** (`pt` - Portuguese, as configured in `config/app.php`)

### Example:
```http
GET /api/business/v1/business/{tenant_id}/courts
Accept-Language: pt
Authorization: Bearer {token}
```

## Translation Pattern

### Using English as Translation Keys

Instead of using dot-notation keys like `messages.court_not_found`, we use the **actual English message** as the key:

#### ✅ Recommended Pattern:
```php
return response()->json([
    'message' => __('Court not found.')
], 404);
```

#### ❌ Old Pattern (Not Used):
```php
return response()->json([
    'message' => __('messages.court_not_found')
], 404);
```

### Benefits

1. **Readability**: Code is immediately understandable without looking up translation keys
2. **English Fallback**: If a translation is missing, the English message automatically displays
3. **Maintainability**: No need to maintain separate key mappings
4. **Developer Experience**: New developers can read and understand responses instantly

## File Structure

```
lang/
├── en/
│   ├── validation.php      # Laravel validation messages (EN)
│   ├── court_types.php      # Enum translations (EN)
│   └── messages.php         # Can be minimal/empty
├── pt/
│   ├── validation.php      # Laravel validation messages (PT)
│   ├── court_types.php      # Enum translations (PT)
│   └── messages.php         # Can be minimal/empty
└── pt.json                  # JSON translations (English → Portuguese)
```

### JSON Translation Files

Laravel's JSON translation files (`lang/pt.json`, `lang/es.json`, etc.) map English sentences to their translations:

**lang/pt.json**:
```json
{
    "Court not found.": "Quadra não encontrada.",
    "Court deleted successfully.": "Quadra deletada com sucesso.",
    "There was an error updating the court.": "Houve um erro ao actualizar a Quadra."
}
```

## Implementation Examples

### In Controllers

```php
// Error Response
if (!$court) {
    return response()->json([
        'message' => __('Court not found.')
    ], 404);
}

// Success Response
return response()->json([
    'message' => __('Court deleted successfully.')
]);

// With Parameters
return response()->json([
    'message' => __('There was an error creating the court: :error', [
        'error' => $e->getMessage()
    ])
], 400);
```

### In Validation

Laravel's validation messages are handled automatically with `lang/{locale}/validation.php` files.

### For Enums

Enums should include a `label()` method for translations:

```php
enum CourtTypeEnum: string
{
    case FOOTBALL = 'football';
    case BASKETBALL = 'basketball';
    
    public function label(): string
    {
        return __('court_types.' . $this->value);
    }
    
    public static function options(): array
    {
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label()
            ],
            self::cases()
        );
    }
}
```

**lang/pt/court_types.php**:
```php
return [
    'football' => 'Futebol',
    'basketball' => 'Basquetebol',
];
```

## User Locale Management

### Database Schema

Users (both `users` and `business_users` tables) have a `locale` column:

```php
$table->string('locale')->default('pt');
```

### Locale Enum

```php
enum LocaleEnum: string
{
    case EN = 'en';
    case PT = 'pt';
    case ES = 'es';
    case FR = 'fr';

    public function label(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::PT => 'Português',
            self::ES => 'Español',
            self::FR => 'Français',
        };
    }
}
```

### Updating User Locale

Users can update their language preference via API endpoint:

```http
PATCH /api/business/v1/profile/language
Content-Type: application/json

{
    "locale": "en"
}
```

## Middleware: SetLocale

The `SetLocale` middleware automatically sets the application locale for each request:

**File**: `app/Http/Middleware/SetLocale.php`

```php
public function handle(Request $request, Closure $next): Response
{
    $locale = null;
    
    // Priority 1: User's saved locale preference
    $user = $request->user();
    if ($user && !empty($user->locale)) {
        $locale = $user->locale instanceof \App\Enums\LocaleEnum 
            ? $user->locale->value 
            : $user->locale;
    }
    
    // Priority 2: Accept-Language header
    if (!$locale) {
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $locale = substr($acceptLanguage, 0, 2);
        }
    }

    // Fallback to default
    if (!in_array($locale, ['en', 'pt'])) {
        $locale = config('app.locale');
    }

    App::setLocale($locale);
    return $next($request);
}
```

## API Resources

API resources automatically include translated labels for enums:

```php
class CourtTypeResourceGeneral extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'court-type-id'),
            'type' => $this->type,
            'type_label' => $this->type->label(),  // Translated label
            'name' => $this->name,
            // ...
        ];
    }
}
```

**Response Example** (when locale is `pt`):
```json
{
    "id": "abc123",
    "type": "football",
    "type_label": "Futebol",
    "name": "Quadra Principal"
}
```

## Testing

Tests should verify translations work correctly:

```php
public function test_api_response_messages_translation()
{
    // Test English
    $response = $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/courts/invalid-id');
    
    $response->assertJson(['message' => 'Court not found.']);
    
    // Test Portuguese
    $responsePt = $this->withHeaders(['Accept-Language' => 'pt'])
        ->getJson('/api/courts/invalid-id');
    
    $responsePt->assertJson(['message' => 'Quadra não encontrada.']);
}
```

## Adding New Languages

1. **Create JSON translation file**: `lang/{locale}.json`
2. **Add validation messages**: `lang/{locale}/validation.php`
3. **Add enum translations**: `lang/{locale}/court_types.php`
4. **Update LocaleEnum**: Add new case
5. **Update SetLocale middleware**: Add locale to supported list

Example for Spanish:

**lang/es.json**:
```json
{
    "Court not found.": "Cancha no encontrada.",
    "Court deleted successfully.": "Cancha eliminada con éxito."
}
```

## Best Practices

1. **Always use English as keys**: Write messages in clear, proper English
2. **Be consistent**: Use the same phrasing across the API
3. **Use proper punctuation**: Include periods at the end of sentences
4. **Keep messages concise**: Clear and to the point
5. **Use parameters for dynamic content**: `:error`, `:name`, etc.
6. **Log in English**: Internal logs should use English for consistency

## Common Patterns

### Error Messages
```php
__('There was an error :action.', ['action' => 'updating the court'])
```

### Success Messages
```php
__('Court :action successfully.', ['action' => 'deleted'])
```

### Validation Messages
Handled automatically by `lang/{locale}/validation.php`

### Not Found Messages
```php
__(':resource not found.', ['resource' => 'Court'])
```

## Notes

- All API responses should be translatable
- Internal error logs should remain in English
- Field names in validation should be translated via the `attributes` array in `validation.php`
- Ensure proper encoding (UTF-8) for all language files
