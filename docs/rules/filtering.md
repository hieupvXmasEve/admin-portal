---
paths: '**/*.{php,vue,js,ts}'
---

# useDataTable + Laravel Rules

This guide standardizes how list pages use the `useDataTable` composable together with Laravel controllers. Follow it whenever you implement or update a paginated index that needs filters, sorting, and pagination.

> **Reference:** `docs/useDataTable-examples.md` for full composable usage, validation, and dependent filter patterns.
>
> **Legacy:** `useInertiaFilters` (26 pages) and `useServerTableQuery` (9 pages) are frozen — bug fix in-place only, migrate to `useDataTable` when refactoring.

## 1. Decide the Contract First

- **Inventory the filters** (search text, selects, booleans, date ranges, sort, pagination) and write a TypeScript interface that includes them all.
- **Define defaults** for every field that should not pollute the query string (e.g., `status: ''`, `per_page: 15`).
- **Name consistency matters**: use the same filter keys everywhere — TypeScript interface, composable `initialFilters`, Laravel validation, query builder, and Inertia response payload.

## 2. Frontend Pattern (Vue + useDataTable)

### 1. Props + interface

```ts
interface StudentFilters {
    search: string;
    status: string;
    campus_id: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    students: PaginatedResponse<Student>;
    filters?: Partial<StudentFilters>;
    campuses: Campus[];
}>();
```

### 2. Initialize `useDataTable`

```ts
import { useDataTable } from '@/composables/useDataTable';

const {
    state, setFilter, apply, setPage, setPerPage, setSort, clearAllFilters,
    hasActiveFilters, isLoading, currentPage, totalPages, isFirstPage, isLastPage,
} = useDataTable<StudentFilters>({
    baseUrl: route('academic.students.index'),
    initialFilters: {
        search:    props.filters?.search    ?? '',
        status:    props.filters?.status    ?? '',
        campus_id: props.filters?.campus_id ?? '',
        sort:      props.filters?.sort      ?? null,
        direction: props.filters?.direction ?? null,
        per_page:  props.filters?.per_page  ?? 15,
    },
    defaultValues: {
        status:    '',
        campus_id: '',
        per_page:  15,
        direction: null,
    },
    only: ['students', 'filters'],
    debounce: 300,
    // Per-field overrides (optional)
    fieldDebounce: { search: 400 },
    immediateFields: ['status', 'campus_id'],
});
```

- Use `defaultValues` for anything that should disappear from the URL when unchanged.
- Use `fieldDebounce` to give text inputs a longer delay than select inputs.
- Use `immediateFields` for selects/checkboxes that should navigate instantly.

### 3. Bind UI components

- **Text search:** `@update:model-value="setFilter('search', $event)"`
- **Select filters:** `@update:model-value="setFilter('status', $event)"`
- **Sort:** `setSort(field, direction)` — toggles asc/desc
- **Pagination:** `setPage(n)`, `setPerPage(n)`
- **Clear:** show button when `hasActiveFilters`, call `clearAllFilters()`
- **Loading state:** `:disabled="isLoading"` on inputs; show spinner when `isLoading`

### 4. Apply button pattern (manual mode)

For pages with an explicit "Apply" button, call `apply()` instead of relying on debounced `setFilter`:

```ts
// Collect filter changes in local state, then:
const handleApply = () => {
    apply({ search: localSearch.value, status: localStatus.value });
};
```

### 5. Validation (optional)

```ts
const { addValidationRule } = useDataTable({ ... });

addValidationRule('search', {
    validate: (value) => value && value.length < 2 ? 'Minimum 2 characters' : null,
    message: 'Search too short',
});
```

### 6. Dependent filters (optional)

```ts
const { addDependency } = useDataTable({ ... });

addDependency('program_id', {
    dependsOn: ['campus_id'],
    resolve: async ([campusId]) => {
        if (!campusId) return null;
        const res = await fetch(`/api/campuses/${campusId}/programs`);
        const data = await res.json();
        return data[0]?.id ?? null;
    },
});
```

