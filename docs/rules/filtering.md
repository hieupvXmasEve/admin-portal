---
title: Filtering, Sorting, and Pagination Rules
status: active
owner: Frontend Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - "**/*.php"
  - "**/*.vue"
  - "**/*.ts"
---

# Filtering, Sorting, and Pagination Rules

`useDataTable` is the only approved composable for server-filtered, sorted, or
paginated list pages. Do not add or extend `useInertiaFilters`,
`useServerTableQuery`, `useFilters`, or page-local navigation glue. When a list
page is materially edited, migrate its legacy filtering as part of that change;
a strictly isolated one-line fix may remain unchanged.

The executable frontend contract is
`resources/js/composables/useDataTable.ts`. Shared filter components live in
`resources/js/components/filters/`.

## Define one filter contract

Use the same snake_case keys in:

- the TypeScript filter interface;
- Inertia `filters` props;
- `initialFilters` and `defaultValues`;
- the Laravel FormRequest;
- the Query or query builder;
- the response and query string.

Whitelist sortable fields and directions. Define defaults for values that
should disappear from the URL, including `per_page`, empty selects, and null
sort state.

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
}>();
```

## Initialize `useDataTable`

```ts
const {
    state,
    setFilter,
    apply,
    setPage,
    setPerPage,
    setSort,
    clearAllFilters,
    hasActiveFilters,
    isLoading,
    currentSort,
    currentDirection,
} = useDataTable<StudentFilters>({
    baseUrl: route('academic.students.index'),
    initialFilters: {
        search: props.filters?.search ?? '',
        status: props.filters?.status ?? '',
        campus_id: props.filters?.campus_id ?? '',
        sort: typeof props.filters?.sort === 'string'
            ? props.filters.sort
            : null,
        direction: props.filters?.direction ?? null,
        per_page: props.filters?.per_page ?? 15,
    },
    defaultValues: {
        status: '',
        campus_id: '',
        sort: null,
        direction: null,
        per_page: 15,
    },
    only: ['students', 'filters'],
    debounce: 300,
    fieldDebounce: { search: 400 },
    immediateFields: ['status', 'campus_id'],
});
```

- Use `setFilter()` for normal input updates.
- Use `apply()` for an explicit Apply button or immediate navigation.
- Use `setSort()`, `setPage()`, and `setPerPage()` rather than mutating URL
  state directly.
- Show a clear action only when `hasActiveFilters` and call
  `clearAllFilters()`.
- Use the composable's loading and current-sort state in the template.
- Use named routes for `baseUrl`.

Optional validation and dependent-filter APIs may be used when the executable
composable supports the requirement. Do not fetch dependent options through
raw `fetch` or Axios; use the project API wrapper and named routes.

## PHP empty-array guard

An empty PHP filter array serializes to a JavaScript array. On an array,
`filters.sort` is the native `Array.prototype.sort` function and is truthy.
Always guard the server sort prop:

```ts
sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null
```

Template components receive `currentSort` and `currentDirection` from
`useDataTable`, not an unguarded `filters.sort`.

## Shared filter UI

Prefer the existing primitives in `resources/js/components/filters/`:

- `FilterPanel.vue` for layout and clear behavior;
- `FilterSearchInput.vue` for debounced text;
- `FilterSelect.vue` for enums and related keys;
- `FilterDateRange.vue` for date boundaries.

Select options use non-empty string values. Convert numeric IDs with
`String(id)` and use a sentinel such as `all` for a visible reset option.

## Backend contract

Use a FormRequest to validate the complete filter shape:

- nullable search and select fields;
- existing related IDs scoped as required;
- allowed sort fields and `asc`/`desc`;
- bounded integer `per_page`;
- well-formed dates and coherent ranges.

Apply filters incrementally in an owning Query class or focused query builder.
Keep controllers thin. Sort only by a server whitelist and always provide a
stable fallback order.

Return the sanitized filters and paginator through Inertia:

```php
return Inertia::render('Academic/Students/Index', [
    'students' => $query->handle($filters)->withQueryString(),
    'filters' => (object) $filters,
]);
```

Casting an empty filter payload to an object prevents the PHP-array
serialization trap. Use `withQueryString()` so pagination retains active
filters. Restrict partial reloads with the composable's `only` list.

## Review checklist

- One filter interface and one set of keys are used end-to-end.
- Validation, sort whitelist, defaults, and maximum page size are explicit.
- `useDataTable` owns navigation; no legacy composable or raw URL glue remains.
- Sort props use the `typeof` guard and empty backend filters serialize as an
  object.
- Pagination retains query parameters.
- Loading, empty, error, clear, and selection states use shared UI patterns.
- Targeted tests cover defaults, filters, invalid input, sorting, pagination,
  and empty data as applicable.
