# Frontend Integration Guide: API Localization Updates

## Overview

The API has been updated to support multi-language responses with automatic locale detection. This document outlines the changes and how to integrate them in the frontend.

---

## 🌍 Supported Languages

- **English (en)** - Default
- **Portuguese (pt)** - Fully implemented
- **Spanish (es)** - Prepared (needs translations)
- **French (fr)** - Prepared (needs translations)

---

## 📡 How Language Detection Works

The API determines the response language using this priority:

1. **User's Saved Locale** (from database, if user is authenticated)
2. **Accept-Language Header** (from HTTP request)
3. **Default Locale** (`pt` - Portuguese)

### Setting the Language

#### Option 1: Using Accept-Language Header (Recommended)
```typescript
// In your API client/service
const headers = {
  'Authorization': `Bearer ${token}`,
  'Accept-Language': userSelectedLanguage, // 'en', 'pt', 'es', or 'fr'
  'Content-Type': 'application/json'
};
```

#### Option 2: Update User Profile (Persistent)
```typescript
// PATCH /api/business/v1/profile/language
await updateLanguage({ locale: 'en' });
```

---

## 🔄 Breaking Changes

### 1. Court Type Modalities Endpoint

**Endpoint**: `GET /api/business/v1/business/{tenant_id}/court-types/modalities`

#### ❌ Old Response Format:
```json
{
  "data": [
    "football",
    "basketball",
    "tennis",
    "squash",
    "badminton",
    "padel",
    "other"
  ]
}
```

#### ✅ New Response Format:
```json
{
  "data": [
    {
      "value": "football",
      "label": "Futebol"
    },
    {
      "value": "basketball",
      "label": "Basquetebol"
    },
    {
      "value": "tennis",
      "label": "Ténis"
    },
    {
      "value": "squash",
      "label": "Squash"
    },
    {
      "value": "badminton",
      "label": "Badminton"
    },
    {
      "value": "padel",
      "label": "Padel"
    },
    {
      "value": "other",
      "label": "Outro"
    }
  ]
}
```

