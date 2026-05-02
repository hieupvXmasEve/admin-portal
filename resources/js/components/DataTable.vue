<script setup lang="ts" generic="TData">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn, valueUpdater } from '@/lib/utils';
import type { ColumnDef, ExpandedState, SortingState, VisibilityState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getExpandedRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface DataTableProps<TData> {
    data: TData[];
    columns: ColumnDef<TData>[];
    showColumnToggle?: boolean;
    emptyMessage?: string;
    loading?: boolean;
    enableRowSelection?: boolean;
    enableServerSorting?: boolean;
    initialSort?: string;
    initialDirection?: 'asc' | 'desc';
}

const props = withDefaults(defineProps<DataTableProps<TData>>(), {
    showColumnToggle: true,
    emptyMessage: 'No results found.',
    loading: false,
    enableRowSelection: false,
    enableServerSorting: true,
});

// Emits for selection and sorting events
const emit = defineEmits<{
    'selection-change': [selectedRows: TData[]];
    'select-all': [isSelected: boolean];
    'sort-change': [sort: string | null, direction: 'asc' | 'desc' | null];
}>();

// Table state
const sorting = ref<SortingState>(props.initialSort ? [{ id: props.initialSort, desc: (props.initialDirection || 'asc') === 'desc' }] : []);
const columnVisibility = ref<VisibilityState>({});
const rowSelection = ref({});
const expanded = ref<ExpandedState>({});
const columnPinning = ref({ right: ['actions'] });

// Watch for prop changes and update sorting state
watch(
    () => [props.initialSort, props.initialDirection] as const,
    ([newSort, newDirection]) => {
        // Update sorting state when props change
        if (newSort) {
            // Default to 'asc' if direction is missing
            const direction = newDirection || 'asc';

            // Only update if different from current state
            const currentSort = sorting.value[0];
            const needsUpdate = !currentSort || currentSort.id !== newSort || (currentSort.desc ? 'desc' : 'asc') !== direction;

            if (needsUpdate) {
                sorting.value = [{ id: newSort, desc: direction === 'desc' }];
            }
        } else {
            // Clear sorting when no sort prop
            if (sorting.value.length > 0) {
                sorting.value = [];
            }
        }
    },
    { immediate: false }, // Don't run on mount (already initialized above)
);

// Handle sorting change
const handleSortingChange = (updaterOrValue: any) => {
    valueUpdater(updaterOrValue, sorting);

    // Emit sort change for server-side sorting
    if (props.enableServerSorting) {
        const currentSort = sorting.value[0];
        if (currentSort) {
            emit('sort-change', currentSort.id, currentSort.desc ? 'desc' : 'asc');
        } else {
            emit('sort-change', null, null);
        }
    }
};

// Table instance
const table = useVueTable({
    get data() {
        return props.data;
    },
    columns: props.columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getExpandedRowModel: getExpandedRowModel(),
    onSortingChange: handleSortingChange,
    onColumnVisibilityChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnVisibility),
    onRowSelectionChange: (updaterOrValue) => {
        valueUpdater(updaterOrValue, rowSelection);
        // Emit selection change event
        const selectedRows = table.getFilteredSelectedRowModel().rows.map((row) => row.original);
        emit('selection-change', selectedRows);
    },
    onExpandedChange: (updaterOrValue) => valueUpdater(updaterOrValue, expanded),
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnVisibility() {
            return columnVisibility.value;
        },
        get rowSelection() {
            return rowSelection.value;
        },
        get expanded() {
            return expanded.value;
        },
        get columnPinning() {
            return columnPinning.value;
        },
    },
    enableRowSelection: props.enableRowSelection,
    manualFiltering: true,
    manualPagination: true,
    manualSorting: props.enableServerSorting,
});

// Computed properties for selection info
const selectedRowsCount = computed(() => table.getFilteredSelectedRowModel().rows.length);
const totalRowsCount = computed(() => table.getFilteredRowModel().rows.length);
const isAllSelected = computed(() => table.getIsAllPageRowsSelected());
const isSomeSelected = computed(() => table.getIsSomePageRowsSelected());

