import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import { useApi } from './index';

// Interface for search result item (matches API response structure)
export interface StudentSearchItem {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    avatar_url: string | null;
    status: string;
    program: {
        id: number;
        name: string;
    } | null;
    campus: {
        id: number;
        name: string;
    } | null;
}

// Interface for API response
export interface StudentSearchResponse {
    items: StudentSearchItem[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        has_more_pages: boolean;
    };
}

export interface StudentSearchOptions {
    limit?: number;
    status?: string;
    minChars?: number;
    immediate?: boolean;
}

export function useStudentSearch(options: StudentSearchOptions = {}) {
    const { limit = 5, minChars = 3 } = options;

    const api = useApi();

    // State
    const searchQuery = ref('');
    const searchResults = ref<StudentSearchItem[]>([]);
    const isLoading = ref(false);
    const searchError = ref<string | null>(null);
    const lastSearchQuery = ref<string>('');

    // Search function
    const performSearch = async (query: string) => {
        const trimmedQuery = query.trim();

        if (trimmedQuery.length < minChars) {
            searchResults.value = [];
            searchError.value = null;
            lastSearchQuery.value = '';
            return;
        }

        // Don't search again if query hasn't changed and we already have results
        if (lastSearchQuery.value === trimmedQuery && searchResults.value.length > 0) {
            return;
        }

        isLoading.value = true;
        searchError.value = null;
        lastSearchQuery.value = trimmedQuery;

        try {
            const response = await api.get<StudentSearchResponse>('/api/students/search', {
                query: trimmedQuery,
                limit,
                status,
            });

            const responseData = response.data.value;

            if (responseData?.success) {
                searchResults.value = responseData.data.items;
            } else {
                throw new Error(responseData?.message || 'Failed to search students');
            }
        } catch (err) {
            console.error('Error searching students:', err);
            searchError.value = err instanceof Error ? err.message : 'Failed to search students';
            searchResults.value = [];
            lastSearchQuery.value = '';
        } finally {
            isLoading.value = false;
        }
    };

    // Debounced search function
    const debouncedSearch = useDebounceFn((query: string) => {
        performSearch(query);
    }, 300);

    // Watch search query changes
    watch(searchQuery, (newValue) => {
        if (newValue.length >= minChars) {
            debouncedSearch(newValue);
        } else {
            searchResults.value = [];
            searchError.value = null;
            lastSearchQuery.value = '';
        }
    });

    const reset = () => {
        searchQuery.value = '';
        searchResults.value = [];
        searchError.value = null;
        lastSearchQuery.value = '';
    };

    return {
        searchQuery,
        searchResults,
        isLoading,
        searchError,
        performSearch,
        reset,
    };
}
