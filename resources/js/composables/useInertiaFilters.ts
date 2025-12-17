import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';

export interface InertiaFilterOptions<T = Record<string, any>> {
    /**
     * Base URL for the index route
     * @example '/students' or studentRoutes.list()
     */
    baseUrl: string;

    /**
     * Initial filter values from props
     */
    initialFilters?: T;

    /**
     * Empty filter values (for clear filters)
     * If not provided, will construct from defaultValues
     * @example { search: '', program_id: 'all', status: 'all', sort: null, direction: null }
     */
    emptyFilters?: T;

    /**
     * Default values that should not appear in URL
     * @example { per_page: 15, direction: 'asc' }
     */
    defaultValues?: Partial<T>;

    /**
     * Inertia partial reload keys
     * @default ['items', 'filters']
     */
    only?: string[];

    /**
     * Debounce delay in ms
     * @default 400
     */
    debounce?: number;

    /**
     * Enable auto-sync with URL (watch mode)
     * @default true
     */
    autoSync?: boolean;

    /**
     * Use replace instead of push for history
     * @default true
     */
    replace?: boolean;

    /**
     * Custom transform before navigation
     * Useful for converting data types
     */
    transform?: (filters: T) => Record<string, any>;
}

/**
 * Unified Inertia filters composable with auto URL sync
 *
 * @example Basic usage
 * ```ts
 * const { filters, hasActiveFilters, clearFilters } = useInertiaFilters({
 *     baseUrl: studentRoutes.list(),
 *     initialFilters: props.filters,
 *     defaultValues: { per_page: 15, direction: 'asc' },
 *     only: ['students', 'filters']
 * });
 *
 * // Auto syncs to URL when changed
 * filters.search = 'john';
 * filters.status = 'active';
 * ```
 *
 * @example With sorting
 * ```ts
 * const { filters, handleSortChange } = useInertiaFilters({
 *     baseUrl: '/students',
 *     initialFilters: {
 *         search: props.filters.search,
 *         sort: props.filters.sort,
 *         direction: props.filters.direction
 *     },
 *     defaultValues: { direction: 'asc' }
 * });
 *
 * // Use with DataTable
 * <DataTable
 *     @sort-change="handleSortChange"
 *     :initial-sort="filters.sort"
 *     :initial-direction="filters.direction"
 * />
 * ```
 */
