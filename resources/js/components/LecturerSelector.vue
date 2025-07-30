<script setup lang="ts">
import DebouncedInput from '@/components/DebouncedInput.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFilterOptions } from '@/composables/useFilterOptions';
import type { AssignmentFilters, AvailableLecturer } from '@/types/TeachingAssignment';
import { debounce } from 'lodash-es';
import { computed, ref, watch } from 'vue';

// Props
interface Props {
    courseOfferingId: number;
    availableLecturers: AvailableLecturer[];
    loading?: boolean;
    selectedLecturerId?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    selectedLecturerId: null,
});

// Emits
const emit = defineEmits<{
    'lecturer-selected': [lecturer: AvailableLecturer];
    'selection-cleared': [];
    'filters-changed': [filters: Partial<AssignmentFilters>];
}>();

// Composables
const { faculties, departments } = useFilterOptions();

// State
const searchQuery = ref('');
const filters = ref<Partial<AssignmentFilters>>({
    faculty: '',
    department: '',
});

// Computed
const selectedLecturer = computed(() => props.availableLecturers.find((l) => l.id === props.selectedLecturerId));

// Methods
const selectLecturer = (lecturer: AvailableLecturer) => {
    if (lecturer.can_be_assigned) {
        emit('lecturer-selected', lecturer);
    }
};

const clearSelection = () => {
    emit('selection-cleared');
};

const handleSearch = debounce(() => {
    emitFiltersChanged();
}, 300);

const handleFilterChange = () => {
    emitFiltersChanged();
};

const emitFiltersChanged = () => {
    const newFilters: Partial<AssignmentFilters> = {
        search: searchQuery.value,
        faculty: filters.value.faculty || undefined,
        department: filters.value.department || undefined,
    };

    emit('filters-changed', newFilters);
};

const getAvailabilityBadgeVariant = (status: string) => {
    switch (status) {
        case 'available':
            return 'default';
        case 'unavailable':
            return 'secondary';
        case 'inactive':
            return 'destructive';
        case 'contract_expired':
            return 'destructive';
        default:
            return 'outline';
    }
};

const formatAvailabilityStatus = (status: string) => {
    switch (status) {
        case 'available':
            return 'Available';
        case 'unavailable':
            return 'Unavailable';
        case 'inactive':
            return 'Inactive';
        case 'contract_expired':
            return 'Contract Expired';
        default:
            return 'Unknown';
    }
};

// Watch for external selection changes
watch(
    () => props.selectedLecturerId,
    (newId) => {
        if (newId && selectedLecturer.value) {
            // Selection was made externally, no need to emit
        }
    },
);
</script>

