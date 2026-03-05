---
name: inertia-filter-table
description: Build reusable filter/table/pagination pages with Inertia.js, Vue 3, and Laravel. Use for admin dashboards, data lists, server-side filtering, sorting, pagination.
version: 1.2.1
---

# Inertia Filter Table Pattern

Build server-side filtered, sorted, paginated tables with Laravel + Vue 3 + Inertia.js.

## Scope

**Handles:** Admin pages with data tables, filters, sorting, pagination.
**Does NOT handle:** Client-side filtering, GraphQL, non-Inertia SPAs.

## Workflow (5 Steps)

### Step 1: Query Class
```php
class ListItemsQuery {
    private const SORTABLE = ['name' => 'items.name', 'created_at' => 'items.created_at'];
    public function handle(array $filters, ?int $campusId): LengthAwarePaginator {
        $sortCol = self::SORTABLE[$filters['sort'] ?? 'created_at'] ?? self::SORTABLE['created_at'];
        $sortDir = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        return Item::query()
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy($sortCol, $sortDir)->paginate((int)($filters['per_page'] ?? 15))->withQueryString();
    }
}
```

### Step 2: Controller
```php
public function index(Request $request): Response {
    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:255'],
        'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        'page' => ['nullable', 'integer', 'min:1'],
        'sort' => ['nullable', 'string', 'max:50'], 'direction' => ['nullable', 'in:asc,desc'],
    ]);
    $allowedSorts = ['name', 'created_at'];
    $sort = in_array($validated['sort'] ?? 'created_at', $allowedSorts, true) ? $validated['sort'] : 'created_at';
    return Inertia::render('Admin/Items/Index', [
        'items' => $this->query->handle($validated, session('current_campus_id')),
        'filters' => ['search' => $validated['search'] ?? null, 'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null, 'per_page' => (int)($validated['per_page'] ?? 15),
            'page' => (int)($validated['page'] ?? 1), 'sort' => $sort,
            'direction' => ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc'],
    ]);
}
```

### Step 3: Frontend Composable
```typescript
const { filters, hasActiveFilters, clearFilters, apply, applySearch, handleSortChange,
        handlePageChange, handlePageSizeChange, currentSort, currentDirection } = useServerTableQuery<Filters>({
    baseUrl: route('items.index'),
    initialFilters: { search: props.filters.search ?? '', date_from: props.filters.date_from ?? '',
        date_to: props.filters.date_to ?? '', sort: props.filters.sort ?? 'created_at',
        direction: props.filters.direction ?? 'desc', per_page: props.filters.per_page ?? 15, page: 1 },
    emptyFilters: { search: '', date_from: '', date_to: '', sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
    defaultValues: { sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
    only: ['items', 'filters'],
});
const handleDateChange = (from: string, to: string) => apply({ date_from: from || '', date_to: to || '', page: 1 });
```

### Step 4: Filter Panel
```vue
<FilterPanel :has-active-filters="hasActiveFilters" :columns="4" @clear="clearFilters">
    <FilterSearchInput :model-value="filters.search ?? ''" placeholder="Search..."
        @update:model-value="(v) => (filters.search = v)" @search="applySearch" />
    <FilterDateRange :from-value="filters.date_from ?? ''" :to-value="filters.date_to ?? ''"
        from-placeholder="From date" to-placeholder="To date" @change="handleDateChange" />
</FilterPanel>
```

### Step 5: Data Table
```vue
<ServerPaginatedDataTable :data="items.data" :columns="columns" :pagination-data="items"
    :initial-sort="currentSort" :initial-direction="currentDirection" item-name="items"
    @sort-change="handleSortChange" @page-change="handlePageChange" @page-size-change="handlePageSizeChange">
    <template #cell-actions="{ row }">
        <Button size="sm" @click="edit(row.original)">Edit</Button>
    </template>
</ServerPaginatedDataTable>
```

## Component Props

### FilterPanel
- `has-active-filters` (boolean) - show clear button
- `columns` (2-6) - grid columns, default 4
- `@clear` event

### FilterSearchInput
- `model-value` (string) - current value
- `placeholder` (string) - default 'Search...'
- `debounce` (number) - default 300ms
- `@update:model-value` + `@search` events

### FilterDateRange
- `from-value`, `to-value` (string)
- `from-placeholder`, `to-placeholder` (string)
- `@change(from, to)` event
- **Note:** Uses 2 grid cells

### ServerPaginatedDataTable
- `data`, `columns`, `pagination-data` (required)
- `initial-sort`, `initial-direction`, `item-name`
- `@sort-change`, `@page-change`, `@page-size-change`
- **Slots:** `#cell-{columnId}="{ row }"`

## Column Definition
```typescript
{ header: 'No', id: 'no', enableSorting: false, cell: ({ row }) => rowNumber }
{ header: 'Name', accessorKey: 'name', enableSorting: true }
{ header: 'Actions', id: 'actions', enableSorting: false, enableHiding: false, cell: 'actions' }
```

## References
- `references/backend-patterns.md` - Query, controller templates
- `references/frontend-patterns.md` - Vue page, columns
- `references/component-templates.md` - Filter components

## Security
- Whitelist sortable columns on backend
- Validate all filter inputs
