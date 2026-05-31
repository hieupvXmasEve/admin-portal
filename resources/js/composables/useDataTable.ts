import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';

// ─── Types ───────────────────────────────────────────────────────────────────

export type FilterValue = string | number | boolean | string[] | null;
export type SortDirection = 'asc' | 'desc' | null;

export interface DataTableConfig<T extends Record<string, any> = Record<string, FilterValue>> {
    /** Base URL for navigation — use route() helper */
    baseUrl: string;
    /** Initial filter values from server props */
    initialFilters?: T;
    /** Default values — excluded from URL when unchanged */
    defaultValues?: Partial<T>;
    /** Inertia partial reload keys (e.g. ['buildings', 'filters']) */
    only?: string[];
    /** Global debounce delay in ms @default 300 */
    debounce?: number;
    /** Per-field debounce overrides (e.g. { search: 400 }) */
    fieldDebounce?: Record<string, number>;
    /** Fields that navigate immediately — use for selects, toggles */
    immediateFields?: string[];
    /** Preserve scroll position @default true */
    preserveScroll?: boolean;
    /** Replace history entry instead of push @default true */
    replace?: boolean;
    /** Callback on navigation error */
    onError?: (error: unknown) => void;
    /** Callback on navigation success */
    onSuccess?: () => void;
}

// ─── Composable ──────────────────────────────────────────────────────────────

/**
 * Unified data table composable for Inertia v3 filter/sort/pagination pages.
 *
 * Replaces useInertiaFilters and useServerTableQuery. Uses router.get() with
 * partial reload, per-field debounce, and clean URL param building.
 *
 * @example
 * const { filters, setFilter, setSort, handleSearch, hasActiveFilters } = useDataTable<Filters>({
 *     baseUrl: route('campuses.show', campus.id),
 *     initialFilters: { search: '', sort: null, direction: null, per_page: 15 },
 *     defaultValues: { per_page: 15, search: '', sort: null, direction: null },
 *     only: ['buildings', 'filters'],
 * });
 */
