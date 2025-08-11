import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';

export interface FilterOptions {
    baseIndexUrl: string;
    initialFilters?: Record<string, any>;
    only?: string[];
    method?: 'get' | 'visit';
}

/**
 * Unified filters composable - simple and powerful
 * Handles URL synchronization, pagination, and filtering
 */
export function useFilters<T extends Record<string, any>>(options: FilterOptions) {
    const { baseIndexUrl, initialFilters = {}, only = ['items', 'filters'], method = 'visit' } = options;
    
    const filters = ref<T>({ ...initialFilters } as T);

    const hasActiveFilters = computed(() => {
        return Object.values(filters.value).some(value => {
            if (Array.isArray(value)) return value.length > 0;
            return value !== undefined && value !== null && String(value) !== '';
        });
    });

    const buildUrl = (filterParams: T) => {
        const params = new URLSearchParams();
        
        Object.entries(filterParams).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '') return;
            
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
                // Apply current filters with the new page number
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

    const appendCurrentQueryTo = (url: string) => {
        const search = typeof window !== 'undefined' ? window.location.search : '';
        return `${url}${search || ''}`;
    };

    return {
        filters,
        hasActiveFilters,
        applyFilters,
        debouncedApplyFilters,
        clearFilters,
        handlePaginationNavigate,
        handlePageSizeChange,
        updateField,
        updateFieldDebounced,
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
