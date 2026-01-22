# useInertiaFilters + Laravel Rules

This guide standardizes how list pages use the `useInertiaFilters` composable together with Laravel controllers. Follow it whenever you implement or update a paginated index that needs filters, sorting, and pagination.

## 1. Decide the Contract First

- **Inventory the filters**: Identify all filterable fields (search text, select dropdowns, booleans, numeric ranges, sorting, and pagination).
- **Define TypeScript interface**: Create a clear interface for the filters.
- **Define defaults**: Set default values for every field (e.g., `status: 'all'`, `per_page: 15`). Defaults should not pollute the query string.
- **Name consistency**: Use identical keys across Vue props, the composable, Laravel validation/query builder, and response payloads.

## 2. Frontend Pattern (Vue + Inertia)

### 2.1. Props & Interface

```ts
interface EntityFilters {
    search: string;
    category: string;
    status: string;
    // ...other filters
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    items: PaginatedResponse<EntityModel>;
    filters?: Partial<EntityFilters>;
}>();
```

### 2.2. Initialize useInertiaFilters

```ts
const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useInertiaFilters<EntityFilters>({
    baseUrl: route('admin.entities.index'), // Use Ziggy route helper
    initialFilters: {
        // DEFENSIVE: Always check type string to avoid picking up JS prototype methods (like .sort)
        // if the backend accidentally sends an empty array [] instead of object {}.
        search: (typeof props.filters?.search === 'string' ? props.filters.search : '') || '',
        category: (typeof props.filters?.category === 'string' ? props.filters.category : 'all') || 'all',
        sort: (typeof props.filters?.sort === 'string' ? props.filters.sort : 'created_at') || 'created_at',
        direction: (props.filters?.direction as 'asc' | 'desc') || 'desc',
        per_page: props.filters?.per_page || 15,
        page: props.items.current_page || 1,
    },
    defaultValues: {
        category: 'all',
        status: 'all',
        per_page: 15,
        direction: 'desc',
        sort: 'created_at',
    },
    only: ['items', 'filters'], // Partial reload keys
    debounce: 400,
});
```

### 2.3. Bind UI Components

- **Search**: `<DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" />`
- **Selects**: Use `handleSelectFilter('field_name', value)` to handle the "all" value correctly.
- **DataTable**:
    ```vue
    <DataTable :columns="columns" :data="items.data" :initial-sort="currentSort" :initial-direction="currentDirection" enable-server-sorting @sort-change="handleSortChange" />
    ```
- **Pagination**:
    ```vue
    <DataPagination :pagination-data="items" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    ```

### 2.4. DataTable + DataPagination Rules

1.  **Server Sorting**: Always enable `enable-server-sorting` when using `useInertiaFilters`.
2.  **Sort State Source**: Use `currentSort` and `currentDirection` from the composable as the table source of truth.
3.  **Pagination Events**: Route pagination through `handlePaginationNavigate` and `handlePageSizeChange`.
4.  **Data Contract**: Pass `items.data` to `DataTable` and the full paginator object to `DataPagination`.
5.  **Consistent Keys**: Match `sort`, `direction`, `page`, and `per_page` with backend validation and filters payload.

## 3. Backend Pattern (Laravel Controller)

### 3.1. Validation & Querying

```php
public function index(Request $request): Response
{
    $validated = $request->validate([
        'search' => 'nullable|string|max:255',
        'category' => 'nullable|string',
        'sort' => 'nullable|string|in:id,title,created_at',
        'direction' => 'nullable|string|in:asc,desc',
        'per_page' => 'nullable|integer|min:5|max:100',
    ]);

    $items = Entity::query()
        ->when($validated['search'] ?? null, fn($q, $s) => $q->search($s))
        ->when($validated['category'] ?? null, fn($q, $c) => $q->where('category', $c))
        ->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
        ->paginate($validated['per_page'] ?? 15)
        ->withQueryString();

    return Inertia::render('Admin/Entities/Index', [
        'items' => $items,
        'filters' => [
            // EXPLICIT DEFINITION: Ensure results is ALWAYS a JSON object {},
            // NEVER use $request->only() which can return an empty array [].
            'search' => $validated['search'] ?? null,
            'category' => $validated['category'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'per_page' => $validated['per_page'] ?? null,
        ],
    ]);
}
```

## 4. Key Rules & Implementation Checklist

1.  **Object Stability**: The `filters` prop from Laravel MUST be an object. If it's an empty array `[]`, JS `Array.prototype.sort` will collide with `filters.sort`.
2.  **Defensive Initialization**: In Vue, always default props.filters values with type checks (`typeof === 'string'`).
3.  **Explicit Keys**: Always define explicit keys in the controller's `filters` response. Do not use `$request->only()` or `$request->all()`.
4.  **Clean URLs**: Use `defaultValues` in `useInertiaFilters` to keep default parameters out of the query string.
5.  **Partial Reloads**: Always define the `only` property to ensure Inertia only refreshes the list data and filters.

Following these rules ensures consistent behavior across all listing pages, prevents common JavaScript naming collisions, and maintains clean, readable URLs.
