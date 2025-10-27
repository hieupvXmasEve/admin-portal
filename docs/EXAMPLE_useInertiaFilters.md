# useInertiaFilters - Usage Examples

## Tại sao nên dùng `useInertiaFilters` thay vì code thủ công?

### ❌ Cách cũ (thủ công)
```vue
<script setup lang="ts">
const filters = ref({
    search: props.filters.search || '',
    program_id: props.filters.program_id?.toString() || 'all',
    status: props.filters.status || 'all',
});

const currentSort = ref(props.filters.sort || null);
const currentDirection = ref(props.filters.direction || 'asc');

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    updateFilters();
};

const handleFilter = () => {
    updateFilters();
};

const handleSortChange = (sort: string | null, direction: 'asc' | 'desc' | null) => {
    currentSort.value = sort;
    currentDirection.value = direction || 'asc';
    updateFilters();
};

const clearFilters = () => {
    filters.value = {
        search: '',
        program_id: 'all',
        status: 'all',
    };
    currentSort.value = null;
    currentDirection.value = 'asc';
    router.visit(studentRoutes.list(), {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

const getFilterParams = () => {
    return {
        search: filters.value.search,
        program_id: filters.value.program_id === 'all' ? '' : filters.value.program_id,
        status: filters.value.status === 'all' ? '' : filters.value.status,
        sort: currentSort.value || '',
        direction: currentDirection.value || '',
    };
};

const updateFilters = () => {
    const params = getFilterParams();
    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });
    const url = `${studentRoutes.list()}${searchParams.toString() ? '?' + searchParams.toString() : ''}`;
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

// ... more handlers
</script>

<template>
    <DebouncedInput v-model="filters.search" @debounced="handleSearch" />
    <Select v-model="filters.program_id" @update:model-value="handleFilter">
    <DataTable @sort-change="handleSortChange" />
</template>
```

### ✅ Cách mới (useInertiaFilters)
```vue
<script setup lang="ts">
import { useInertiaFilters } from '@/composables/useInertiaFilters';

interface StudentFilters {
    search: string;
    program_id: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<Props>();

const { 
    filters, 
    hasActiveFilters, 
    clearFilters,
    handleSearch,
    handleSelectFilter,
    handleSortChange,
    handlePageSizeChange,
    handlePaginationNavigate,
} = useInertiaFilters<StudentFilters>({
    baseUrl: studentRoutes.list(),
    initialFilters: {
        search: props.filters.search || '',
        program_id: props.filters.program_id?.toString() || 'all',
        status: props.filters.status || 'all',
        sort: props.filters.sort || null,
        direction: props.filters.direction as 'asc' | 'desc' || null,
        per_page: props.filters.per_page || 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'asc',
        program_id: 'all',
        status: 'all',
    },
    only: ['students', 'filters'],
    debounce: 400,
});
</script>

<template>
    <!-- Auto syncs! -->
    <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" />
    
    <!-- Auto syncs! -->
    <Select 
        v-model="filters.program_id" 
        @update:model-value="(v) => handleSelectFilter('program_id', v)"
    />
    
    <!-- Auto syncs! -->
    <DataTable 
        @sort-change="handleSortChange"
        :initial-sort="filters.sort"
        :initial-direction="filters.direction"
    />
    
    <Button v-if="hasActiveFilters" @click="clearFilters">
        Clear Filters
    </Button>
</template>
```

**Lợi ích:**
- ✅ Giảm ~100 lines code
- ✅ Type-safe
- ✅ Auto debounce
- ✅ Clean URLs (loại bỏ default values)
- ✅ Consistent across all pages
- ✅ Easy to maintain

---

## Example 1: Students Index Page

```vue
<script setup lang="ts">
import { useInertiaFilters } from '@/composables/useInertiaFilters';

interface StudentFilters {
    search: string;
    program_id: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
}

const props = defineProps<{
    students: PaginatedData<Student>;
    filters: Partial<StudentFilters>;
    programs: Program[];
}>();

const { 
    filters, 
    hasActiveFilters, 
    clearFilters,
    handleSearch,
    handleSelectFilter,
    handleSortChange,
    handlePageSizeChange,
    handlePaginationNavigate,
} = useInertiaFilters<StudentFilters>({
    baseUrl: studentRoutes.list(),
    initialFilters: {
        search: props.filters.search || '',
        program_id: props.filters.program_id?.toString() || 'all',
        status: props.filters.status || 'all',
        sort: props.filters.sort || null,
        direction: (props.filters.direction as 'asc' | 'desc') || null,
        per_page: props.filters.per_page || 15,
        page: 1,
    },
    defaultValues: {
        per_page: 15,
        direction: 'asc',
        program_id: 'all',
        status: 'all',
        page: 1,
    },
    only: ['students', 'filters'],
    debounce: 400,
});
</script>

<template>
    <div class="space-y-4">
        <!-- Filters -->
        <Card>
            <CardContent class="p-4">
                <div class="flex gap-2">
                    <DebouncedInput 
                        :model-value="filters.search" 
                        @update:model-value="handleSearch" 
                        placeholder="Search students..." 
                    />
                    
                    <Select 
                        v-model="filters.program_id" 
                        @update:model-value="(v) => handleSelectFilter('program_id', v)"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="All Programs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Programs</SelectItem>
                            <SelectItem 
                                v-for="program in programs" 
                                :key="program.id" 
                                :value="program.id.toString()"
                            >
                                {{ program.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    
                    <Select 
                        v-model="filters.status" 
                        @update:model-value="(v) => handleSelectFilter('status', v)"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="All Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                    
                    <Button 
                        v-if="hasActiveFilters" 
                        variant="ghost" 
                        @click="clearFilters"
                    >
                        <X class="mr-2 h-4 w-4" /> Clear
                    </Button>
                </div>
            </CardContent>
        </Card>
        
        <!-- Table -->
        <DataTable 
            :columns="columns" 
            :data="students.data"
            enable-server-sorting
            :initial-sort="filters.sort"
            :initial-direction="filters.direction"
            @sort-change="handleSortChange"
        >
            <!-- ... -->
        </DataTable>
        
        <!-- Pagination -->
        <DataPagination 
            :pagination-data="students"
            @navigate="handlePaginationNavigate"
            @page-size-change="handlePageSizeChange"
        />
    </div>
</template>
```

