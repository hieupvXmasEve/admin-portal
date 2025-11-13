<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { useApi } from '@/composables';
import { Link } from '@inertiajs/vue3';
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { Loader2, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

// API composable
const api = useApi();

// Interface for search result item (matches API response structure)
interface StudentSearchItem {
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
interface StudentSearchResponse {
    items: StudentSearchItem[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        has_more_pages: boolean;
    };
}

// Search state
const searchQuery = ref('');
const searchResults = ref<StudentSearchItem[]>([]);
const isLoading = ref(false);
const isDropdownOpen = ref(false);
const searchError = ref<string | null>(null);
const lastSearchQuery = ref<string>('');
const searchContainerRef = ref<HTMLDivElement | null>(null);

// Search function
const performSearch = async (query: string) => {
    const trimmedQuery = query.trim();

    if (trimmedQuery.length < 3) {
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
            limit: 5,
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
    if (newValue.length >= 3) {
        isDropdownOpen.value = true;
        debouncedSearch(newValue);
    } else {
        searchResults.value = [];
        searchError.value = null;
        lastSearchQuery.value = '';
        isDropdownOpen.value = false;
    }
});

// Watch search results - keep dropdown open when results arrive
watch([searchResults, isLoading, searchError], () => {
    if (isLoading.value || searchError.value || searchResults.value.length > 0) {
        isDropdownOpen.value = true;
    }
});

// Handle input focus - open dropdown if there's content
const handleInputFocus = () => {
    if (searchQuery.value.length > 0) {
        isDropdownOpen.value = true;
    }
};

// Handle click outside to close dropdown
onClickOutside(searchContainerRef, () => {
    isDropdownOpen.value = false;
});

// Get student initials for avatar fallback
const getStudentInitials = (student: StudentSearchItem) => {
    return student.full_name
        .split(' ')
        .map((name) => name.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2);
};
</script>

<template>
    <div ref="searchContainerRef" class="relative w-72 flex-1">
        <Search class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2 z-10" />
        <Input
            v-model="searchQuery"
            type="text"
            placeholder="Search students..."
            class="pr-4 pl-9"
            @focus="handleInputFocus"
        />
        
        <!-- Dropdown results -->
        <div
            v-if="isDropdownOpen"
            class="bg-popover text-popover-foreground absolute top-full left-0 z-50 mt-2 w-full rounded-md border shadow-md"
        >
            <!-- Loading state -->
            <div v-if="isLoading" class="flex items-center justify-center py-6">
                <Loader2 class="size-4 animate-spin" />
                <span class="text-muted-foreground ml-2 text-sm">Searching...</span>
            </div>

            <!-- Error state -->
            <div v-else-if="searchError" class="flex items-center justify-center px-4 py-6">
                <span class="text-destructive text-sm">{{ searchError }}</span>
            </div>

            <!-- Results list -->
            <div v-else-if="searchResults.length > 0" class="max-h-[300px] overflow-y-auto">
                <Link
                    v-for="student in searchResults"
                    :key="student.id"
                    :href="route('students.academic-summary.overview', { student: student.id })"
                    class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 border-b px-4 py-3 transition-colors last:border-b-0"
                    @click="isDropdownOpen = false"
                >
                    <Avatar class="size-10 shrink-0">
                        <AvatarImage v-if="student.avatar_url" :src="student.avatar_url" :alt="student.full_name" />
                        <AvatarFallback>{{ getStudentInitials(student) }}</AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ student.full_name }}</p>
                        <p class="text-muted-foreground truncate text-xs">{{ student.student_id }}</p>
                        <p v-if="student.program" class="text-muted-foreground truncate text-xs">
                            {{ student.program.name }}
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Empty/No results state -->
            <div v-else-if="searchQuery.length >= 3" class="flex items-center justify-center px-4 py-6">
                <span class="text-muted-foreground text-sm">No students found</span>
            </div>

            <!-- Minimum characters message -->
            <div v-else-if="searchQuery.length > 0 && searchQuery.length < 3" class="flex items-center justify-center px-4 py-6">
                <span class="text-muted-foreground text-sm">Type at least 3 characters to search</span>
            </div>

            <!-- Default state - show placeholder -->
            <div v-else class="flex items-center justify-center px-4 py-6">
                <span class="text-muted-foreground text-sm">Start typing to search students...</span>
            </div>
        </div>
    </div>
</template>
