# useDataTable Composable - Usage Examples

## Basic Usage

```typescript
import { useDataTable } from '@/composables/useDataTable';

// Basic table with search and pagination
const {
    state,
    filters,
    setFilter,
    apply,
    setPage,
    setPerPage,
    isLoading,
    hasActiveFilters
} = useDataTable({
    baseUrl: '/students',
    debounce: 300,
    initialFilters: {
        search: '',
        status: 'active'
    },
    defaultValues: {
        per_page: 15,
        status: 'active'
    }
});
```

## Advanced Usage with Validation

```typescript
const dataTable = useDataTable({
    baseUrl: '/students',
    fieldDebounce: {
        search: 500,  // Longer debounce for search
        status: 0     // Immediate for dropdowns
    },
    immediateFields: ['page', 'per_page'], // No debounce for pagination
    initialFilters: {
        search: '',
        email: '',
        status: 'active'
    }
});

// Add validation rules
dataTable.addValidationRule('email', {
    validate: (value) => {
        if (!value) return null;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) 
            ? null 
            : 'Invalid email format';
    },
    message: 'Please enter a valid email address'
});

// Add async validation
dataTable.addValidationRule('search', {
    validate: async (value) => {
        if (value.length < 3) return null;
        
        // Check if search term is allowed
        const response = await fetch('/api/validate-search', {
            method: 'POST',
            body: JSON.stringify({ term: value })
        });
        
        const result = await response.json();
        return result.allowed ? null : 'Search term contains blocked words';
    },
    message: 'Search term validation failed',
    async: true
});
```

## Dependent Filters

```typescript
const dataTable = useDataTable({
    baseUrl: '/students',
    initialFilters: {
        campus_id: null,
        program_id: null,
        course_id: null
    }
});

// Add campus -> programs dependency
dataTable.addDependency('program_id', {
    dependsOn: ['campus_id'],
    resolve: async ([campusId]) => {
        if (!campusId) return null;
        
        const response = await fetch(`/api/campuses/${campusId}/programs`);
        const programs = await response.json();
        
        // Return first program as default, or null if no programs
        return programs.length > 0 ? programs[0].id : null;
    }
});

// Add program -> courses dependency
dataTable.addDependency('course_id', {
    dependsOn: ['program_id'],
    resolve: async ([programId]) => {
        if (!programId) return null;
        
        const response = await fetch(`/api/programs/${programId}/courses`);
        const courses = await response.json();
        
        return courses.length > 0 ? courses[0].id : null;
    }
});
```

## Template Usage with shadcn-vue

