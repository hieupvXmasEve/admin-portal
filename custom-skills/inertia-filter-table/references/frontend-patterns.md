# Frontend Patterns

Vue 3 + Inertia.js patterns for filter/table pages.

## Page Component Structure

```vue
<script setup lang="ts">
import FilterDateRange from '@/components/filters/FilterDateRange.vue';
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import type { PaginatedResponse } from '@/types';
import { Head } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';

interface ItemFilters {
    search?: string; date_from?: string; date_to?: string;
    sort?: string | null; direction?: 'asc' | 'desc' | null;
    per_page?: number; page?: number;
}

const props = defineProps<{ items: PaginatedResponse<Item>; filters: ItemFilters }>();

const { filters, hasActiveFilters, clearFilters, apply, applySearch,
        handleSortChange, handlePageChange, handlePageSizeChange,
        currentSort, currentDirection } = useServerTableQuery<ItemFilters>({
    baseUrl: route('items.index'),
    initialFilters: {
        search: props.filters.search ?? '', date_from: props.filters.date_from ?? '',
        date_to: props.filters.date_to ?? '', sort: props.filters.sort ?? 'created_at',
        direction: props.filters.direction ?? 'desc', per_page: props.filters.per_page ?? 15, page: 1,
    },
    emptyFilters: { search: '', date_from: '', date_to: '', sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
    defaultValues: { sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
    only: ['items', 'filters'],
});

const handleDateChange = (from: string, to: string) => apply({ date_from: from || '', date_to: to || '', page: 1 });

const columns: ColumnDef<Item>[] = [
    { header: 'No', id: 'no', enableSorting: false,
      cell: ({ row }) => (props.items.current_page - 1) * props.items.per_page + row.index + 1 },
    { header: 'Name', accessorKey: 'name', enableSorting: true },
    { header: 'Created', accessorKey: 'created_at', enableSorting: true,
      cell: ({ row }) => formatDate(row.original.created_at) },
    { header: 'Actions', id: 'actions', enableSorting: false, enableHiding: false, cell: 'actions' },
];
</script>

<template>
    <Head title="Items" />
    <div class="space-y-6">
        <Card>
            <CardHeader><CardTitle>Filter</CardTitle></CardHeader>
            <CardContent>
                <FilterPanel :has-active-filters="hasActiveFilters" :columns="4" @clear="clearFilters">
                    <FilterSearchInput :model-value="filters.search ?? ''" placeholder="Search..."
                        @update:model-value="(v) => (filters.search = v)" @search="applySearch" />
                    <FilterDateRange :from-value="filters.date_from ?? ''" :to-value="filters.date_to ?? ''"
                        from-placeholder="From date" to-placeholder="To date" @change="handleDateChange" />
                </FilterPanel>
            </CardContent>
        </Card>
        <Card>
            <CardContent>
                <ServerPaginatedDataTable :data="items.data" :columns="columns" :pagination-data="items"
                    :initial-sort="currentSort" :initial-direction="currentDirection" item-name="items"
                    @sort-change="handleSortChange" @page-change="handlePageChange" @page-size-change="handlePageSizeChange">
                    <template #cell-actions="{ row }">
                        <Button size="sm" @click="edit(row.original)">Edit</Button>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>
    </div>
</template>
```

## useServerTableQuery Options

| Option | Type | Description |
|--------|------|-------------|
| baseUrl | string | Route URL for Inertia visit |
| initialFilters | T | Initial filter values from props |
| emptyFilters | T | Values when clearing filters |
| defaultValues | Partial<T> | Values excluded from URL params |
| only | string[] | Inertia partial reload keys |

## Filter Component Grid

| Filters | Columns | Layout |
|---------|---------|--------|
| Search only | 2 | search + clear |
| Search + select | 3 | search + select + clear |
| Search + date range | 4 | search + from + to + clear |

Note: `FilterDateRange` uses 2 grid cells.

## Column Definition Patterns

```typescript
// Row number
{ header: 'No', id: 'no', enableSorting: false,
  cell: ({ row }) => (props.items.current_page - 1) * props.items.per_page + row.index + 1 }

// Basic sortable
{ header: 'Name', accessorKey: 'name', enableSorting: true }

// Custom render
{ header: 'Status', accessorKey: 'status', enableSorting: true,
  cell: ({ row }) => h('span', { class: 'badge' }, row.original.status) }

// Slot-based (define in template #cell-{id})
{ header: 'Actions', id: 'actions', enableSorting: false, enableHiding: false, cell: 'actions' }
```

## Slot Pattern

```vue
<ServerPaginatedDataTable ...>
    <template #cell-file="{ row }">
        <a v-if="row.original.file_url" :href="row.original.file_url">Download</a>
    </template>
    <template #cell-actions="{ row }">
        <Button size="sm" @click="edit(row.original)">Edit</Button>
    </template>
</ServerPaginatedDataTable>
```
