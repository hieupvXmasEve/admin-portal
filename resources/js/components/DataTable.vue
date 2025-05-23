<script setup lang="ts" generic="TData">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn, valueUpdater } from '@/lib/utils';
import type { ColumnDef, ExpandedState, SortingState, VisibilityState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getExpandedRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

interface DataTableProps<TData> {
    data: TData[];
    columns: ColumnDef<TData>[];
    showColumnToggle?: boolean;
    emptyMessage?: string;
}

const props = withDefaults(defineProps<DataTableProps<TData>>(), {
    showColumnToggle: true,
    emptyMessage: 'No results found.',
});

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
    onRowSelectionChange: (updaterOrValue) => valueUpdater(updaterOrValue, rowSelection),
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
    manualFiltering: true,
    manualPagination: true,
});

// Expose table instance for parent components
defineExpose({
    table,
});
</script>

<template>
    <div class="space-y-4">
        <!-- Column Toggle -->
        <div v-if="showColumnToggle" class="flex justify-end">
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
                        :model-value="column.getIsVisible()"
                        @update:model-value="(value) => column.toggleVisibility(!!value)"
                    >
                        {{ column.id }}
                    </DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>
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
                            :class="
                                cn(
                                    { 'bg-background/95 sticky': header.column.getIsPinned() },
                                    header.column.getIsPinned() === 'left' ? 'left-0' : 'right-0',
                                )
                            "
                        >
                            <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
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
                                    :class="
                                        cn(
                                            { 'bg-background/95 sticky': cell.column.getIsPinned() },
                                            cell.column.getIsPinned() === 'left' ? 'left-0' : 'right-0',
                                        )
                                    "
                                >
                                    <slot
                                        :name="`cell-${cell.column.id}`"
                                        :cell="cell"
                                        :row="row"
                                        :value="cell.getValue()"
                                    >
                                        <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                    </slot>
                                </TableCell>
                            </TableRow>
                        </template>
                    </template>

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