```vue
<template>
    <div class="space-y-4">
        <!-- Search Input -->
        <div class="relative">
            <Input
                v-model="filters.search"
                @update:model-value="setFilter('search', $event)"
                placeholder="Search students..."
                :disabled="isLoading"
                class="w-full"
            >
                <template #prefix>
                    <Search class="h-4 w-4" />
                </template>
            </Input>
        </div>
        
        <!-- Validation Errors -->
        <Alert v-if="hasValidationErrors" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Validation Errors</AlertTitle>
            <AlertDescription>
                <div v-for="(errors, field) in validationErrors" :key="field" class="space-y-1">
                    <div v-for="error in errors" :key="error" class="text-sm">
                        {{ error }}
                    </div>
                </div>
            </AlertDescription>
        </Alert>
        
        <!-- Filters Row -->
        <div class="flex gap-4 items-end">
            <!-- Campus Filter -->
            <div class="flex-1">
                <Label for="campus">Campus</Label>
                <Select
                    v-model="filters.campus_id"
                    @update:model-value="setFilter('campus_id', $event)"
                    :disabled="isLoading"
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select Campus" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Select Campus</SelectItem>
                        <SelectItem 
                            v-for="campus in campuses" 
                            :key="campus.id" 
                            :value="campus.id"
                        >
                            {{ campus.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            
            <!-- Program Filter (dependent on campus) -->
            <div class="flex-1">
                <Label for="program">Program</Label>
                <Select
                    v-model="filters.program_id"
                    @update:model-value="setFilter('program_id', $event)"
                    :disabled="!filters.campus_id || isResolvingDependencies"
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select Program" />
                        <template v-if="isResolvingDependencies" #suffix>
                            <Loader2 class="h-4 w-4 animate-spin" />
                        </template>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Select Program</SelectItem>
                        <SelectItem 
                            v-for="program in programs" 
                            :key="program.id" 
                            :value="program.id"
                        >
                            {{ program.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            
            <!-- Status Filter -->
            <div class="flex-1">
                <Label for="status">Status</Label>
                <Select
                    v-model="filters.status"
                    @update:model-value="setFilter('status', $event)"
                    :disabled="isLoading"
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="inactive">Inactive</SelectItem>
                        <SelectItem value="graduated">Graduated</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            
            <!-- Clear Filters Button -->
            <Button
                v-if="hasActiveFilters"
                @click="clearAllFilters"
                variant="outline"
                :disabled="isLoading"
            >
                <X class="h-4 w-4 mr-2" />
                Clear Filters
            </Button>
        </div>
        
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center py-8">
            <Loader2 class="h-8 w-8 animate-spin mr-2" />
            <span>Loading students...</span>
        </div>
        
        <!-- Error State -->
        <Alert v-if="hasError" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>
                {{ errorMessage }}
            </AlertDescription>
        </Alert>
        
        <!-- Data Table -->
        <div v-if="!isLoading && !hasError" class="border rounded-lg">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead 
                            @click="setSort('name', currentSort === 'name' && currentDirection === 'asc' ? 'desc' : 'asc')"
                            class="cursor-pointer hover:bg-muted/50"
                        >
                            <div class="flex items-center space-x-2">
                                <span>Name</span>
                                <div v-if="currentSort === 'name'" class="flex items-center">
                                    <ArrowUp v-if="currentDirection === 'asc'" class="h-4 w-4" />
                                    <ArrowDown v-else class="h-4 w-4" />
                                </div>
                            </div>
                        </TableHead>
                        <TableHead 
                            @click="setSort('email', currentSort === 'email' && currentDirection === 'asc' ? 'desc' : 'asc')"
                            class="cursor-pointer hover:bg-muted/50"
                        >
                            <div class="flex items-center space-x-2">
                                <span>Email</span>
                                <div v-if="currentSort === 'email'" class="flex items-center">
                                    <ArrowUp v-if="currentDirection === 'asc'" class="h-4 w-4" />
                                    <ArrowDown v-else class="h-4 w-4" />
                                </div>
                            </div>
                        </TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="student in state.data" :key="student.id">
                        <TableCell class="font-medium">
                            {{ student.name }}
                        </TableCell>
                        <TableCell>{{ student.email }}</TableCell>
                        <TableCell>
                            <Badge :variant="getStatusVariant(student.status)">
                                {{ student.status }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal class="h-4 w-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            
            <!-- Empty State -->
            <div v-if="state.data.length === 0" class="text-center py-8">
                <Users class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <h3 class="text-lg font-semibold">No students found</h3>
                <p class="text-muted-foreground">
                    Try adjusting your search or filters to find what you're looking for.
                </p>
            </div>
        </div>
        
        <!-- Pagination -->
        <div v-if="state.data.length > 0" class="flex items-center justify-between">
            <div class="text-sm text-muted-foreground">
                Showing {{ (currentPage - 1) * state.pagination.perPage + 1 }} to 
                {{ Math.min(currentPage * state.pagination.perPage, state.pagination.total) }} 
                of {{ state.pagination.total }} results
            </div>
            
            <div class="flex items-center space-x-2">
                <Button
                    @click="setPage(currentPage - 1)"
                    :disabled="isFirstPage || isLoading"
                    variant="outline"
                    size="sm"
                >
                    <ChevronLeft class="h-4 w-4" />
                    Previous
                </Button>
                
                <div class="flex items-center space-x-1">
                    <Button
                        v-for="page in visiblePages"
                        :key="page"
                        @click="setPage(page)"
                        :variant="page === currentPage ? 'default' : 'outline'"
                        size="sm"
                        class="w-8 h-8 p-0"
                    >
                        {{ page }}
                    </Button>
                </div>
                
                <Button
                    @click="setPage(currentPage + 1)"
                    :disabled="isLastPage || isLoading"
                    variant="outline"
                    size="sm"
                >
                    Next
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
            
            <!-- Page Size Selector -->
            <Select
                v-model="state.pagination.perPage"
                @update:model-value="setPerPage($event)"
                :disabled="isLoading"
            >
                <SelectTrigger class="w-20">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="10">10</SelectItem>
                    <SelectItem :value="15">15</SelectItem>
                    <SelectItem :value="25">25</SelectItem>
                    <SelectItem :value="50">50</SelectItem>
                </SelectContent>
            </Select>
        </div>
        
        <!-- Performance Metrics (development only) -->
        <Card v-if="import.meta.env.DEV" class="border-dashed">
            <CardContent class="pt-6">
                <div class="flex items-center space-x-2 text-xs text-muted-foreground">
                    <Activity class="h-3 w-3" />
                    <span>Performance Metrics:</span>
                    <span>Requests: {{ performanceMetrics.requestCount }}</span>
                    <span>•</span>
                    <span>Cached: {{ performanceMetrics.cachedRequests }}</span>
                    <span>•</span>
                    <span>Debounced: {{ performanceMetrics.debouncedFunctions }}</span>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
    Search,
    AlertCircle,
    Loader2,
    X,
    ArrowUp,
    ArrowDown,
    MoreHorizontal,
    Users,
    ChevronLeft,
    ChevronRight,
    Activity
} from 'lucide-vue-next';

// shadcn-vue components
import {
    Input,
    Label,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    Button,
    Alert,
    AlertDescription,
    AlertTitle,
    Table,
    TableHeader,
    TableRow,
    TableHead,
    TableBody,
    TableCell,
    Badge,
    Card,
    CardContent
} from '@/components/ui';

// Helper for visible page numbers
const visiblePages = computed(() => {
    const current = currentPage.value;
    const total = totalPages.value;
    const delta = 2; // Show 2 pages before and after current
    
    const range = [];
    const rangeWithDots = [];
    let l;

    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
            range.push(i);
        }
    }

    range.forEach((i) => {
        if (l) {
            if (i - l === 2) {
                rangeWithDots.push(l + 1);
            } else if (i - l !== 1) {
                rangeWithDots.push('...');
            }
        }
        rangeWithDots.push(i);
        l = i;
    });

    return rangeWithDots.filter(page => page !== '...');
});

// Helper for status badge variants
const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active': return 'default';
        case 'inactive': return 'secondary';
        case 'graduated': return 'outline';
        default: return 'secondary';
    }
};
</script>
```

