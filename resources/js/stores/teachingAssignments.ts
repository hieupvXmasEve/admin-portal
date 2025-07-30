import { useApi } from '@/composables/useApiRequest';
import { TEACHING_ASSIGNMENT_ROUTES } from '@/constants';
import type {
    ApiResponse,
    AssignLecturerRequest,
    AssignmentFilters,
    AvailableLecturer,
    ConflictCheckRequest,
    ConflictCheckResponse,
    ExportRequest,
    PaginatedResponse,
    ScheduleConflict,
    TeachingAssignment,
} from '@/types/TeachingAssignment';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useTeachingAssignmentsStore = defineStore('teachingAssignments', () => {
    const api = useApi();

    // State
    const assignments = ref<PaginatedResponse<TeachingAssignment> | null>(null);
    const availableLecturers = ref<AvailableLecturer[]>([]);
    const conflicts = ref<ScheduleConflict[]>([]);
    const currentFilters = ref<AssignmentFilters>({});
    const selectedAssignment = ref<TeachingAssignment | null>(null);
    const selectedLecturer = ref<AvailableLecturer | null>(null);

    // Loading states
    const loading = ref(false);
    const lecturersLoading = ref(false);
    const conflictsLoading = ref(false);
    const assignmentLoading = ref(false);
    const exportLoading = ref(false);

    // Error states
    const error = ref<string | null>(null);
    const lecturersError = ref<string | null>(null);
    const conflictsError = ref<string | null>(null);
    const assignmentError = ref<string | null>(null);
    const exportError = ref<string | null>(null);

    // Cache
    const lecturersCache = ref<Map<number, AvailableLecturer[]>>(new Map());
    const conflictsCache = ref<Map<string, ScheduleConflict[]>>(new Map());
    const lastFetchTime = ref<number>(0);
    const cacheTimeout = 5 * 60 * 1000; // 5 minutes

    // Computed
    const hasAssignments = computed(() => assignments.value?.data?.length > 0);
    const totalAssignments = computed(() => assignments.value?.meta.total ?? 0);
    const hasConflicts = computed(() => conflicts.value.length > 0);
    const isAnyLoading = computed(
        () => loading.value || lecturersLoading.value || conflictsLoading.value || assignmentLoading.value || exportLoading.value,
    );

    const assignmentStats = computed(() => {
        if (!assignments.value?.data) {
            return { assigned: 0, unassigned: 0, urgent: 0, total: 0 };
        }

        const data = assignments.value.data;
        return {
            assigned: data.filter((a) => a.assignment_status === 'assigned').length,
            unassigned: data.filter((a) => a.assignment_status === 'unassigned').length,
            urgent: data.filter((a) => a.assignment_status === 'urgent').length,
            total: data.length,
        };
    });

    const conflictStats = computed(() => {
        const stats = { critical: 0, high: 0, medium: 0, low: 0 };
        conflicts.value.forEach((conflict) => {
            stats[conflict.conflict_severity as keyof typeof stats]++;
        });
        return stats;
    });

    // Actions
    const fetchAssignments = async (filters: AssignmentFilters = {}) => {
        loading.value = true;
        error.value = null;
        currentFilters.value = filters;

        try {
            const response = await api.get<PaginatedResponse<TeachingAssignment>>(route(TEACHING_ASSIGNMENT_ROUTES.API.INDEX), filters);

            if (response.data?.success && response.data?.data) {
                assignments.value = response.data.data;
                lastFetchTime.value = Date.now();
            } else {
                error.value = response.data?.error || 'Failed to fetch assignments';
            }

            return response.data;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error occurred';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchAvailableLecturers = async (courseOfferingId: number, filters: Partial<AssignmentFilters> = {}) => {
        // Check cache first
        const cacheKey = courseOfferingId;
        const cached = lecturersCache.value.get(cacheKey);
        if (cached && Date.now() - lastFetchTime.value < cacheTimeout) {
            availableLecturers.value = cached;
            return { success: true, data: { data: cached } };
        }

        lecturersLoading.value = true;
        lecturersError.value = null;

        try {
            const response = await api.get<{ data: AvailableLecturer[] }>(
                route(TEACHING_ASSIGNMENT_ROUTES.API.AVAILABLE_LECTURERS, { courseOfferingId }),
                filters,
            );

            if (response.data?.success && response.data?.data) {
                availableLecturers.value = response.data.data.data;
                lecturersCache.value.set(cacheKey, response.data.data.data);
            } else {
                lecturersError.value = response.data?.error || 'Failed to fetch lecturers';
            }

            return response.data;
        } catch (err) {
            lecturersError.value = err instanceof Error ? err.message : 'Unknown error occurred';
            throw err;
        } finally {
            lecturersLoading.value = false;
        }
    };

    const checkConflicts = async (request: ConflictCheckRequest) => {
        // Check cache first
        const cacheKey = `${request.lecturer_id}-${request.course_offering_id}`;
        const cached = conflictsCache.value.get(cacheKey);
        if (cached && Date.now() - lastFetchTime.value < cacheTimeout) {
            conflicts.value = cached;
            return { success: true, data: { conflicts: cached, has_conflicts: cached.length > 0 } };
        }

        conflictsLoading.value = true;
        conflictsError.value = null;

        try {
            const response = await api.post<ConflictCheckResponse>(route(TEACHING_ASSIGNMENT_ROUTES.API.CHECK_CONFLICTS), request);

            if (response.data?.success && response.data?.data) {
                conflicts.value = response.data.data.conflicts;
                conflictsCache.value.set(cacheKey, response.data.data.conflicts);
            } else {
                conflictsError.value = response.data?.error || 'Failed to check conflicts';
            }

            return response.data;
        } catch (err) {
            conflictsError.value = err instanceof Error ? err.message : 'Unknown error occurred';
            throw err;
        } finally {
            conflictsLoading.value = false;
        }
    };

    const assignLecturer = async (request: AssignLecturerRequest) => {
        assignmentLoading.value = true;
        assignmentError.value = null;

        try {
            const response = await api.post<ApiResponse>(route(TEACHING_ASSIGNMENT_ROUTES.API.ASSIGN), request);

            if (response.data?.success) {
                // Invalidate caches
                clearCaches();
                // Refresh assignments
                await fetchAssignments(currentFilters.value);
            } else {
                assignmentError.value = response.data?.error || 'Failed to assign lecturer';
            }

            return response.data;
        } catch (err) {
            assignmentError.value = err instanceof Error ? err.message : 'Unknown error occurred';
            throw err;
        } finally {
            assignmentLoading.value = false;
        }
    };

    const unassignLecturer = async (courseOfferingId: number) => {
        assignmentLoading.value = true;
        assignmentError.value = null;

        try {
            const response = await api.delete<ApiResponse>(route(TEACHING_ASSIGNMENT_ROUTES.API.UNASSIGN, { courseOfferingId }));

            if (response.data?.success) {
                // Invalidate caches
                clearCaches();
                // Refresh assignments
                await fetchAssignments(currentFilters.value);
            } else {
                assignmentError.value = response.data?.error || 'Failed to unassign lecturer';
            }

            return response.data;
        } catch (err) {
            assignmentError.value = err instanceof Error ? err.message : 'Unknown error occurred';
            throw err;
        } finally {
            assignmentLoading.value = false;
        }
    };

    const exportAssignments = async (request: ExportRequest) => {
        exportLoading.value = true;
        exportError.value = null;

        try {
            const response = await api.post<Blob>(route(TEACHING_ASSIGNMENT_ROUTES.API.EXPORT), request);

            if (response.data?.success && response.data?.data) {
                // Create download link
                const url = window.URL.createObjectURL(response.data.data);
                const link = document.createElement('a');
                link.href = url;
                link.download = `teaching-assignments-${new Date().toISOString().split('T')[0]}.${request.format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            } else {
                exportError.value = 'Failed to export assignments';
            }

            return response.data;
        } catch (err) {
            exportError.value = err instanceof Error ? err.message : 'Unknown error occurred';
            throw err;
        } finally {
            exportLoading.value = false;
        }
    };

    // Utility actions
    const clearCaches = () => {
        lecturersCache.value.clear();
        conflictsCache.value.clear();
    };

    const clearErrors = () => {
        error.value = null;
        lecturersError.value = null;
        conflictsError.value = null;
        assignmentError.value = null;
        exportError.value = null;
    };

    const clearConflicts = () => {
        conflicts.value = [];
        conflictsError.value = null;
    };

    const clearAvailableLecturers = () => {
        availableLecturers.value = [];
        lecturersError.value = null;
    };

    const setSelectedAssignment = (assignment: TeachingAssignment | null) => {
        selectedAssignment.value = assignment;
    };

    const setSelectedLecturer = (lecturer: AvailableLecturer | null) => {
        selectedLecturer.value = lecturer;
    };

    const refreshAssignments = () => {
        return fetchAssignments(currentFilters.value);
    };

    const isCacheValid = () => {
        return Date.now() - lastFetchTime.value < cacheTimeout;
    };

    return {
        // State
        assignments: assignments,
        availableLecturers: availableLecturers,
        conflicts: conflicts,
        currentFilters: currentFilters,
        selectedAssignment: selectedAssignment,
        selectedLecturer: selectedLecturer,

        // Loading states
        loading: loading,
        lecturersLoading: lecturersLoading,
        conflictsLoading: conflictsLoading,
        assignmentLoading: assignmentLoading,
        exportLoading: exportLoading,

        // Error states
        error: error,
        lecturersError: lecturersError,
        conflictsError: conflictsError,
        assignmentError: assignmentError,
        exportError: exportError,

        // Computed
        hasAssignments,
        totalAssignments,
        hasConflicts,
        isAnyLoading,
        assignmentStats,
        conflictStats,

        // Actions
        fetchAssignments,
        fetchAvailableLecturers,
        checkConflicts,
        assignLecturer,
        unassignLecturer,
        exportAssignments,

        // Utility actions
        clearCaches,
        clearErrors,
        clearConflicts,
        clearAvailableLecturers,
        setSelectedAssignment,
        setSelectedLecturer,
        refreshAssignments,
        isCacheValid,
    };
});
