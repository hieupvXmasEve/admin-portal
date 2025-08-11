# useFilters Composable - Tài liệu sử dụng

## Tổng quan

`useFilters` là một composable mạnh mẽ và đơn giản để quản lý filtering, pagination, và URL synchronization trong ứng dụng Vue.js với Inertia.js.

### Tính năng chính

✅ **URL Synchronization**: Tự động sync filters với URL  
✅ **Filter Preservation**: Giữ nguyên filters khi pagination/page size change  
✅ **Type Safety**: Hỗ trợ TypeScript với generic types  
✅ **Debounced Filtering**: Tối ưu hiệu suất với debounced input  
✅ **Easy API**: Interface đơn giản, dễ sử dụng  

## Cài đặt và Import

```typescript
import { useTableFilters } from '@/composables/useFilters';
```

## Cách sử dụng cơ bản

### 1. Định nghĩa Interface cho Filters

```typescript
interface UnitsFilters {
    search?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
    page?: number;
    credit_points?: number;
    has_prerequisites?: boolean;
    has_equivalents?: boolean;
    in_curriculum?: boolean;
    min_credit_points?: number;
    max_credit_points?: number;
}
```

### 2. Sử dụng trong Component

```typescript
<script setup lang="ts">
import { useTableFilters } from '@/composables/useFilters';

interface Props {
    units: PaginatedResponse<Unit>;
    filters?: UnitsFilters;
    statistics: Statistics;
}

const props = defineProps<Props>();

// Khởi tạo composable với type safety
const { 
    filters, 
    hasActiveFilters, 
    debouncedApplyFilters, 
    clearFilters, 
    handlePaginationNavigate, 
    handlePageSizeChange, 
    updateFieldDebounced 
} = useTableFilters<UnitsFilters>(
    route('units.index'), // Base URL
    props.filters || {},  // Initial filters from backend
    ['units', 'filters']  // Inertia.js only params
);

// Helper function cho search input
const updateSearchFilter = (value: string) => {
    updateFieldDebounced('search', value);
};
</script>
```

### 3. Sử dụng trong Template

```vue
<template>
    <!-- Search Input -->
    <Input 
        placeholder="Search units..." 
        :model-value="filters.search" 
        @update:model-value="updateSearchFilter" 
    />

    <!-- Clear Filters Button -->
    <Button v-if="hasActiveFilters" @click="clearFilters">
        Clear Filters
    </Button>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" />

    <!-- Pagination -->
    <DataPagination 
        :pagination-data="units" 
        @navigate="handlePaginationNavigate" 
        @page-size-change="handlePageSizeChange" 
    />
</template>
```

## API Reference

### useTableFilters

**Signature:**
```typescript
function useTableFilters<T>(
    baseIndexUrl: string,
    initialFilters?: Record<string, any>,
    only?: string[]
): FilterComposableReturn<T>
```

**Parameters:**
- `baseIndexUrl`: URL cơ sở cho trang index (ví dụ: `/units`)
- `initialFilters`: Giá trị filters ban đầu từ backend props
- `only`: Array các keys để Inertia.js chỉ reload (default: `['items', 'filters']`)

**Return Object:**
```typescript
{
    // State
    filters: Ref<T>;                    // Reactive filters object
    hasActiveFilters: ComputedRef<boolean>; // Có filters đang active không

    // Methods
    applyFilters: (newFilters: Partial<T>) => void;         // Apply filters ngay lập tức
    debouncedApplyFilters: (newFilters: Partial<T>) => void; // Apply filters với debounce (500ms)
    clearFilters: () => void;                               // Reset tất cả filters
    
    // Pagination
    handlePaginationNavigate: (url: string) => void;        // Xử lý pagination navigation
    handlePageSizeChange: (size: number) => void;           // Xử lý thay đổi page size
    
    // Helpers  
    updateField: (key: keyof T, value: any) => void;        // Update 1 field ngay lập tức
    updateFieldDebounced: (key: keyof T, value: any) => void; // Update 1 field với debounce
    appendCurrentQueryTo: (url: string) => string;          // Append current query to URL
}
```

## Các ví dụ sử dụng

### 1. Search với Debounce

```typescript
// Cách 1: Sử dụng updateFieldDebounced
const updateSearchFilter = (value: string) => {
    updateFieldDebounced('search', value);
};

// Cách 2: Sử dụng debouncedApplyFilters
const handleSearchChange = (value: string) => {
    debouncedApplyFilters({ search: value });
};
```

### 2. Select Dropdown

```typescript
const handleSortChange = (sortField: string) => {
    applyFilters({ sort: sortField });
};

const handleDirectionChange = (direction: 'asc' | 'desc') => {
    applyFilters({ direction });
};
```

### 3. Boolean Filters

```typescript
const togglePrerequisites = (hasPrerequisites: boolean) => {
    applyFilters({ has_prerequisites: hasPrerequisites });
};
```

### 4. Range Filters

```typescript
const handleCreditPointsChange = (min: number, max: number) => {
    applyFilters({ 
        min_credit_points: min, 
        max_credit_points: max 
    });
};
```

### 5. Multiple Filters cùng lúc

```typescript
const applyAdvancedFilters = () => {
    applyFilters({
        credit_points: 15,
        has_prerequisites: true,
        in_curriculum: true
    });
};
```

## Template Examples

### Complete Filter Section

