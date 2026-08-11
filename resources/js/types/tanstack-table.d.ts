import '@tanstack/vue-table';

// TanStack's ColumnMeta is an empty interface by design. This augmentation adds
// an optional readable label so DataTable's column-toggle dropdown can show
// something better than a raw snake_case column id.
declare module '@tanstack/vue-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData, TValue> {
        label?: string;
    }
}
