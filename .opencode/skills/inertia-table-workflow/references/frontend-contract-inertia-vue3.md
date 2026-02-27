# Frontend Contract (Inertia.js + Vue 3)

Use one composable as orchestration layer; keep UI components presentational.

## 1) Shared Composable API

Create/standardize `useIndexTablePage` with:

- `filters` (reactive)
- `hasActiveFilters`
- `clearFilters()`
- `onSearch(value)`
- `onSelect(key, value, allValue = 'all')`
- `onSortChange(sort, direction)`
- `onPageChange(page)`
- `onPageSizeChange(size)`
- `navigate()` and `buildQuery()`

## 2) Query Serialization Rules

- Exclude `undefined`, `null`, empty string.
- Exclude default values.
- Map sentinel UI values (like `all`) to omitted query params.
- Convert number-like strings consistently when needed.

## 3) Navigation Rules

- Use `router.get(baseUrl, query, options)`.
- Default options:
    - `replace: true`
    - `preserveState: true`
    - `preserveScroll: true`
    - `only: ['items', 'filters']` (resource-specific)
- Debounce search only.
- Trigger immediate navigation for sort/page/per-page.

## 4) DataTable Contract

- Input: `data`, `columns`, `initialSort`, `initialDirection`.
- Output events: `sort-change`, `selection-change`.
- No URL/query construction inside `DataTable`.
- No direct Inertia calls inside `DataTable`.

## 5) DataPagination Contract

- Input: paginator meta (`current_page`, `last_page`, `per_page`, `total`).
- Output events:
    - `page-change(pageNumber)`
    - `page-size-change(pageSize)`
- No `window.location` query string mutation.

## 6) Page Integration Pattern

```ts
const list = useIndexTablePage({
    baseUrl: '/units',
    only: ['units', 'filters'],
    defaults: { page: 1, per_page: 15, direction: 'asc' },
    initialFilters: props.filters,
});
```

```vue
<DataTable :data="rows" :columns="columns" :initial-sort="list.currentSort" :initial-direction="list.currentDirection" @sort-change="list.onSortChange" />

<DataPagination :pagination-data="units" @page-change="list.onPageChange" @page-size-change="list.onPageSizeChange" />
```

## 7) UX Consistency Rules

- Clear filters returns exact default state.
- Changing any filter except `page` resets to page 1.
- Keep URL shareable/bookmarkable.
- Keep table state aligned after back/forward navigation.

## 8) Date Filter Contract (DatePicker)

- Use `DatePicker` for `issued_from` / `issued_to` when using two independent date fields.
- Apply filter immediately when date changes; do not require Apply button for date-only updates.
- Implement clear date action inside `DatePicker` component popover (`Clear date`).
- Clearing date must emit empty string and trigger filter navigation.
- Keep search debounce independent from date filter apply.

Example pattern:

```vue
<ServerDateRangeFilters :model-value="filterForm" @update:model-value="updateFilterForm" @search="handleSearchInput" @date-change="handleDateChange" />
```

```ts
const handleDateChange = ({ issued_from, issued_to }: { issued_from: string; issued_to: string }) => {
    apply({ issued_from: issued_from || '', issued_to: issued_to || '', page: 1 });
};
```
