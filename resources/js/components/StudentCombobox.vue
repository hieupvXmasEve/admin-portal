<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
    ComboboxViewport,
} from '@/components/ui/combobox';
import { useApi } from '@/composables';
import { cn } from '@/lib/utils';
import type { Student } from '@/types/models';
import { useDebounceFn } from '@vueuse/core';
import { AlertCircle, Check, ChevronsUpDown, Loader2, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Props {
    modelValue?: string | number | null;
    placeholder?: string;
    disabled?: boolean;
    errorMessage?: string;
    required?: boolean;
    status?: 'active' | 'inactive' | 'suspended';
    class?: string;
}

interface StudentSearchData {
    items: Student[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        has_more_pages: boolean;
    };
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select student...',
    disabled: false,
    required: false,
    status: 'active',
    class: '',
});

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
    select: [student: Student | null];
}>();

// API composable
const api = useApi();

// Component state
const open = ref(false);
const searchQuery = ref('');
const students = ref<Student[]>([]);
const selectedStudent = ref<Student | null>(null);
const loading = ref(false);
const apiError = ref<string | null>(null);

// Computed properties
const displayValue = computed(() => {
    if (selectedStudent.value) {
        return `${selectedStudent.value.full_name} (${selectedStudent.value.student_id})`;
    }
    return props.placeholder;
});

const showMinimumCharactersMessage = computed(() => {
    return searchQuery.value.length > 0 && searchQuery.value.length < 2;
});

const showInitialMessage = computed(() => {
    return searchQuery.value.length === 0 && students.value.length === 0;
});

// API functions
const fetchStudents = async (query: string) => {
    // Only fetch if query has at least 2 characters
    if (query.length < 2) {
        students.value = [];
        apiError.value = null;
        return;
    }

    // Clear previous results and error
    students.value = [];
    apiError.value = null;
    loading.value = true;

    try {
        const params: Record<string, any> = {
            page: '1',
            limit: '20',
            status: props.status,
            query: query.trim(),
        };

        const response = await api.get<StudentSearchData>('/api/students/search', params);
        const responseData = response.data.value;

        if (responseData?.success) {
            students.value = responseData.data.items;
        } else {
            throw new Error(responseData?.message || 'Failed to fetch students');
        }
    } catch (err) {
        console.error('Error fetching students:', err);
        apiError.value = err instanceof Error ? err.message : 'Failed to fetch students';
    } finally {
        loading.value = false;
    }
};

const fetchStudentById = async (id: string | number) => {
    if (!id) return;

    try {
        const response = await api.get<Student>(`/api/students/${id}`);
        const responseData = response.data.value;

        if (responseData?.success) {
            selectedStudent.value = responseData.data;
            emit('select', responseData.data);
        }
    } catch (err) {
        console.error('Error fetching student by ID:', err);
    }
};

// Debounced search function with minimum character requirement
const debouncedSearch = useDebounceFn((query: string) => {
    fetchStudents(query);
}, 300);

// Event handlers
const handleSelect = (value: any) => {
    const student = value as Student | null;
    if (student) {
        selectedStudent.value = student;
        searchQuery.value = '';
        open.value = false;
        emit('update:modelValue', student.id);
        emit('select', student);
    }
};

const handleClear = (event: Event) => {
    event.stopPropagation();
    selectedStudent.value = null;
    searchQuery.value = '';
    students.value = [];
    emit('update:modelValue', null);
    emit('select', null);
};

const getStudentInitials = (student: Student) => {
    return student.full_name
        .split(' ')
        .map((name) => name.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const getEnrollmentStatusColor = (status: string) => {
    switch (status) {
        case 'active':
        case 'enrolled':
            return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
        case 'admitted':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
        case 'on_leave':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
        case 'suspended':
        case 'dropped_out':
            return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400';
        case 'graduated':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
    }
};

// Watchers
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue && newValue !== selectedStudent.value?.id) {
            fetchStudentById(newValue);
        } else if (!newValue) {
            selectedStudent.value = null;
        }
    },
    { immediate: true },
);

// Lifecycle
onMounted(() => {
    if (props.modelValue) {
        fetchStudentById(props.modelValue);
    }
});
</script>

