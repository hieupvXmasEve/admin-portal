import { h } from 'vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { Checkbox } from '@/components/ui/checkbox';

/**
 * Creates a row selection column for DataTable using shadcn-vue Checkbox components
 * This follows the official shadcn-vue data table patterns
 */
export function createSelectionColumn<TData>(): ColumnDef<TData> {
    return {
        id: 'select',
        header: ({ table }) => h(Checkbox, {
            'modelValue': table.getIsAllPageRowsSelected(),
            'onUpdate:modelValue': (value: boolean | "indeterminate") => table.toggleAllPageRowsSelected(!!value),
            'ariaLabel': 'Select all',
        }),
        cell: ({ row }) => h(Checkbox, {
            'modelValue': row.getIsSelected(),
            'onUpdate:modelValue': (value: boolean | "indeterminate") => row.toggleSelected(!!value),
            'ariaLabel': 'Select row',
        }),
        enableSorting: false,
        enableHiding: false,
    };
}

/**
 * Creates an actions column for DataTable with custom content
 */
export function createActionsColumn<TData>(
    cellRenderer: (props: { row: any }) => any
): ColumnDef<TData> {
    return {
        id: 'actions',
        header: 'Actions',
        cell: cellRenderer,
        enableSorting: false,
        enableHiding: false,
    };
}

/**
 * Utility type for column definitions with selection support
 */
export type DataTableColumn<TData> = ColumnDef<TData>;

/**
 * Helper to create columns array with optional selection column
 */
export function createColumns<TData>(
    columns: ColumnDef<TData>[],
    options: {
        enableSelection?: boolean;
        selectionColumn?: ColumnDef<TData>;
    } = {}
): ColumnDef<TData>[] {
    const { enableSelection = false, selectionColumn } = options;

    const result: ColumnDef<TData>[] = [];

    if (enableSelection) {
        result.push(selectionColumn || createSelectionColumn<TData>());
    }

    result.push(...columns);

    return result;
}
