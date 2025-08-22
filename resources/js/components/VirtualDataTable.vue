<script setup lang="ts" generic="TData, TValue">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { type ColumnDef, FlexRender, getCoreRowModel, getSortedRowModel, type RowSelectionState, type SortingState, useVueTable, type VisibilityState } from '@tanstack/vue-table';
import { useVirtualizer } from '@tanstack/vue-virtual';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface VirtualDataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    enableRowSelection?: boolean;
    enableSorting?: boolean;
    enableColumnVisibility?: boolean;
    height?: string;
    estimatedRowHeight?: number;
    overscan?: number;
    emptyMessage?: string;
    onSelectionChange?: (selectedRows: TData[]) => void;
}

const props = withDefaults(defineProps<VirtualDataTableProps<TData, TValue>>(), {
    enableRowSelection: false,
    enableSorting: true,
    enableColumnVisibility: false,
    height: '400px',
    estimatedRowHeight: 53,
    overscan: 10,
    emptyMessage: 'No results.',
});

const emit = defineEmits<{
    'selection-change': [selectedRows: TData[]];
}>();

// Table state
const sorting = ref<SortingState>([]);
const rowSelection = ref<RowSelectionState>({});
const columnVisibility = ref<VisibilityState>({});

// Table instance
const table = useVueTable({
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    onSortingChange: (updaterOrValue) => {
        sorting.value = typeof updaterOrValue === 'function' ? updaterOrValue(sorting.value) : updaterOrValue;
    },
    onRowSelectionChange: (updaterOrValue) => {
        rowSelection.value = typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection.value) : updaterOrValue;
    },
    onColumnVisibilityChange: (updaterOrValue) => {
        columnVisibility.value = typeof updaterOrValue === 'function' ? updaterOrValue(columnVisibility.value) : updaterOrValue;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
        get rowSelection() {
            return rowSelection.value;
        },
        get columnVisibility() {
            return columnVisibility.value;
        },
    },
    enableRowSelection: props.enableRowSelection,
    enableSorting: props.enableSorting,
});

// Virtual scrolling setup
const rows = computed(() => table.getRowModel().rows);
const tableContainerRef = ref<HTMLDivElement | null>(null);

const rowVirtualizerOptions = computed(() => ({
    count: rows.value.length,
    estimateSize: () => props.estimatedRowHeight,
    getScrollElement: () => tableContainerRef.value,
    overscan: props.overscan,
}));

const rowVirtualizer = useVirtualizer(rowVirtualizerOptions);
const virtualRows = computed(() => rowVirtualizer.value.getVirtualItems());
const totalSize = computed(() => rowVirtualizer.value.getTotalSize());

// Selection change watcher
watch(
    () => rowSelection.value,
    () => {
        const selectedRows = table.getFilteredSelectedRowModel().rows.map((row) => row.original);
        emit('selection-change', selectedRows);
        props.onSelectionChange?.(selectedRows);
    },
    { deep: true },
);

// Measure element for dynamic height
function measureElement(el?: Element) {
    if (!el) return;
    rowVirtualizer.value.measureElement(el);
    return undefined;
}

// Clear selection method
function clearSelection() {
    rowSelection.value = {};
}

// Toggle all rows selection
function toggleAllRowsSelection() {
    table.toggleAllRowsSelected();
}

// Column sizing functions
function getColumnMinWidth(columnDef: any): string {
    const accessorKey = columnDef.accessorKey || columnDef.id;

    // Define min widths for specific columns
    const minWidths: Record<string, string> = {
        id: '80px',
        student_code: '140px',
        full_name: '200px',
        gender: '120px',
        phone: '120px',
        email: '250px',
        status: '120px',
        actions: '100px',
        overall: '90px',
        listening: '80px',
        reading: '80px',
        writing: '80px',
        speaking: '80px',
        submitted_photo: '70px',
        submitted_cccd: '70px',
        submitted_ccta: '70px',
        submitted_tn_translate: '100px',
        submitted_hb_translate: '100px',
        submitted_other: '90px',
        submitted_insurance_card: '90px',
        submitted_exemption_gc: '100px',
        is_international_applicant: '100px',
        address: '200px',
        intended_program: '180px',
        intended_specialization: '180px',
    };

    return minWidths[accessorKey] || '50px';
}

function getColumnMaxWidth(columnDef: any): string {
    const accessorKey = columnDef.accessorKey || columnDef.id;

    // Define max widths for specific columns to prevent them from becoming too wide
    const maxWidths: Record<string, string> = {
        id: '100px',
        gender: '100px',
        phone: '150px',
        status: '150px',
        actions: '140px',
        overall: '120px',
        listening: '100px',
        reading: '100px',
        writing: '100px',
        speaking: '100px',
        submitted_photo: '80px',
        submitted_cccd: '80px',
        submitted_ccta: '80px',
        submitted_other: '120px',
        submitted_insurance_card: '120px',
        is_international_applicant: '120px',
        full_name: '300px',
        student_code: '180px',
        email: '350px',
    };

    return maxWidths[accessorKey] || '200px';
}

function getColumnWidth(columnDef: any): string | undefined {
    const accessorKey = columnDef.accessorKey || columnDef.id;

    // Define fixed widths for specific columns
    const fixedWidths: Record<string, string> = {
        id: '80px',
        gender: '80px',
        overall: '90px',
        listening: '80px',
        reading: '80px',
        writing: '80px',
        speaking: '80px',
        submitted_photo: '70px',
        submitted_cccd: '70px',
        submitted_ccta: '70px',
        submitted_other: '90px',
        submitted_insurance_card: '90px',
        submitted_exemption_gc: '100px',
        is_international_applicant: '100px',
    };

    return fixedWidths[accessorKey];
}

