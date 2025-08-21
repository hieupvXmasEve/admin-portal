<script setup lang="ts" generic="TData">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn, valueUpdater } from '@/lib/utils';
import type { ColumnDef, ExpandedState, SortingState, VisibilityState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getExpandedRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DataTableProps<TData> {
    data: TData[];
    columns: ColumnDef<TData>[];
    showColumnToggle?: boolean;
    emptyMessage?: string;
    loading?: boolean;
    enableRowSelection?: boolean;
}

const props = withDefaults(defineProps<DataTableProps<TData>>(), {
    showColumnToggle: true,
    emptyMessage: 'No results found.',
    loading: false,
    enableRowSelection: false,
});

// Emits for selection events
const emit = defineEmits<{
    'selection-change': [selectedRows: TData[]];
    'select-all': [isSelected: boolean];
}>();

// Table state
const sorting = ref<SortingState>([]);
const columnVisibility = ref<VisibilityState>({});
const rowSelection = ref({});
const expanded = ref<ExpandedState>({});

// Table instance
const table = useVueTable({
    get data() {
        return props.data;
    },
    columns: props.columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getExpandedRowModel: getExpandedRowModel(),
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
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
    },
    enableRowSelection: props.enableRowSelection,
    manualFiltering: true,
    manualPagination: true,
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
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :data-pinned="header.column.getIsPinned()"
                            :class="cn({ 'bg-background/95 sticky': header.column.getIsPinned() }, header.column.getIsPinned() === 'left' ? 'left-0' : 'right-0')"
                        >
                            <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="!loading && table.getRowModel().rows?.length">
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

                    <!-- Loading State -->
                    <TableRow v-else-if="loading">
                        <TableCell :colspan="columns.length" class="h-24 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <div class="border-primary h-4 w-4 animate-spin rounded-full border-2 border-t-transparent"></div>
                                <span>Loading...</span>
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