export function useDataTable<T extends Record<string, any> = Record<string, FilterValue>>(config: DataTableConfig<T>) {
    const { baseUrl, initialFilters = {} as T, defaultValues = {} as Partial<T>, only, debounce = 300, fieldDebounce = {}, immediateFields = [], preserveScroll = true, replace = true, onError, onSuccess } = config;

    const filters = reactive<T>({ ...initialFilters } as T);
    const isNavigating = ref(false);

    // Alias for internal mutations that bypass T's strict typing
    const raw = filters as Record<string, FilterValue>;
    const defaults = defaultValues as Record<string, FilterValue>;

    // ── URL param builder ────────────────────────────────────────────────────

    /** Build clean query params — excludes defaults, nulls, empty strings, page=1 */
    const buildParams = (): Record<string, string | number | boolean | string[]> => {
        const params: Record<string, string | number | boolean | string[]> = {};

        for (const [key, value] of Object.entries(raw)) {
            if (value === null || value === undefined || value === '') continue;
            if (key === 'page' && value === 1) continue;
            if (defaults[key] !== undefined && value === defaults[key]) continue;

            if (Array.isArray(value)) {
                if (value.length > 0) params[key] = value;
            } else if (['string', 'number', 'boolean'].includes(typeof value)) {
                params[key] = value;
            }
        }

        return params;
    };

    const buildUrl = (): string => {
        const url = new URL(baseUrl, window.location.origin);
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries(buildParams())) {
            if (Array.isArray(value)) {
                value.forEach((item) => params.append(`${key}[]`, String(item)));
            } else {
                params.set(key, String(value));
            }
        }

        const query = params.toString();

        return `${url.pathname}${query ? `?${query}` : ''}${url.hash}`;
    };

    // ── Navigation ───────────────────────────────────────────────────────────

    const navigate = () => {
        if (isNavigating.value) return;
        isNavigating.value = true;

        router.visit(buildUrl(), {
            only,
            preserveState: true,
            preserveScroll,
            replace,
            onFinish: () => {
                isNavigating.value = false;
            },
            onSuccess: () => onSuccess?.(),
            onError: (errors) => onError?.(errors),
        });
    };

    // Per-field debounced function cache
    const debouncedFns = new Map<string, ReturnType<typeof useDebounceFn>>();

    const getNavigateFn = (field?: string): (() => void) => {
        if (field && immediateFields.includes(field)) return navigate;
        const delay = field && fieldDebounce[field] ? fieldDebounce[field] : debounce;
        const cacheKey = field ? `${field}:${delay}` : `_:${delay}`;
        if (!debouncedFns.has(cacheKey)) {
            debouncedFns.set(cacheKey, useDebounceFn(navigate, delay));
        }
        return debouncedFns.get(cacheKey)!;
    };

    // ── Core methods ─────────────────────────────────────────────────────────

    /** Set a single filter and trigger (debounced) navigation. Resets page to 1. */
    const setFilter = <K extends keyof T & string>(key: K, value: T[K]) => {
        raw[key] = value;
        if (key !== 'page') raw.page = 1;
        getNavigateFn(key)();
    };

    /** Clear a single filter to its default (or null) */
    const clearFilter = (key: keyof T & string) => {
        raw[key] = (defaults[key] ?? null) as FilterValue;
        raw.page = 1;
        getNavigateFn(key)();
    };

    /** Set sort field + direction — navigates immediately */
    const setSort = (field: string | null, direction: SortDirection) => {
        raw.sort = field;
        raw.direction = direction;
        raw.page = 1;
        navigate();
    };

    /** Clear sort */
    const clearSort = () => setSort(null, null);

    /** Go to specific page — navigates immediately */
    const setPage = (page: number) => {
        raw.page = page;
        navigate();
    };

    /** Change page size — navigates immediately, resets to page 1 */
    const setPerPage = (perPage: number) => {
        raw.per_page = perPage;
        raw.page = 1;
        navigate();
    };

    /** Clear all filters to defaults and navigate */
    const clearAllFilters = () => {
        for (const key of Object.keys(raw)) {
            raw[key] = (defaults[key] ?? null) as FilterValue;
        }
        navigate();
    };

    /** Batch-apply filter changes — for pages with an explicit "Apply" button */
    const apply = (newFilters?: Partial<T>) => {
        if (newFilters) Object.assign(filters, newFilters);
        raw.page = 1;
        navigate();
    };

    /** Re-fetch current data without changing filters */
    const refresh = () => navigate();

    // ── Convenience handlers for UI components ───────────────────────────────

    /** For DebouncedInput @update:model-value */
    const handleSearch = (value: string | number) => {
        setFilter('search' as keyof T & string, String(value) as T[keyof T & string]);
    };

    /** For DataTable @sort-change */
    const handleSortChange = (sort: string | null, direction: SortDirection) => setSort(sort, direction);

    /** For DataPagination @navigate — parses page number from URL */
    const handlePaginationNavigate = (url: string) => {
        try {
            const urlObj = url.startsWith('http') ? new URL(url) : new URL(url, window.location.origin);
            const page = urlObj.searchParams.get('page');
            if (page) setPage(parseInt(page, 10));
        } catch {
            router.visit(url, { preserveState: true, preserveScroll, only, replace });
        }
    };

    /** For DataPagination @page-size-change */
    const handlePageSizeChange = (size: number) => setPerPage(size);

    // ── Computed ──────────────────────────────────────────────────────────────

    /** True when any filter differs from its default (excludes page/per_page) */
    const hasActiveFilters = computed(() =>
        Object.entries(raw).some(([key, value]) => {
            if (value === null || value === undefined || value === '') return false;
            if (key === 'page' || key === 'per_page') return false;
            const def = defaults[key];
            if (def !== undefined) {
                return Array.isArray(value) ? value.length > 0 && JSON.stringify(value) !== JSON.stringify(def) : value !== def;
            }
            return Array.isArray(value) ? value.length > 0 : true;
        }),
    );

    const isLoading = computed(() => isNavigating.value);
    const currentSort = computed(() => (typeof raw.sort === 'string' ? raw.sort : null));
    const currentDirection = computed((): SortDirection => (raw.direction === 'asc' || raw.direction === 'desc' ? raw.direction : null));

    return {
        // State
        filters,

        // Core methods
        setFilter,
        clearFilter,
        clearAllFilters,
        setSort,
        clearSort,
        setPage,
        setPerPage,
        apply,
        refresh,

        // Convenience handlers (DataTable, DataPagination, DebouncedInput)
        handleSearch,
        handleSortChange,
        handlePaginationNavigate,
        handlePageSizeChange,

        // Computed
        hasActiveFilters,
        isLoading,
        currentSort,
        currentDirection,
    };
}