export function useInertiaFilters<T extends Record<string, any>>(options: InertiaFilterOptions<T>) {
    const { baseUrl, initialFilters = {} as T, emptyFilters, defaultValues = {}, only = ['items', 'filters'], debounce = 300, autoSync = true, replace = true, transform } = options;

    // Use reactive for auto-tracking
    const filters = reactive<T>({ ...initialFilters } as T);

    // Track if we're currently navigating to prevent loops
    const isNavigating = ref(false);

    // Construct empty state from emptyFilters or defaultValues
    const emptyState = emptyFilters || ({ ...defaultValues } as T);

    /**
     * Check if any filters are active (excluding defaults)
     */
    const hasActiveFilters = computed(() => {
        return Object.entries(filters).some(([key, value]) => {
            const defaultValue = defaultValues[key as keyof T];

            // Skip empty values
            if (value === undefined || value === null || value === '') return false;

            // If has default value, check if different
            if (defaultValue !== undefined) {
                if (Array.isArray(value)) {
                    return value.length > 0 && JSON.stringify(value) !== JSON.stringify(defaultValue);
                }
                return value !== defaultValue;
            }

            // No default, any truthy value counts as active
            if (Array.isArray(value)) return value.length > 0;
            return String(value) !== '';
        });
    });

    /**
     * Build clean URL with filters (excludes defaults)
     */
    const buildUrl = (filterParams: Record<string, any>) => {
        const params = new URLSearchParams();

        // Apply transform if provided
        const transformed = transform ? transform(filterParams as T) : filterParams;

        Object.entries(transformed).forEach(([key, value]) => {
            const defaultValue = defaultValues[key as keyof T];

            // Skip undefined, null, empty
            if (value === undefined || value === null || value === '') return;

            // Skip if equals default value
            if (defaultValue !== undefined) {
                if (Array.isArray(value) && JSON.stringify(value) === JSON.stringify(defaultValue)) return;
                if (value === defaultValue) return;
            }

            // Handle arrays
            if (Array.isArray(value)) {
                value.forEach((v) => params.append(key.endsWith('[]') ? key : `${key}[]`, String(v)));
            } else {
                params.set(key, String(value));
            }
        });

        const query = params.toString();
        return `${baseUrl}${query ? `?${query}` : ''}`;
    };

    /**
     * Navigate with current filters
     */
    const navigate = () => {
        if (isNavigating.value) {
            return;
        }

        isNavigating.value = true;
        const url = buildUrl(filters);

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only,
            replace,
            onFinish: () => {
                isNavigating.value = false;
            },
        });
    };

    /**
     * Manually apply filters (useful when autoSync is false)
     */
    const applyFilters = (newFilters?: Partial<T>) => {
        if (newFilters) {
            Object.assign(filters, newFilters);
        }
        navigate();
    };

    /**
     * Clear all filters back to empty state
     */
    const clearFilters = () => {
        Object.assign(filters, emptyState);
        if (!isNavigating.value) {
            navigate();
        }
    };

    /**
     * Update a single filter field
     */
    const updateFilter = <K extends keyof T>(key: K, value: T[K]) => {
        filters[key] = value;
        if (!autoSync) navigate();
    };

    /**
     * Handle search from DebouncedInput
     */
    const handleSearch = (value: string | number) => {
        filters.search = String(value) as any;
    };

    /**
     * Handle Select with "all" option
     */
    const handleSelectFilter = <K extends keyof T>(key: K, value: any, allValue: any = 'all') => {
        filters[key] = (value === allValue ? '' : value) as T[K];
    };

    /**
     * Handle sorting from DataTable
     * Note: Sorting navigates immediately (no debounce)
     */
    const handleSortChange = (sort: string | null, direction: 'asc' | 'desc' | null) => {
        filters.sort = sort as any;
        filters.direction = direction as any;
        // Navigate immediately for sorting (bypass debounce)
        if (!isNavigating.value) {
            navigate();
        }
    };

    /**
     * Handle pagination navigate
     */
    const handlePaginationNavigate = (url: string) => {
        try {
            const urlObj = url.startsWith('http') ? new URL(url) : new URL(url, window.location.origin);

            const page = urlObj.searchParams.get('page');
            if (page) {
                filters.page = parseInt(page) as any;
            }
        } catch (error) {
            // Fallback to direct navigation
            router.visit(url, {
                preserveState: true,
                preserveScroll: true,
                only,
                replace,
            });
        }
    };

    /**
     * Handle page size change
     */
    const handlePageSizeChange = (size: number) => {
        filters.per_page = size as any;
        filters.page = 1 as any; // Reset to first page
    };

    // Auto-sync with URL when filters change
    if (autoSync) {
        watchDebounced(
            filters,
            () => {
                if (!isNavigating.value) {
                    navigate();
                }
            },
            { debounce, deep: true },
        );
    }

    // Computed sort props for DataTable (optional convenience)
    const currentSort = computed(() => (filters.sort as any) || undefined);
    const currentDirection = computed(() => (filters.direction as any) || undefined);

    return {
        // State
        filters,
        hasActiveFilters,

        // Core actions
        applyFilters,
        clearFilters,
        updateFilter,

        // Common handlers
        handleSearch,
        handleSelectFilter,
        handleSortChange,

        // Pagination
        handlePaginationNavigate,
        handlePageSizeChange,

        // Sorting (for DataTable)
        currentSort,
        currentDirection,

        // Utils
        buildUrl,
    };
}

/**
 * Quick helper for standard table with filters
 */
export function useTableFilters<T extends Record<string, any>>(baseUrl: string, initialFilters: T, only: string[] = ['items', 'filters']) {
    return useInertiaFilters<T>({
        baseUrl,
        initialFilters,
        only,
        defaultValues: {
            per_page: 15,
            direction: 'asc',
        } as Partial<T>,
    });
}
