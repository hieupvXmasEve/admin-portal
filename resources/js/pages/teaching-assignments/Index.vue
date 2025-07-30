<script setup lang="ts">
import AssignmentModal from '@/components/AssignmentModal.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import ErrorHandler from '@/components/ErrorHandler.vue';
import ExportButton from '@/components/ExportButton.vue';
import ExportModal from '@/components/ExportModal.vue';
import Icon from '@/components/Icon.vue';
import TeachingAssignmentsTable from '@/components/TeachingAssignmentsTable.vue';
import { useErrorHandler } from '@/composables/useErrorHandler';
import { useFilterOptions } from '@/composables/useFilterOptions';
import { useTeachingAssignments } from '@/composables/useTeachingAssignments';
import type { AssignmentFilters, TeachingAssignment } from '@/types/TeachingAssignment';
import { debounce } from 'lodash-es';
import { computed, onMounted, ref, watchEffect } from 'vue';

// Props
interface Props {
    filters?: Partial<AssignmentFilters>;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
});

// Composables
const { assignments, loading, totalAssignments, fetchAssignments } = useTeachingAssignments();
watchEffect(() => {
    console.log('assignments', assignments.value);
});
const { semesters, campuses, faculties, departments } = useFilterOptions();

const { currentError: pageError, handleError, clearError, reportError, reloadPage } = useErrorHandler();

// State
const filters = ref<AssignmentFilters>({
    ...props.filters,
});
const showExportModal = ref(false);
const showAssignmentModal = ref(false);
const selectedAssignment = ref<TeachingAssignment | null>(null);

// Computed
const assignedCount = computed(() => assignments.value?.data.filter((a) => a.assignment_status === 'assigned').length ?? 0);

const unassignedCount = computed(() => assignments.value?.data.filter((a) => a.assignment_status === 'unassigned').length ?? 0);

const urgentCount = computed(() => assignments.value?.data.filter((a) => a.assignment_status === 'urgent').length ?? 0);

// Methods
const applyFilters = () => {
    fetchAssignments(filters.value);
};

const debouncedApplyFilters = debounce(applyFilters, 300);

const clearFilters = () => {
    filters.value = {};
    applyFilters();
};

const refreshData = () => {
    fetchAssignments(filters.value);
};

const handleAssignLecturer = (assignment: TeachingAssignment) => {
    selectedAssignment.value = assignment;
    showAssignmentModal.value = true;
};

const handleUnassignLecturer = (assignment: TeachingAssignment) => {
    selectedAssignment.value = assignment;
    showAssignmentModal.value = true;
};

const handleViewDetails = (assignment: TeachingAssignment) => {
    // TODO: Open details modal or navigate to details page
    console.log('View details for:', assignment);
};

const handlePageChange = (page: number) => {
    filters.value.per_page = filters.value.per_page || 15;
    const newFilters = { ...filters.value, page };
    fetchAssignments(newFilters);
};

const closeAssignmentModal = () => {
    showAssignmentModal.value = false;
    selectedAssignment.value = null;
};

const handleLecturerAssigned = (lecturerId: number) => {
    // Refresh the assignments list to show the updated assignment
    refreshData();
    closeAssignmentModal();
};

const handleLecturerUnassigned = () => {
    // Refresh the assignments list to show the updated assignment
    refreshData();
    closeAssignmentModal();
};

const closeExportModal = () => {
    showExportModal.value = false;
};

const handleExportStarted = () => {
    console.log('Export started');
};

const handleExportCompleted = () => {
    console.log('Export completed successfully');
    closeExportModal();
};

const handleExportFailed = (error: string) => {
    handleError(error, { source: 'export', action: 'export_assignments' });
};

// Error handling methods
const handleGlobalError = (error: Error) => {
    handleError(error, { source: 'global', component: 'TeachingAssignmentsIndex' });
};

const handleGlobalRetry = () => {
    // Retry the last failed operation
    refreshData();
};

const handleErrorRetry = () => {
    // Retry the specific operation that failed
    refreshData();
};

const handleErrorDismiss = () => {
    clearError();
};

const handleErrorReport = (errorInfo: any) => {
    reportError(errorInfo);
};

const handleErrorReload = () => {
    reloadPage();
};

// Enhanced data fetching with error handling
const fetchAssignmentsWithErrorHandling = async (filters: AssignmentFilters = {}) => {
    try {
        await fetchAssignments(filters);
    } catch (error) {
        handleError(error, {
            source: 'data_fetch',
            action: 'fetch_assignments',
            filters,
        });
    }
};

// Lifecycle
onMounted(() => {
    fetchAssignmentsWithErrorHandling(filters.value);
});
</script>

