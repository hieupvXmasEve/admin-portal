import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';

export interface FilterOptions<T = Record<string, any>> {
    baseIndexUrl: string;
    initialFilters?: T;
    defaultValues?: Partial<T>; // Values to exclude from URL (e.g., per_page: 15, direction: 'asc')
    only?: string[];
    method?: 'get' | 'visit';
}

/**
 * Unified filters composable - simple and powerful
 * Handles URL synchronization, pagination, and filtering
 * 
 * @example
 * ```ts
 * const { filters, handleSearch, handleSelectFilter, clearFilters } = useFilters({
 *     baseIndexUrl: '/units',
 *     initialFilters: { search: '', type: '', level: null, per_page: 15 },
 *     defaultValues: { per_page: 15, direction: 'asc' }, // Exclude from URL
 *     only: ['units', 'filters']
 * });
 * ```
 */
export function useFilters<T extends Record<string, any>>(options: FilterOptions<T>) {
    const { 
        baseIndexUrl, 
        initialFilters = {} as T, 
        defaultValues = {}, 
        only = ['items', 'filters'], 
        method = 'visit' 
    } = options;
    
    const filters = ref<T>({ ...initialFilters } as T);

    /**
     * Check if any filters are active (excluding default values)
     */
    const hasActiveFilters = computed(() => {
        return Object.entries(filters.value).some(([key, value]) => {
            const defaultValue = defaultValues[key as keyof T];
            
            // If has default value, compare with it
            if (defaultValue !== undefined) {
                if (Array.isArray(value)) {
                    return value.length > 0 && JSON.stringify(value) !== JSON.stringify(defaultValue);
                }
                return value !== defaultValue && value !== '' && value !== null && value !== undefined;
            }
            
            // No default value, check if has any value
            if (Array.isArray(value)) return value.length > 0;
            return value !== undefined && value !== null && String(value) !== '';
        });
    });

    /**
     * Build URL with filters, excluding default values
     */
    const buildUrl = (filterParams: T) => {
        const params = new URLSearchParams();
        
        Object.entries(filterParams).forEach(([key, value]) => {
            const defaultValue = defaultValues[key as keyof T];
            
            // Skip if undefined, null, empty
            if (value === undefined || value === null || value === '') return;
            
            // Skip if equals default value
            if (defaultValue !== undefined) {
                if (Array.isArray(value) && JSON.stringify(value) === JSON.stringify(defaultValue)) return;
                if (value === defaultValue) return;
            }
            
            if (Array.isArray(value)) {
                value.forEach(v => params.append(key, String(v)));
            } else {
                params.set(key, String(value));
            }
        });

        const query = params.toString();
        return `${baseIndexUrl}${query ? `?${query}` : ''}`;
    };

    const navigate = (filterParams: T, replace = true) => {
        const url = buildUrl(filterParams);
        const navOptions = {
            preserveState: true,
            preserveScroll: true,
            only,
            replace,
        };

        if (method === 'get') {
            router.get(url, {}, navOptions);
        } else {
            router.visit(url, navOptions);
        }
    };

    const applyFilters = (newFilters: Partial<T>) => {
        filters.value = { ...filters.value, ...newFilters };
        navigate(filters.value);
    };

    const debouncedApplyFilters = useDebounceFn((newFilters: Partial<T>) => {
        applyFilters(newFilters);
    }, 500);

    const clearFilters = () => {
        filters.value = { ...initialFilters } as T;
        navigate(filters.value);
    };

    /**
     * Handle pagination navigation from DataPagination component
     */
    const handlePaginationNavigate = (url: string) => {
        try {
            // Handle both absolute and relative URLs
            let urlObj: URL;
            if (url.startsWith('http')) {
                urlObj = new URL(url);
            } else {
                // For relative URLs, use current origin
                urlObj = new URL(url, window.location.origin);
            }
            
            const page = urlObj.searchParams.get('page');
            
            if (page && !isNaN(parseInt(page))) {
                // FIXED: Use 'page' not 'per_page'
                applyFilters({ page: parseInt(page) } as Partial<T>);
            } else {
                // Fallback to direct navigation if page parameter not found or invalid
                router.visit(url, {
                    preserveState: true,
                    preserveScroll: true,
                    only,
                    replace: true,
                });
            }
        } catch (error) {
            console.error('Error parsing pagination URL:', url, error);
            // Fallback to direct navigation if URL parsing fails
            router.visit(url, {
                preserveState: true,
                preserveScroll: true,
                only,
                replace: true,
            });
        }
    };

    const handlePageSizeChange = (size: number) => {
        applyFilters({ per_page: size } as Partial<T>);
    };

    // Helper methods for common operations
    const updateField = (key: keyof T, value: any) => {
        applyFilters({ [key]: value } as Partial<T>);
    };

    const updateFieldDebounced = (key: keyof T, value: any) => {
        debouncedApplyFilters({ [key]: value } as Partial<T>);
    };

    /**
     * Handle search input from DebouncedInput component
     * Automatically converts to string and applies filters
     * 
     * @example
     * ```vue
     * <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" />
     * ```
     */
    const handleSearch = (value: string | number) => {
        filters.value = { ...filters.value, search: String(value) } as T;
        applyFilters({ search: String(value) } as Partial<T>);
    };

    /**
     * Handle Select component with "all" option support
     * Automatically converts "all" to empty string
     * 
     * @param key - Filter field name
     * @param value - Selected value
     * @param allValue - Value that represents "all" (default: 'all')
     * 
     * @example
     * ```vue
     * <Select v-model="filters.type" @update:model-value="(v) => handleSelectFilter('type', v)">
     * ```
     */
    const handleSelectFilter = (key: keyof T, value: any, allValue: any = 'all') => {
        const filterValue = value === allValue ? '' : value;
        filters.value = { ...filters.value, [key]: filterValue } as T;
        applyFilters({ [key]: filterValue } as Partial<T>);
    };

    const appendCurrentQueryTo = (url: string) => {
        const search = typeof window !== 'undefined' ? window.location.search : '';
        return `${url}${search || ''}`;
    };

    return {
        // State
        filters,
        hasActiveFilters,
        
        // Core actions
        applyFilters,
        debouncedApplyFilters,
        clearFilters,
        
        // Pagination
        handlePaginationNavigate,
        handlePageSizeChange,
        
        // Field updates
        updateField,
        updateFieldDebounced,
        
        // Common handlers (NEW)
        handleSearch,
        handleSelectFilter,
        
        // Utilities
        appendCurrentQueryTo,
    };
}

/**
 * Quick helper for standard table filters
 */
export function useTableFilters<T extends Record<string, any>>(
    baseIndexUrl: string,
    initialFilters?: Record<string, any>,
    only: string[] = ['items', 'filters']
) {
    return useFilters<T>({
        baseIndexUrl,
        initialFilters,
        only,
        method: 'visit'
    });
}
