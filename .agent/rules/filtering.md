---
trigger: manual
---

# Filtering & Pagination Rules (Inertia + Laravel)

## 1. Core Principles

- **Contract First**: Define filter keys, types, and defaults before coding.
- **Name Consistency**: Use identical keys in Vue Props, Composable, Laravel Validation, and Response.
- **Object Stability**: Backend MUST return an object `{}` for filters, never an empty array `[]` (prevents JS prototype collisions).

## 2. Frontend Pattern (Vue 3)

### Interface & Props

```ts
interface EntityFilters {
    search: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}
```

### Initialization (Defensive)

```ts
const { filters, ... } = useInertiaFilters<EntityFilters>({
    baseUrl: route('admin.entities.index'),
    initialFilters: {
        // Defensive checks against backend returning wrong types
        search: (typeof props.filters?.search === 'string' ? props.filters.search : '') || '',
        sort: (typeof props.filters?.sort === 'string' ? props.filters.sort : 'created_at') || 'created_at',
        // ...
    },
    defaultValues: {
        status: 'all',
        per_page: 15,
    },
    only: ['items', 'filters'], // Mandatory for partial reloads
});
```

## 3. Backend Pattern (Laravel)

### Controller

- **Validation**: Validate all filter parameters.
- **Response**: Return explicit keys.
- **Prohibited**: Do NOT use `$request->only()` or `$request->all()` for the `filters` prop.

```php
return Inertia::render('Admin/Index', [
    'items' => $items,
    'filters' => [
        'search' => $validated['search'] ?? null,
        'status' => $validated['status'] ?? null,
        // Explicit null ensures JSON object structure
    ],
]);
```

## 4. Implementation Checklist

- [ ] Are default values defined in `defaultValues` (to keep URLs clean)?
- [ ] Does the Backend return an explicit array for `filters`?
- [ ] Is `only: ['items', 'filters']` set to prevent full page reloads?
- [ ] Are UI components bound to the composable handlers (`handleSearch`, `handleSortChange`)?