<template>
    <div class="w-full space-y-1">
        <Combobox
            v-model="selectedStudent"
            by="id"
            v-model:open="open"
            v-model:search-term="searchQuery"
            @update:model-value="handleSelect"
            :disabled="props.disabled"
        >
            <ComboboxAnchor as-child>
                <ComboboxTrigger as-child>
                    <Button
                        variant="outline"
                        class="w-full justify-between"
                        :class="cn(props.errorMessage && 'border-destructive', props.class)"
                        :disabled="props.disabled"
                    >
                        <span class="truncate">{{ displayValue }}</span>

                        <div class="flex items-center gap-1">
                            <Button
                                v-if="selectedStudent && !props.disabled"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="hover:bg-muted-foreground/20 h-4 w-4"
                                @click="handleClear"
                            >
                                <X class="h-3 w-3" />
                                <span class="sr-only">Clear selection</span>
                            </Button>
                            <ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
                        </div>
                    </Button>
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxList class="w-full">
                <!-- Search Input -->
                <div class="relative w-full items-center">
                    <ComboboxInput
                        class="h-10 rounded-none border-0 border-b pl-4 focus-visible:ring-0"
                        placeholder="Search students..."
                        @update:model-value="debouncedSearch"
                    />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-3">
                        <Search class="text-muted-foreground size-4" />
                    </span>
                </div>

                <!-- Content with proper height and scrolling -->
                <ComboboxViewport class="max-h-[300px] overflow-y-auto">
                    <!-- Loading state -->
                    <div v-if="loading" class="flex items-center justify-center py-6">
                        <Loader2 class="size-4 animate-spin" />
                        <span class="text-muted-foreground ml-2 text-sm">Loading students...</span>
                    </div>

                    <!-- Error state -->
                    <div v-else-if="apiError" class="flex items-center justify-center py-6">
                        <AlertCircle class="text-destructive size-4" />
                        <span class="text-destructive ml-2 text-sm">{{ apiError }}</span>
                    </div>

                    <!-- Minimum characters message -->
                    <div v-else-if="showMinimumCharactersMessage" class="flex items-center justify-center py-6">
                        <span class="text-muted-foreground text-sm">Type at least 2 characters to search students</span>
                    </div>

                    <!-- Initial message when no search query -->
                    <div v-else-if="showInitialMessage" class="flex items-center justify-center py-6">
                        <span class="text-muted-foreground text-sm">Start typing to search for students...</span>
                    </div>

                    <!-- Empty states -->
                    <ComboboxEmpty v-else-if="students.length === 0 && searchQuery.length >= 2">
                        No students found for "{{ searchQuery }}".
                    </ComboboxEmpty>

                    <!-- Student list -->
                    <ComboboxGroup v-else-if="students.length > 0">
                        <ComboboxItem v-for="student in students" :key="student.id" :value="student">
                            <div class="flex w-full items-center gap-3">
                                <Avatar class="size-8 shrink-0">
                                    <AvatarFallback class="text-xs">
                                        {{ getStudentInitials(student) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-sm font-medium">{{ student.full_name }}</p>
                                        <Badge variant="outline" :class="getEnrollmentStatusColor(student.status)" class="shrink-0 text-xs">
                                            {{ student.status }}
                                        </Badge>
                                    </div>
                                    <div class="text-muted-foreground flex items-center gap-2 text-xs">
                                        <span>{{ student.student_id }}</span>
                                        <span>•</span>
                                        <span class="truncate">{{ student.email }}</span>
                                    </div>
                                    <div v-if="student.program" class="text-muted-foreground mt-1 text-xs">
                                        {{ student.program.name }}
                                    </div>
                                </div>
                            </div>

                            <ComboboxItemIndicator>
                                <Check class="ml-auto size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>

                        <!-- Results count indicator -->
                        <div v-if="students.length === 20" class="border-t p-2 text-center">
                            <span class="text-muted-foreground text-xs">
                                Showing first 20 results. Refine your search for more specific results.
                            </span>
                        </div>
                    </ComboboxGroup>
                </ComboboxViewport>
            </ComboboxList>
        </Combobox>

        <!-- Error message -->
        <p v-if="props.errorMessage" class="text-destructive text-sm">
            {{ props.errorMessage }}
        </p>
    </div>
</template>
