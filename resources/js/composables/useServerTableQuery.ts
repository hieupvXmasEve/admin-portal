import { type InertiaFilterOptions, useInertiaFilters } from '@/composables/useInertiaFilters';
import { useDebounceFn } from '@vueuse/core';
import { computed } from 'vue';

type SortDirection = 'asc' | 'desc' | null;

interface BaseServerTableFilters {
    search?: string;
    sort?: string | null;
    direction?: SortDirection;
    page?: number;
    per_page?: number;
}

export interface ServerTableQueryOptions<T extends BaseServerTableFilters & Record<string, any>> extends InertiaFilterOptions<T> {
    searchDebounce?: number;
}

export function useServerTableQuery<T extends BaseServerTableFilters & Record<string, any>>(options: ServerTableQueryOptions<T>) {
    const { searchDebounce = 300, ...inertiaOptions } = options;

    const { filters, hasActiveFilters, clearFilters, applyFilters, updateFilter, currentSort, currentDirection } = useInertiaFilters<T>({
        ...inertiaOptions,
        autoSync: false,
    });

    const apply = (payload?: Partial<T>) => {
        applyFilters(payload);
    };

    const setFilter = <K extends keyof T>(key: K, value: T[K]) => {
        updateFilter(key, value);
    };

    const applySearch = useDebounceFn((value: string) => {
        applyFilters({
            search: value as T[keyof T],
            page: 1 as T[keyof T],
        } as Partial<T>);
    }, searchDebounce);

    const handleSortChange = (sort: string | null, direction: SortDirection) => {
        applyFilters({
            sort: sort as T[keyof T],
            direction: direction as T[keyof T],
            page: 1 as T[keyof T],
        } as Partial<T>);
    };

    const handlePageChange = (page: number) => {
        applyFilters({
            page: page as T[keyof T],
        } as Partial<T>);
    };

    const handlePageSizeChange = (size: number) => {
        applyFilters({
            per_page: size as T[keyof T],
            page: 1 as T[keyof T],
        } as Partial<T>);
    };

    return {
        filters,
        hasActiveFilters,
        clearFilters,
        apply,
        setFilter,
        applySearch,
        handleSortChange,
        handlePageChange,
        handlePageSizeChange,
        currentSort: computed(() => currentSort.value || undefined),
        currentDirection: computed(() => currentDirection.value || undefined),
    };
}
