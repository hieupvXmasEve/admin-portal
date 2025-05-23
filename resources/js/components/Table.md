# Reusable Table Components

This directory contains reusable components for building data tables with pagination in Vue.js applications.

## Components

### DataTable.vue
A generic data table component that uses TanStack Vue Table for advanced table functionality.

**Props:**
- `data: TData[]` - Array of data to display
- `columns: ColumnDef<TData>[]` - Column definitions for the table
- `showColumnToggle?: boolean` - Whether to show column visibility toggle (default: true)
- `emptyMessage?: string` - Message to show when no data (default: "No results found.")

**Slots:**
- `cell-{columnId}` - Custom cell rendering for specific columns
  - Props: `{ cell, row, value }`

**Example:**
```vue
<DataTable :data="users" :columns="columns">
  <template #cell-actions="{ row }">
    <TableActions @edit="editUser(row.original)" />
  </template>
</DataTable>
```

### DataPagination.vue
A pagination component that works with Laravel's paginated responses with smart ellipsis handling and page size selection.

**Props:**
- `paginationData: PaginatedResponse` - Pagination data from Laravel (must include `per_page`)
- `itemName?: string` - Name of items being paginated (default: "items")
- `pageSizeOptions?: number[]` - Available page size options (default: [10, 25, 50, 100])
- `showPageSizeSelector?: boolean` - Whether to show page size selector (default: true)

**Events:**
- `navigate: (url: string)` - Emitted when user clicks pagination links
- `pageSizeChange: (pageSize: number)` - Emitted when user changes page size

**Features:**
- **Smart Ellipsis**: Shows "..." when there are many pages, clicking advances/goes back 5 pages
- **Page Size Selector**: Dropdown to change number of items per page
- **Responsive Layout**: Page info on left, controls on right
- **Keyboard Accessible**: Full keyboard navigation support

**Example:**
```vue
<DataPagination 
  :pagination-data="users" 
  item-name="users"
  :page-size-options="[10, 25, 50, 100]"
  :show-page-size-selector="true"
  @navigate="handlePaginationNavigate"
  @page-size-change="handlePageSizeChange"
/>
```

**Handling Events:**
```vue
const handlePaginationNavigate = (url: string) => {
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
    only: ['users'],
  });
};

const handlePageSizeChange = (pageSize: number) => {
  const params = new URLSearchParams(window.location.search);
  params.set('per_page', pageSize.toString());
  params.delete('page'); // Reset to first page
  
  const url = `/users?${params.toString()}`;
  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
    only: ['users'],
  });
};
```

### TableActions.vue
A component for common table row actions with tooltips.

**Props:**
- `showEdit?: boolean` - Whether to show edit button (default: true)
- `editTooltip?: string` - Tooltip text for edit button (default: "Edit")

**Events:**
- `edit: []` - Emitted when edit button is clicked

**Slots:**
- Default slot for additional action buttons

**Example:**
```vue
<TableActions @edit="editUser(row.original)">
  <Button @click="deleteUser(row.original)" variant="destructive" size="icon">
    <Trash2 class="h-4 w-4" />
  </Button>
</TableActions>
```

## Usage Pattern

1. Define your column definitions with TanStack Vue Table
2. Use DataTable for the main table display
3. Use TableActions in action columns via slots
4. Use DataPagination for pagination controls
5. Handle navigation events to update data

## Benefits

- **Reusable**: Components can be used across different data types
- **Consistent**: Uniform styling and behavior across the application
- **Maintainable**: Changes to table behavior only need to be made in one place
- **Flexible**: Slots allow for custom content while maintaining structure
- **Type-safe**: Full TypeScript support with generics 
