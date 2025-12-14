---
trigger: manual
---

# useInertiaFilters + Laravel Rules

This guide standardizes how list pages use the `useInertiaFilters` composable together with Laravel controllers. Follow it whenever you implement or update a paginated index that needs filters, sorting, and pagination. Reference examples in `docs/EXAMPLE_useInertiaFilters.md`, `resources/js/pages/rooms/Index.vue`, and `app/Http/Controllers/Web/RoomController.php`.

## 1. Decide the Contract First

- **Inventory the filters** (search text, selects, booleans, numeric ranges, sort, pagination) and write a TypeScript interface that includes them all.
- **Define defaults** for every field that should not pollute the query string (e.g., `status: 'all'`, `per_page: 15`, `direction: 'asc'`).
- **Name consistency matters**: use the same filter keys everywhere (Vue props, composable, Laravel validation, query builder, and response payload).

## 2. Frontend Pattern (Vue + Inertia)

1. **Props + interface**

    ```ts
    interface RoomFilters {
        search: string;
        type: string;
        status: string;
        building_id: string;
        floor: string;
        min_capacity: string;
        max_capacity: string;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
        per_page: number;
    }

    const props = defineProps<{
        rooms: PaginatedResponse<Room>;
        filters?: Partial<RoomFilters>;
    }>();
    ```

2. **Initialize `useInertiaFilters`**

    ```ts
    const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange } = useInertiaFilters<RoomFilters>({
        baseUrl: systemRoutes.rooms.index(),
        initialFilters: {
            search: props.filters?.search || '',
            type: props.filters?.type || 'all',
            // ...
            min_capacity: props.filters?.min_capacity?.toString() || '',
            sort: props.filters?.sort || null,
            direction: (props.filters?.direction as 'asc' | 'desc') || null,
            per_page: props.filters?.per_page || 15,
        },
        defaultValues: {
            type: 'all',
            status: 'all',
            building_id: 'all',
            floor: 'all',
            per_page: 15,
            direction: 'asc',
        },
        only: ['rooms', 'filters'],
        debounce: 400,
        transform: (filters) => ({
            ...filters,
            min_capacity: filters.min_capacity ? Number(filters.min_capacity) : undefined,
            max_capacity: filters.max_capacity ? Number(filters.max_capacity) : undefined,
        }),
    });
    ```
    - Always return strings for select inputs and convert them inside `transform`.
    - Use `defaultValues` for anything that should disappear from the URL when unchanged.

3. **Bind UI components**
    - `DebouncedInput`: `:model-value="filters.search"` + `@update:model-value="handleSearch"`.
    - Shadcn Selects: bind `v-model` to `filters.field` and call `handleSelectFilter('field', value)` when you need the "All" semantics.
    - `DataTable`: pass `:initial-sort="filters.sort"` and `@sort-change="handleSortChange"`.
    - `DataPagination`: pass the paginated response and hook `@navigate` + `@page-size-change` to the composable handlers.
    - When numeric inputs should not sync on every keystroke, copy the `rooms/Index.vue` pattern: keep the values in `filters`, but debounce manual updates with `useDebounceFn`.

4. **Clearing filters**
    - Show a clear button only when `hasActiveFilters` is true.
    - `clearFilters()` resets to `defaultValues` (or `emptyFilters` if you provide one) and triggers a navigation.

5. **Manual mode**
    - Set `autoSync: false` if you need an explicit Apply button. Use `applyFilters()` from the composable when the user submits.

## 3. Backend Pattern (Laravel Controller)

1. **Validate the request**

    ```php
    $validated = $request->validate([
        'search' => 'nullable|string|max:255',
        'type' => 'nullable|string|in:' . implode(',', Room::getTypes()),
        // ...other filters
        'min_capacity' => 'nullable|integer|min:1',
        'max_capacity' => 'nullable|integer|min:1',
        'sort' => 'nullable|string|in:name,building_id,type,capacity,status,created_at',
        'direction' => 'nullable|string|in:asc,desc',
        'per_page' => 'nullable|integer|min:5|max:100',
    ]);
    ```
    - Keep validation keys in lock-step with the TS interface.
    - Guard sort columns and per-page ranges so malicious values never reach the query.

2. **Build the query incrementally**
    - Start from a scoped base query (`Room::query()->forCampus(...)`).
    - Apply each filter only when the validated value exists. The `rooms` controller demonstrates handling search, enums, foreign keys, booleans, numeric ranges, and sort direction safely.
    - Always add deterministic fallbacks, e.g., `orderBy('created_at', 'desc')` after custom sorts.

3. **Return filters back to Inertia**

    ```php
    return Inertia::render('rooms/Index', [
        'rooms' => $rooms,
        'filters' => [
            'search' => $validated['search'] ?? null,
            'type' => $validated['type'] ?? null,
            // ...
            'min_capacity' => $validated['min_capacity'] ?? null,
            'max_capacity' => $validated['max_capacity'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
        ],
        // ...
    ]);
    ```
    - Use `null` values so Vue can reapply fallback defaults without polluting URLs.
    - If you compute extra filter metadata (options for selects, counts, etc.), pass them next to the list data like `room_types`, `buildings`, and `statistics`.

4. **Paginate with `withQueryString()`**
    - Inertia pagination expects the backend paginator to keep the same query params when navigating.
    - Enforce integer per-page values from the validated payload.

## 4. Implementation Checklist

1. **Controller**
    - Add validation rules for every filter key.
    - Update the query builder with conditional clauses.
    - Return the `filters` object plus any supporting lookup data.
2. **Vue Page**
    - Declare the TypeScript interface and props.
    - Pass normalized values to `useInertiaFilters` with `defaultValues`, `only`, and `debounce`.
    - Wire UI components to the composable handlers.
    - Use `transform` for any field that must arrive as number/boolean on the server.
3. **Testing**
    - Manually verify query strings drop default values (e.g., no `?status=all`).
    - Trigger search, select filters, sorting, pagination, and ensure the backend receives the converted types.

## 5. Tips & Gotchas

- Prefer storing select defaults as `'all'` in the UI and convert them to `null`/`undefined` inside `transform` so the controller receives `null`.
- When adding checkbox filters (booleans) use `filters.is_bookable = value` and let Laravel validation handle `nullable|boolean`.
- Keep `only` scoped to the resources and filters that actually change to reduce payload sizes (e.g., `['rooms', 'filters']`).
- If two filters are related (min/max), debounce manual assignments to avoid extra visits (`rooms/Index.vue` shows this with capacity).
- Reuse helper functions (routes, schema options) so option lists in Vue stay in sync with backend enums.

Following these rules ensures every new index page behaves the same way, keeps URLs tidy, and stays in sync with Laravel validation and query logic.