**What Changed:**
- Each item is now an object with `value` and `label`
- `value` is the enum value (always in English, for API communication)
- `label` is the translated display name (changes based on user's locale)

### 2. Court Type Resources

**Affected Endpoints:**
- `GET /api/business/v1/business/{tenant_id}/court-types`
- `GET /api/business/v1/business/{tenant_id}/court-types/{court_type_id}`

#### New Field: `type_label`

```json
{
  "id": "abc123",
  "type": "football",
  "type_label": "Futebol",  // ← NEW: Translated label
  "name": "Quadra Principal",
  "description": "...",
  "interval_time_minutes": 60,
  "buffer_time_minutes": 10,
  "price_per_interval": 5000,
  "price_formatted": "50.00 AOA",
  "status": true,
  "created_at": "2024-01-15T10:30:00.000000Z"
}
```

---

## 📝 Frontend Migration Guide

### TypeScript Interfaces

#### Update CourtTypeModality Interface

```typescript
// Before
type CourtTypeModality = string;

// After
interface CourtTypeModality {
  value: string;
  label: string;
}
```

#### Update CourtType Interface

```typescript
interface CourtType {
  id: string;
  type: string;
  type_label: string;  // ADD THIS
  name: string;
  description: string;
  interval_time_minutes: number;
  buffer_time_minutes: number;
  price_per_interval: number;
  price_formatted: string;
  status: boolean;
  created_at: string;
  // ... other fields
}
```

### Service Updates

#### court-types.service.ts

```typescript
// Update the return type
export async function getCourtTypeModalities(
  tenantId: string
): Promise<CourtTypeModality[]> {
  const response = await api.get(
    `/business/v1/business/${tenantId}/court-types/modalities`
  );
  return response.data.data;
}
```

### Component Updates

#### Select/Dropdown Components

```tsx
// Before
<Select>
  {modalities.map((modality) => (
    <option key={modality} value={modality}>
      {translateCourtType(modality)} {/* Manual translation */}
    </option>
  ))}
</Select>

// After
<Select>
  {modalities.map((modality) => (
    <option key={modality.value} value={modality.value}>
      {modality.label} {/* Already translated by API */}
    </option>
  ))}
</Select>
```

#### Court Type Display

```tsx
// Before
<div>
  <span>Type: {translateCourtType(courtType.type)}</span>
</div>

// After
<div>
  <span>Type: {courtType.type_label}</span>
</div>
```

### Remove Manual Translation Logic

You can now **remove** any manual court type translation functions from the frontend:

```typescript
// ❌ Remove this
function translateCourtType(type: string): string {
  const translations = {
    football: 'Futebol',
    basketball: 'Basquetebol',
    // ...
  };
  return translations[type] || type;
}

// ✅ Use API-provided labels instead
courtType.type_label  // Already translated
modality.label        // Already translated
```

---

## 🔧 Validation Error Messages

Validation errors are now automatically translated based on the user's locale.

### Example Error Response

**English** (Accept-Language: en):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

**Portuguese** (Accept-Language: pt):
```json
{
  "message": "Os dados fornecidos são inválidos.",
  "errors": {
    "name": ["O campo nome é obrigatório."],
    "email": ["O email deve ser um endereço de email válido."]
  }
}
```

**Frontend Handling:**
```typescript
// No changes needed - just display the error messages as-is
catch (error) {
  if (error.response?.data?.errors) {
    // Errors are already translated
    setFieldError('name', error.response.data.errors.name[0]);
  }
}
```

---

## 🎯 API Response Messages

All API response messages are now translated:

### Success Messages

```typescript
// English
{ "message": "Court deleted successfully." }

// Portuguese
{ "message": "Quadra deletada com sucesso." }
```

### Error Messages

```typescript
// English
{ "message": "Court not found." }

// Portuguese
{ "message": "Quadra não encontrada." }
```

**Frontend Handling:**
```typescript
// Simply display the message - it's already translated
toast.success(response.data.message);
toast.error(error.response.data.message);
```

---

## 🌐 Setting User Language Preference

### Update User Language

```typescript
// services/user.service.ts
export async function updateUserLanguage(locale: 'en' | 'pt' | 'es' | 'fr') {
  const response = await api.patch('/business/v1/profile/language', {
    locale
  });
  return response.data;
}
```

### Example: Language Selector Component

```tsx
import { useState } from 'react';

const LanguageSelector = () => {
  const [locale, setLocale] = useState('pt');

  const handleLanguageChange = async (newLocale: string) => {
    try {
      await updateUserLanguage(newLocale);
      setLocale(newLocale);
      // Optionally reload or refetch data to see new translations
      window.location.reload();
    } catch (error) {
      console.error('Failed to update language', error);
    }
  };

  return (
    <Select value={locale} onChange={(e) => handleLanguageChange(e.target.value)}>
      <option value="en">English</option>
      <option value="pt">Português</option>
      <option value="es">Español</option>
      <option value="fr">Français</option>
    </Select>
  );
};
```

---

## 📋 Checklist for Frontend Updates

- [ ] Update TypeScript interfaces for `CourtTypeModality` and `CourtType`
- [ ] Update all court type select/dropdown components to use `modality.label`
- [ ] Update all court type display components to use `courtType.type_label`
- [ ] Remove manual translation functions for court types
- [ ] Add `Accept-Language` header to API client (if not using user's saved preference)
- [ ] Implement language selector component (optional)
- [ ] Test with different locales (en, pt)
- [ ] Update any hardcoded translations to rely on API responses

---

## 🧪 Testing

### Test Different Locales

```typescript
// Test with English
api.defaults.headers.common['Accept-Language'] = 'en';
const modalitiesEN = await getCourtTypeModalities(tenantId);
// Expect: [{ value: 'football', label: 'Football' }, ...]

// Test with Portuguese
api.defaults.headers.common['Accept-Language'] = 'pt';
const modalitiesPT = await getCourtTypeModalities(tenantId);
// Expect: [{ value: 'football', label: 'Futebol' }, ...]
```

### Test User Preference Override

```typescript
// 1. Set user's language to Portuguese
await updateUserLanguage('pt');

// 2. Make request with English header
api.defaults.headers.common['Accept-Language'] = 'en';
const modalities = await getCourtTypeModalities(tenantId);

// Result: Should return Portuguese labels (user preference overrides header)
// Expect: [{ value: 'football', label: 'Futebol' }, ...]
```

---

## 📚 Additional Resources

- **Backend Documentation**: `docs/backend/api-language-patterns.md`
- **Supported Languages**: EN, PT (ES and FR prepared but need translations)
- **Default Locale**: Portuguese (pt)

---

## 💡 Best Practices

1. **Always send Accept-Language header** for guest/unauthenticated users
2. **Let authenticated users set their language preference** via profile settings
3. **Don't translate in frontend** - let the API handle all translations
4. **Display API messages as-is** - they're already translated
5. **Use `value` for API calls**, `label` for display

---

## ❓ FAQ

**Q: What if I forget to send Accept-Language header?**  
A: The API defaults to Portuguese (pt).

**Q: Can I override the user's saved language preference?**  
A: No, user's saved preference always takes priority over the header.

**Q: Do I need to translate error messages in the frontend?**  
A: No, all error messages come pre-translated from the API.

**Q: What happens if a translation is missing?**  
A: The API automatically falls back to English.

**Q: How do I add support for a new language?**  
A: Contact the backend team to add translations for the new language.

---

## 🚀 Summary

The API now handles all translations automatically based on:
1. User's language preference (database)
2. Accept-Language header
3. Default locale (pt)

**Key takeaways:**
- Court type modalities now return `{ value, label }` objects
- Court types now include a `type_label` field
- All API messages are pre-translated
- Remove manual translation logic from frontend
- Use `value` for API calls, `label` for user display