function getColumnFlex(columnDef: any): string {
    const accessorKey = columnDef.accessorKey || columnDef.id;

    // Columns that should be fixed width
    const fixedColumns = [
        'id',
        'gender',
        'overall',
        'listening',
        'reading',
        'writing',
        'speaking',
        'submitted_photo',
        'submitted_cccd',
        'submitted_ccta',
        'submitted_other',
        'submitted_insurance_card',
        'submitted_exemption_gc',
        'is_international_applicant',
    ];

    if (fixedColumns.includes(accessorKey)) {
        return '0 0 auto'; // Don't grow or shrink, use fixed width
    }

    // Content-heavy columns that should take more space
    const wideColumns = ['full_name', 'email', 'address', 'intended_program', 'intended_specialization', 'english_qualifications', 'exception_units'];

    if (wideColumns.includes(accessorKey)) {
        return '2'; // Take more space
    }

    return '1'; // Default flex
}

// Function to determine if column content should be truncated
function shouldTruncateColumn(columnDef: any): boolean {
    const accessorKey = columnDef.accessorKey || columnDef.id;

    // Don't truncate important text columns
    const noTruncateColumns = ['full_name', 'student_code', 'email', 'phone', 'address', 'intended_program', 'intended_specialization'];

    return !noTruncateColumns.includes(accessorKey);
}

// Expose methods
defineExpose({
    clearSelection,
    toggleAllRowsSelection,
    getSelectedRows: () => table.getFilteredSelectedRowModel().rows.map((row) => row.original),
});
</script>

<template>
    <div class="space-y-4">
        <!-- Column Visibility Toggle -->
        <div v-if="enableColumnVisibility" class="flex items-center">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="ml-auto">
                        Columns
                        <ChevronDown class="ml-2 h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-[200px]">
                    <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                        v-for="column in table.getAllColumns().filter((column) => typeof column.accessorFn !== 'undefined' && column.getCanHide())"
                        :key="column.id"
                        class="capitalize"
                        :model-value="column.getIsVisible()"
                        @update:model-value="(value) => column.toggleVisibility(!!value)"
                        @select.prevent
                    >
                        {{ column.id }}
                    </DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Table Container -->
        <div class="rounded-md border">
            <div
                ref="tableContainerRef"
                :style="{
                    overflow: 'auto',
                    position: 'relative',
                    height: height,
                }"
                class="relative"
            >
                <div v-if="rows.length === 0" class="text-muted-foreground flex h-24 items-center justify-center">
                    {{ emptyMessage }}
                </div>

                <div v-else :style="{ height: `${totalSize}px` }">
                    <!-- Virtual Table with flexible columns -->
                    <div class="w-full">
                        <!-- Header -->
                        <div class="bg-muted/50 sticky top-0 z-10 border-b">
                            <div v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id" class="flex w-full">
                                <div
                                    v-for="header in headerGroup.headers"
                                    :key="header.id"
                                    :class="cn('text-muted-foreground flex h-12 items-center px-4 text-left align-middle font-medium', header.column.getCanSort() && 'hover:text-foreground cursor-pointer select-none')"
                                    :style="{
                                        minWidth: getColumnMinWidth(header.column.columnDef),
                                        maxWidth: getColumnMaxWidth(header.column.columnDef),
                                        width: getColumnWidth(header.column.columnDef),
                                        flex: getColumnFlex(header.column.columnDef),
                                    }"
                                    @click="header.column.getCanSort() ? header.column.getToggleSortingHandler()?.($event) : undefined"
                                >
                                    <div v-if="!header.isPlaceholder" class="flex min-w-0 items-center space-x-2">
                                        <div class="truncate">
                                            <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                                        </div>
                                        <div v-if="header.column.getCanSort()" class="flex-shrink-0">
                                            <svg
                                                v-if="header.column.getIsSorted() === 'asc'"
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="16"
                                                height="16"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="h-4 w-4"
                                            >
                                                <path d="m7 15 5 5 5-5" />
                                                <path d="m7 9 5-5 5 5" />
                                            </svg>
                                            <svg
                                                v-else-if="header.column.getIsSorted() === 'desc'"
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="16"
                                                height="16"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="h-4 w-4"
                                            >
                                                <path d="m7 15 5 5 5-5" />
                                                <path d="m7 9 5-5 5 5" />
                                            </svg>
                                            <svg
                                                v-else
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="16"
                                                height="16"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="h-4 w-4 opacity-50"
                                            >
                                                <path d="m7 15 5 5 5-5" />
                                                <path d="m7 9 5-5 5 5" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Virtual Body -->
                        <div
                            :style="{
                                height: `${totalSize}px`,
                                position: 'relative',
                            }"
                        >
                            <div
                                v-for="vRow in virtualRows"
                                :key="rows[vRow.index].id"
                                :data-index="vRow.index"
                                :ref="measureElement"
                                :style="{
                                    position: 'absolute',
                                    transform: `translateY(${vRow.start}px)`,
                                    width: '100%',
                                }"
                                :class="cn('hover:bg-muted/50 flex w-full border-b transition-colors', rows[vRow.index].getIsSelected() && 'bg-muted')"
                            >
                                <div
                                    v-for="cell in rows[vRow.index].getVisibleCells()"
                                    :key="cell.id"
                                    class="flex items-center px-4 py-3 align-middle"
                                    :style="{
                                        minWidth: getColumnMinWidth(cell.column.columnDef),
                                        maxWidth: getColumnMaxWidth(cell.column.columnDef),
                                        width: getColumnWidth(cell.column.columnDef),
                                        flex: getColumnFlex(cell.column.columnDef),
                                    }"
                                >
                                    <div :class="shouldTruncateColumn(cell.column.columnDef) ? 'w-full truncate' : 'w-full'">
                                        <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
