# Filter Patterns

Common configurations for different page types.

## Basic Patterns

### Search + Date Range (4 columns)
```vue
<FilterPanel :has-active-filters="hasActiveFilters" :columns="4" @clear="clearFilters">
    <FilterSearchInput :model-value="filters.search ?? ''" @search="applySearch" />
    <FilterDateRange :from-value="filters.date_from ?? ''" :to-value="filters.date_to ?? ''"
        @change="(from, to) => apply({ date_from: from, date_to: to, page: 1 })" />
</FilterPanel>
```

### Search + Status Select (3 columns)
```vue
<FilterPanel :has-active-filters="hasActiveFilters" :columns="3" @clear="clearFilters">
    <FilterSearchInput :model-value="filters.search ?? ''" @search="applySearch" />
    <FilterSelect :model-value="filters.status ?? 'all'" :options="statusOptions"
        @change="(v) => apply({ status: v === 'all' ? '' : v, page: 1 })" />
</FilterPanel>
```

## useServerTableQuery Config

```typescript
interface Filters {
    search?: string; status?: string; date_from?: string; date_to?: string;
    sort?: string | null; direction?: 'asc' | 'desc' | null; per_page?: number; page?: number;
}

const { filters, hasActiveFilters, clearFilters, apply, applySearch,
        handleSortChange, handlePageChange, handlePageSizeChange,
        currentSort, currentDirection } = useServerTableQuery<Filters>({
    baseUrl: route('resource.index'),
    initialFilters: {
        search: props.filters.search ?? '', status: props.filters.status ?? 'all',
        date_from: props.filters.date_from ?? '', date_to: props.filters.date_to ?? '',
        sort: props.filters.sort ?? 'created_at', direction: props.filters.direction ?? 'desc',
        per_page: props.filters.per_page ?? 15, page: props.filters.page ?? 1,
    },
    emptyFilters: { search: '', status: 'all', date_from: '', date_to: '',
        sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
    defaultValues: { status: 'all', sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
    only: ['items', 'filters'],
});
```

## Backend Controller

```php
public function index(Request $request): Response {
    $validated = $request->validate([
        'search' => ['nullable', 'string', 'max:255'],
        'status' => ['nullable', 'string', 'in:active,inactive,pending'],
        'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        'page' => ['nullable', 'integer', 'min:1'],
        'sort' => ['nullable', 'string', 'max:50'],
        'direction' => ['nullable', 'string', 'in:asc,desc'],
    ]);

    $allowedSorts = ['name', 'created_at', 'status'];
    $sort = in_array($validated['sort'] ?? 'created_at', $allowedSorts, true) ? $validated['sort'] : 'created_at';
    $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

    $items = $this->query->handle($validated, $campusId);

    return Inertia::render('Admin/Resource/Index', [
        'items' => $items,
        'filters' => [
            'search' => $validated['search'] ?? null, 'status' => $validated['status'] ?? null,
            'date_from' => $validated['date_from'] ?? null, 'date_to' => $validated['date_to'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 15), 'page' => (int) ($validated['page'] ?? 1),
            'sort' => $sort, 'direction' => $direction,
        ],
    ]);
}
```

## Grid Column Guidelines

| Filters | Columns | Note |
|---------|---------|------|
| Search only | 2 | |
| Search + select | 3 | |
| Search + date range | 4 | DateRange = 2 cells |
| Search + select + date range | 5 | |
| Search + 2 selects + date range | 6 | |