<template>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Teaching Assignments</h1>
            <p class="mt-1 text-sm text-gray-600">Manage lecturer assignments to course offerings</p>
        </div>
        <div class="flex items-center space-x-3">
            <!-- Export Button -->
            <ExportButton
                :current-filters="filters"
                @export-modal-requested="showExportModal = true"
                @export-started="handleExportStarted"
                @export-completed="handleExportCompleted"
                @export-failed="handleExportFailed"
            />

            <!-- Refresh Button -->
            <button
                type="button"
                class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                @click="refreshData"
                :disabled="loading"
            >
                <Icon name="refresh-cw" class="mr-2 h-4 w-4" :class="{ 'animate-spin': loading }" />
                Refresh
            </button>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Filters Section -->
        <div class="rounded-lg bg-white p-6 shadow">
            <h3 class="mb-4 text-lg font-medium text-gray-900">Filters</h3>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <!-- Semester Filter -->
                <div>
                    <label for="semester" class="mb-1 block text-sm font-medium text-gray-700"> Semester </label>
                    <select
                        id="semester"
                        v-model="filters.semester_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All Semesters</option>
                        <option v-for="semester in semesters" :key="semester.id" :value="semester.id">
                            {{ semester.name }}
                        </option>
                    </select>
                </div>

                <!-- Assignment Status Filter -->
                <div>
                    <label for="assignment-status" class="mb-1 block text-sm font-medium text-gray-700"> Assignment Status </label>
                    <select
                        id="assignment-status"
                        v-model="filters.assignment_status"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        @change="applyFilters"
                    >
                        <option value="">All Statuses</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <!-- Search -->
                <div>
                    <label for="search" class="mb-1 block text-sm font-medium text-gray-700"> Search </label>
                    <DebouncedInput
                        id="search"
                        v-model="filters.search"
                        placeholder="Search units, lecturers..."
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        @update:model-value="applyFilters"
                    />
                </div>
            </div>

            <!-- Additional Filters Row -->
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Faculty Filter -->
                <div>
                    <label for="faculty" class="mb-1 block text-sm font-medium text-gray-700"> Faculty </label>
                    <input
                        id="faculty"
                        v-model="filters.faculty"
                        type="text"
                        placeholder="Enter faculty name"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        @input="debouncedApplyFilters"
                    />
                </div>

                <!-- Department Filter -->
                <div>
                    <label for="department" class="mb-1 block text-sm font-medium text-gray-700"> Department </label>
                    <input
                        id="department"
                        v-model="filters.department"
                        type="text"
                        placeholder="Enter department name"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        @input="debouncedApplyFilters"
                    />
                </div>

                <!-- Clear Filters -->
                <div class="flex items-end">
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                        @click="clearFilters"
                    >
                        <Icon name="x" class="mr-2 h-4 w-4" />
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Icon name="book-open" class="h-6 w-6 text-gray-400" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Total Courses</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ totalAssignments }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Icon name="user-check" class="h-6 w-6 text-green-400" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Assigned</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ assignedCount }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Icon name="user-x" class="h-6 w-6 text-red-400" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Unassigned</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ unassignedCount }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Icon name="alert-triangle" class="h-6 w-6 text-yellow-400" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="truncate text-sm font-medium text-gray-500">Urgent</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ urgentCount }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignments Table -->
        <div class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-medium text-gray-900">Course Assignments</h3>
            </div>

            <div class="p-6">
                <TeachingAssignmentsTable
                    :assignments="assignments?.data || []"
                    :meta="assignments?.meta"
                    :loading="loading"
                    :total-assignments="totalAssignments"
                    @assign-lecturer="handleAssignLecturer"
                    @unassign-lecturer="handleUnassignLecturer"
                    @view-details="handleViewDetails"
                    @page-change="handlePageChange"
                />
            </div>
        </div>
    </div>

    <!-- Assignment Modal -->
    <AssignmentModal
        v-if="selectedAssignment"
        :course-offering="selectedAssignment"
        :is-open="showAssignmentModal"
        @close="closeAssignmentModal"
        @assigned="handleLecturerAssigned"
        @unassigned="handleLecturerUnassigned"
    />

    <!-- Export Modal -->
    <ExportModal
        :is-open="showExportModal"
        :current-filters="filters"
        @close="closeExportModal"
        @export-started="handleExportStarted"
        @export-completed="handleExportCompleted"
    />

    <!-- Error Handler for Page-Level Errors -->
    <ErrorHandler
        v-if="pageError"
        :error="pageError"
        @retry="handleErrorRetry"
        @dismiss="handleErrorDismiss"
        @report="handleErrorReport"
        @reload="handleErrorReload"
    />
</template>