## Migration from useInertiaFilters

### Before (useInertiaFilters)
```typescript
const { filters, applyFilters, updateFilter } = useInertiaFilters({
    baseUrl: '/students',
    initialFilters: { search: '' }
});

// Auto-sync behavior - updates happen automatically
const handleSearch = (value: string) => {
    updateFilter('search', value); // Triggers navigation automatically
};
```

### After (useDataTable)
```typescript
const { filters, setFilter, apply } = useDataTable({
    baseUrl: '/students',
    initialFilters: { search: '' }
});

// Explicit control - updates are deliberate
const handleSearch = (value: string) => {
    setFilter('search', value); // Validates and triggers debounced navigation
};

// Or apply multiple changes at once
const applyMultipleFilters = () => {
    apply({
        search: 'john',
        status: 'active',
        campus_id: '1'
    });
};
```

## Key Differences

| Feature | useInertiaFilters | useDataTable |
|---------|------------------|-------------|
| Control | Auto-sync (implicit) | Explicit method calls |
| Validation | None | Built-in sync/async validation |
| Dependencies | None | Built-in dependency management |
| Performance | Basic debouncing | Smart debouncing + optimizations |
| Type Safety | Basic | Full TypeScript support |
| Error Handling | Basic | Comprehensive error states |
| Metrics | None | Built-in performance metrics |