```vue
<template>
    <div class="filters-section">
        <!-- Search -->
        <div class="search-input">
            <Search class="search-icon" />
            <Input 
                placeholder="Tìm kiếm units..." 
                :model-value="filters.search" 
                @update:model-value="updateSearchFilter" 
            />
        </div>

        <!-- Sort -->
        <Select :model-value="filters.sort" @update:model-value="val => applyFilters({ sort: val })">
            <SelectTrigger>
                <SelectValue placeholder="Sắp xếp theo" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="name">Tên</SelectItem>
                <SelectItem value="code">Mã</SelectItem>
                <SelectItem value="credit_points">Điểm tín chỉ</SelectItem>
            </SelectContent>
        </Select>

        <!-- Direction -->
        <Select :model-value="filters.direction" @update:model-value="val => applyFilters({ direction: val })">
            <SelectTrigger>
                <SelectValue placeholder="Thứ tự" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="asc">Tăng dần</SelectItem>
                <SelectItem value="desc">Giảm dần</SelectItem>
            </SelectContent>
        </Select>

        <!-- Boolean Filters -->
        <div class="boolean-filters">
            <Button 
                :variant="filters.has_prerequisites ? 'default' : 'outline'"
                @click="applyFilters({ has_prerequisites: !filters.has_prerequisites })"
            >
                Có điều kiện tiên quyết
            </Button>
            
            <Button 
                :variant="filters.has_equivalents ? 'default' : 'outline'"
                @click="applyFilters({ has_equivalents: !filters.has_equivalents })"
            >
                Có môn tương đương
            </Button>
        </div>

        <!-- Clear Filters -->
        <Button v-if="hasActiveFilters" variant="ghost" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Xóa bộ lọc
        </Button>
    </div>
</template>
```

## Backend Integration

### Laravel Controller

```php
<?php

class UnitsController extends Controller
{
    public function index(Request $request)
    {
        $query = Unit::query();

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('sort')) {
            $direction = $request->input('direction', 'asc');
            $query->orderBy($request->sort, $direction);
        }

        if ($request->filled('credit_points')) {
            $query->where('credit_points', $request->credit_points);
        }

        if ($request->filled('has_prerequisites')) {
            $hasPrerequisites = filter_var($request->has_prerequisites, FILTER_VALIDATE_BOOLEAN);
            if ($hasPrerequisites) {
                $query->whereHas('prerequisiteConditions');
            } else {
                $query->whereDoesntHave('prerequisiteConditions');
            }
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $units = $query->paginate($perPage);

        return inertia('units/Index', [
            'units' => $units,
            'filters' => $request->only([
                'search', 'sort', 'direction', 'per_page', 'page',
                'credit_points', 'has_prerequisites', 'has_equivalents',
                'in_curriculum', 'min_credit_points', 'max_credit_points'
            ]),
            'statistics' => $this->getStatistics()
        ]);
    }
}
```

## Best Practices

### 1. **Type Safety**
```typescript
// ✅ Luôn định nghĩa interface cho filters
interface MyFilters {
    search?: string;
    status?: string[];
    date_range?: { from: string; to: string };
}

// ✅ Sử dụng generic type
const { filters } = useTableFilters<MyFilters>(...);
```

### 2. **Performance**
```typescript
// ✅ Sử dụng debounced cho text input
const updateSearchFilter = (value: string) => {
    updateFieldDebounced('search', value); // Debounce 500ms
};

// ✅ Sử dụng immediate cho select/button
const handleStatusChange = (status: string) => {
    applyFilters({ status }); // Immediate
};
```

### 3. **URL Structure**
```typescript
// ✅ Đảm bảo 'only' parameter khớp với backend response structure
const filters = useTableFilters(
    '/units',
    initialFilters,
    ['units', 'filters'] // Backend trả về { units: {...}, filters: {...} }
);
```

### 4. **Error Handling**
```typescript
// Composable đã tự động handle các lỗi URL parsing
// Không cần thêm try-catch khi sử dụng
```

## Troubleshooting

### 1. **Filters không được preserve khi pagination**
**Nguyên nhân:** Sai `only` parameter  
**Giải pháp:** Đảm bảo `only` array khớp với backend response keys

### 2. **URL không sync với filters**
**Nguyên nhân:** Backend không trả về filters trong response  
**Giải pháp:** Đảm bảo controller trả về filters trong Inertia response

### 3. **Type errors với filters**
**Nguyên nhân:** Không định nghĩa interface hoặc sai generic type  
**Giải pháp:** Định nghĩa interface chi tiết và sử dụng generic type

### 4. **Performance issues với search**
**Nguyên nhân:** Không sử dụng debounce  
**Giải pháp:** Sử dụng `updateFieldDebounced` cho text input

## Kết luận

`useFilters` composable cung cấp một solution hoàn chỉnh và đơn giản để quản lý filtering trong ứng dụng Vue.js + Inertia.js. Với API đơn giản nhưng mạnh mẽ, nó giúp developer tập trung vào logic business thay vì phải lo về URL manipulation và state management phức tạp.

**Ưu điểm chính:**
- 🎯 **Đơn giản**: API trực quan, dễ sử dụng
- 🚀 **Mạnh mẽ**: Đầy đủ tính năng cần thiết 
- 🔒 **Type Safe**: Hỗ trợ TypeScript hoàn chỉnh
- 📈 **Performance**: Debouncing và optimized state management
- 🔄 **Reliable**: Automatic error handling và fallbacks

Sử dụng composable này sẽ giúp code của bạn cleaner, maintainable hơn và giảm đáng kể boilerplate code!