---

## Example 2: Units Index (Simpler)

```vue
<script setup lang="ts">
import { useInertiaFilters } from '@/composables/useInertiaFilters';

interface UnitFilters {
    search: string;
    type: string;
    level: string;
}

const props = defineProps<{
    units: PaginatedData<Unit>;
    filters: Partial<UnitFilters>;
}>();

// Even simpler - just reactive filters!
const { filters, hasActiveFilters, clearFilters, handleSearch } = useInertiaFilters({
    baseUrl: '/units',
    initialFilters: {
        search: props.filters.search || '',
        type: props.filters.type || 'all',
        level: props.filters.level || 'all',
    },
    defaultValues: { type: 'all', level: 'all' },
    only: ['units', 'filters'],
});
</script>

<template>
    <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" />
    
    <!-- Direct binding - auto syncs! -->
    <Select v-model="filters.type">
        <SelectItem value="all">All Types</SelectItem>
        <SelectItem value="theory">Theory</SelectItem>
        <SelectItem value="lab">Lab</SelectItem>
    </Select>
    
    <Select v-model="filters.level">
        <SelectItem value="all">All Levels</SelectItem>
        <SelectItem value="100">Level 100</SelectItem>
        <SelectItem value="200">Level 200</SelectItem>
    </Select>
</template>
```

---

## Example 3: With Transform (Advanced)

Nếu cần convert data types trước khi gửi lên server:

```vue
<script setup lang="ts">
const { filters } = useInertiaFilters({
    baseUrl: '/students',
    initialFilters: {
        program_id: props.filters.program_id || null,
        status: props.filters.status || 'all',
        is_active: props.filters.is_active || false,
    },
    // Transform before sending to server
    transform: (filters) => ({
        program_id: filters.program_id === 'all' ? null : parseInt(filters.program_id),
        status: filters.status === 'all' ? null : filters.status,
        is_active: filters.is_active ? 1 : 0, // Convert boolean to int
    }),
});
</script>
```

---

## Example 4: Manual Mode (No Auto Sync)

Nếu muốn control manually:

```vue
<script setup lang="ts">
const { 
    filters, 
    applyFilters, // Manual apply
    clearFilters 
} = useInertiaFilters({
    baseUrl: '/students',
    initialFilters: props.filters,
    autoSync: false, // Disable auto sync
});

// Apply manually on button click
const handleApply = () => {
    applyFilters();
};
</script>

<template>
    <input v-model="filters.search" />
    <Select v-model="filters.status" />
    <Button @click="handleApply">Apply Filters</Button>
</template>
```

---

## Migration Guide

### Step 1: Import composable
```ts
import { useInertiaFilters } from '@/composables/useInertiaFilters';
```

### Step 2: Define filter types
```ts
interface YourFilters {
    search: string;
    // ... other filters
}
```

### Step 3: Replace manual filter logic
```ts
// Old
const filters = ref({ ... });
const handleSearch = (v) => { ... };
const updateFilters = () => { ... };

// New
const { filters, handleSearch } = useInertiaFilters<YourFilters>({
    baseUrl: yourRoute.list(),
    initialFilters: props.filters,
    only: ['items', 'filters'],
});
```

### Step 4: Update template
```vue
<!-- Old -->
<DebouncedInput v-model="filters.search" @debounced="handleSearch" />

<!-- New (same!) -->
<DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" />

<!-- Or even simpler with auto-sync -->
<DebouncedInput v-model="filters.search" />
```

---

## Tips

1. **Clean URLs**: Default values sẽ không hiển thị trên URL
2. **Type Safety**: Sử dụng interface để define filter types
3. **Debounce**: Tự động debounce 400ms (configurable)
4. **Partial Reloads**: Chỉ reload data cần thiết với `only`
5. **Pagination**: Built-in handlers cho pagination
6. **Sorting**: Built-in handler cho DataTable sorting

## Best Practices

```ts
// ✅ Good: Define interface
interface StudentFilters {
    search: string;
    status: string;
}

// ✅ Good: Set default values
defaultValues: {
    status: 'all',
    per_page: 15,
}

// ✅ Good: Use only for performance
only: ['students', 'filters']

// ❌ Bad: Không define types
const { filters } = useInertiaFilters({
    initialFilters: { search: '' }
});

// ❌ Bad: Không set defaults
// URL sẽ có ?status=all&per_page=15 luôn
```