// Expose table instance and selection utilities for parent components
defineExpose({
    table,
    selectedRowsCount,
    totalRowsCount,
    isAllSelected,
    isSomeSelected,
    getSelectedRows: () => table.getFilteredSelectedRowModel().rows.map((row) => row.original),
    clearSelection: () => table.resetRowSelection(),
});
</script>

<template>
    <div class="space-y-4">
        <!-- Selection Info and Column Toggle -->
        <div class="flex items-center justify-between">
            <!-- Selection Info -->
            <div v-if="enableRowSelection && selectedRowsCount > 0" class="text-muted-foreground flex-1 text-sm">{{ selectedRowsCount }} of {{ totalRowsCount }} row(s) selected.</div>

            <!-- Column Toggle -->
            <div v-if="showColumnToggle" class="flex justify-end" :class="{ 'ml-auto': !enableRowSelection || selectedRowsCount === 0 }">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            Columns
                            <ChevronDown class="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuCheckboxItem
                            v-for="column in table.getAllColumns().filter((column) => column.getCanHide())"
                            :key="column.id"
                            class="capitalize"
                            :checked="column.getIsVisible()"
                            :model-value="column.getIsVisible()"
                            @update:model-value="(value) => column.toggleVisibility(!!value)"
                            @select.prevent
                        >
                            {{ column.id }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Table -->
        <div class="relative rounded-md border">
            <!-- Loading overlay — keeps existing data visible -->
            <div v-if="loading && table.getRowModel().rows?.length" class="bg-background/60 absolute inset-0 z-10 flex items-center justify-center rounded-md">
                <div class="flex items-center space-x-2">
                    <div class="border-primary h-5 w-5 animate-spin rounded-full border-2 border-t-transparent"></div>
                    <span class="text-muted-foreground text-sm">Loading...</span>
                </div>
            </div>
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :data-pinned="header.column.getIsPinned()"
                            :class="cn({ 'bg-background/95 sticky': header.column.getIsPinned() }, header.column.getIsPinned() === 'left' ? 'left-0' : 'right-0')"
                        >
                            <div
                                v-if="!header.isPlaceholder"
                                :class="cn('flex items-center', header.column.getCanSort() ? 'cursor-pointer select-none' : '')"
                                @click="header.column.getCanSort() ? header.column.getToggleSortingHandler()?.($event) : undefined"
                            >
                                <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                                <template v-if="header.column.getCanSort()">
                                    <ArrowUp v-if="header.column.getIsSorted() === 'asc'" class="ml-2 h-4 w-4" />
                                    <ArrowDown v-else-if="header.column.getIsSorted() === 'desc'" class="ml-2 h-4 w-4" />
                                    <ArrowUpDown v-else class="ml-2 h-4 w-4 opacity-50" />
                                </template>
                            </div>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <template v-for="row in table.getRowModel().rows" :key="row.id">
                            <TableRow :data-state="row.getIsSelected() && 'selected'">
                                <TableCell
                                    v-for="cell in row.getVisibleCells()"
                                    :key="cell.id"
                                    :data-pinned="cell.column.getIsPinned()"
                                    :class="cn({ 'bg-background/95 sticky': cell.column.getIsPinned() }, cell.column.getIsPinned() === 'left' ? 'left-0' : 'right-0')"
                                >
                                    <slot :name="`cell-${cell.column.id}`" :cell="cell" :row="row" :value="cell.getValue()">
                                        <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                    </slot>
                                </TableCell>
                            </TableRow>
                        </template>
                    </template>

                    <!-- Loading State (no existing data) -->
                    <TableRow v-else-if="loading">
                        <TableCell :colspan="columns.length" class="h-24 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <div class="border-primary h-5 w-5 animate-spin rounded-full border-2 border-t-transparent"></div>
                                <span class="text-muted-foreground text-sm">Loading...</span>
                            </div>
                        </TableCell>
                    </TableRow>

                    <!-- Empty State -->
                    <TableRow v-else>
                        <TableCell :colspan="columns.length" class="h-24 text-center">
                            {{ emptyMessage }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>

<style scoped>
[data-pinned='right'] {
    @apply shadow-[-4px_0_8px_-2px_rgba(0,0,0,0.1)];
}

[data-pinned='left'] {
    @apply shadow-[4px_0_8px_-2px_rgba(0,0,0,0.1)];
}
</style>