<template>
    <div class="space-y-4">
        <!-- Search and Filters -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Search -->
            <div>
                <label for="lecturer-search" class="mb-1 block text-sm font-medium text-gray-700"> Search Lecturers </label>
                <DebouncedInput
                    id="lecturer-search"
                    v-model="searchQuery"
                    placeholder="Search by name or employee ID..."
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    @update:model-value="handleSearch"
                />
            </div>

            <!-- Faculty Filter -->
            <div>
                <label for="faculty-filter" class="mb-1 block text-sm font-medium text-gray-700"> Faculty </label>
                <select
                    id="faculty-filter"
                    v-model="filters.faculty"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    @change="handleFilterChange"
                >
                    <option value="">All Faculties</option>
                    <option v-for="faculty in faculties" :key="faculty" :value="faculty">
                        {{ faculty }}
                    </option>
                </select>
            </div>

            <!-- Department Filter -->
            <div>
                <label for="department-filter" class="mb-1 block text-sm font-medium text-gray-700"> Department </label>
                <select
                    id="department-filter"
                    v-model="filters.department"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                    @change="handleFilterChange"
                >
                    <option value="">All Departments</option>
                    <option v-for="department in departments" :key="department" :value="department">
                        {{ department }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-8">
            <Icon name="loader-2" class="h-6 w-6 animate-spin text-blue-500" />
            <span class="ml-2 text-sm text-gray-600">Loading lecturers...</span>
        </div>

        <!-- Lecturers List -->
        <div v-else-if="availableLecturers.length > 0" class="max-h-96 space-y-3 overflow-y-auto">
            <div
                v-for="lecturer in availableLecturers"
                :key="lecturer.id"
                class="cursor-pointer rounded-lg border p-4 transition-colors hover:bg-gray-50"
                :class="{
                    'border-blue-500 bg-blue-50': selectedLecturerId === lecturer.id,
                    'border-red-300 bg-red-50': !lecturer.can_be_assigned,
                    'border-gray-200': lecturer.can_be_assigned && selectedLecturerId !== lecturer.id,
                }"
                @click="selectLecturer(lecturer)"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <!-- Lecturer Info -->
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-300">
                                    <Icon name="user" class="h-5 w-5 text-gray-600" />
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">
                                    {{ lecturer.display_name }}
                                </h4>
                                <p class="text-sm text-gray-500">{{ lecturer.employee_id }} • {{ lecturer.department || 'No Department' }}</p>
                            </div>
                        </div>

                        <!-- Additional Info -->
                        <div class="mt-3 grid grid-cols-2 gap-4 text-xs text-gray-600">
                            <div>
                                <span class="font-medium">Faculty:</span>
                                {{ lecturer.faculty || 'N/A' }}
                            </div>
                            <div>
                                <span class="font-medium">Rank:</span>
                                {{ lecturer.academic_rank || 'N/A' }}
                            </div>
                            <div>
                                <span class="font-medium">Current Load:</span>
                                {{ lecturer.current_course_load }} courses
                            </div>
                            <div>
                                <span class="font-medium">Status:</span>
                                <Badge :variant="getAvailabilityBadgeVariant(lecturer.availability_status)">
                                    {{ formatAvailabilityStatus(lecturer.availability_status) }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Teaching Preferences -->
                        <div v-if="lecturer.preferred_teaching_days.length > 0" class="mt-2">
                            <span class="text-xs font-medium text-gray-600">Preferred Days:</span>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge v-for="day in lecturer.preferred_teaching_days" :key="day" variant="outline" class="text-xs">
                                    {{ day.substring(0, 3) }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Conflicts Warning -->
                        <div v-if="lecturer.conflicts.length > 0" class="mt-3 rounded border border-red-200 bg-red-50 p-2">
                            <div class="flex items-center">
                                <Icon name="alert-triangle" class="mr-2 h-4 w-4 text-red-500" />
                                <span class="text-sm font-medium text-red-800"> {{ lecturer.conflicts.length }} Schedule Conflict(s) </span>
                            </div>
                            <div class="mt-1 text-xs text-red-700">
                                {{ lecturer.conflicts[0].conflict_description }}
                                <span v-if="lecturer.conflicts.length > 1"> and {{ lecturer.conflicts.length - 1 }} more... </span>
                            </div>
                        </div>
                    </div>

                    <!-- Selection Indicator -->
                    <div class="ml-4 flex-shrink-0">
                        <div v-if="selectedLecturerId === lecturer.id" class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-500">
                            <Icon name="check" class="h-4 w-4 text-white" />
                        </div>
                        <div v-else-if="!lecturer.can_be_assigned" class="flex h-6 w-6 items-center justify-center rounded-full bg-red-500">
                            <Icon name="x" class="h-4 w-4 text-white" />
                        </div>
                        <div v-else class="h-6 w-6 rounded-full border-2 border-gray-300" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="py-8 text-center">
            <Icon name="users" class="mx-auto mb-4 h-12 w-12 text-gray-400" />
            <h3 class="mb-2 text-lg font-medium text-gray-900">No Available Lecturers</h3>
            <p class="text-sm text-gray-600">No lecturers match your current search criteria or all lecturers have conflicts.</p>
        </div>

        <!-- Selected Lecturer Summary -->
        <div v-if="selectedLecturer" class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h4 class="mb-2 text-sm font-medium text-blue-900">Selected Lecturer</h4>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-800">
                        <strong>{{ selectedLecturer.display_name }}</strong> ({{ selectedLecturer.employee_id }})
                    </p>
                    <p class="text-xs text-blue-600">
                        {{ selectedLecturer.department }} • {{ selectedLecturer.current_course_load }} current courses
                    </p>
                </div>
                <Button variant="outline" size="sm" @click="clearSelection"> Clear </Button>
            </div>
        </div>
    </div>
</template>