## 3. Backend Pattern (Laravel Controller)

### 1. Validate the request

```php
$validated = $request->validate([
    'search'    => 'nullable|string|max:255',
    'status'    => 'nullable|string|in:active,inactive,graduated',
    'campus_id' => 'nullable|integer|exists:campuses,id',
    'sort'      => 'nullable|string|in:name,student_code,status,created_at',
    'direction' => 'nullable|string|in:asc,desc',
    'per_page'  => 'nullable|integer|min:5|max:100',
]);
```

- Keep validation keys in lock-step with the TypeScript interface.
- Guard sort columns and per-page ranges so malicious values never reach the query.

### 2. Build the query incrementally

```php
$query = Student::query()->forCampus($campusId);

if (!empty($validated['search'])) {
    $query->where(fn($q) => $q
        ->where('full_name', 'like', "%{$validated['search']}%")
        ->orWhere('student_code', 'like', "%{$validated['search']}%")
    );
}

if (!empty($validated['status'])) {
    $query->where('status', $validated['status']);
}

if (!empty($validated['campus_id'])) {
    $query->where('campus_id', $validated['campus_id']);
}

$sortColumn    = $validated['sort']      ?? 'created_at';
$sortDirection = $validated['direction'] ?? 'desc';
$query->orderBy($sortColumn, $sortDirection)->orderBy('id', 'desc');

$perPage  = $validated['per_page'] ?? 15;
$students = $query->paginate($perPage)->withQueryString();
```

- Start from a scoped base query.
- Apply each filter only when the validated value is present.
- Always add a deterministic tiebreaker (`orderBy('id', 'desc')`).

### 3. Return filters back to Inertia

```php
return Inertia::render('Academic/Students/Index', [
    'students' => StudentResource::collection($students),
    'filters'  => [
        'search'    => $validated['search']    ?? null,
        'status'    => $validated['status']    ?? null,
        'campus_id' => $validated['campus_id'] ?? null,
        'sort'      => $validated['sort']      ?? null,
        'direction' => $validated['direction'] ?? null,
        'per_page'  => $validated['per_page']  ?? null,
    ],
    'campuses' => CampusResource::collection(Campus::all()),
]);
```

- Use `null` values so Vue can reapply fallback defaults without polluting URLs.
- Pass supporting lookup data (select options, counts) alongside the list.

### 4. Paginate with `withQueryString()`

Always chain `->withQueryString()` on the paginator so page links preserve the current filter state.

## 4. Implementation Checklist

**Controller**
- [ ] Validate every filter key (type, enum values, integer ranges for sort/per_page)
- [ ] Apply filters conditionally — skip when value is null/empty
- [ ] Return `filters` object + any supporting lookup data
- [ ] Chain `withQueryString()` on paginator

**Vue Page**
- [ ] Declare TypeScript interface for filters
- [ ] Pass normalized values to `useDataTable` with `defaultValues`, `only`, `debounce`
- [ ] Wire UI components to `setFilter`, `setSort`, `setPage`, `setPerPage`, `clearAllFilters`
- [ ] Disable inputs and show loading state via `isLoading`
- [ ] Show clear button only when `hasActiveFilters`

## 5. Tips & Gotchas

- Default select values as `''` (empty string) in `defaultValues` so they disappear from the URL — not `'all'`.
- Use `immediateFields` for selects/checkboxes; text search should always be debounced (300–500ms).
- Keep `only` scoped to the resources and filters that actually change to reduce payload size.
- For numeric inputs (min/max capacity) that shouldn't fire on every keystroke, combine `fieldDebounce` with a longer delay.
- `clearAllFilters()` resets all filters to empty/null and triggers navigation immediately.

Following these rules ensures every new index page behaves consistently, keeps URLs tidy, and stays in sync with Laravel validation and query logic.
