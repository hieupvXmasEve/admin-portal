import { computed, ref } from 'vue';
import { useApi } from '@/composables/useApiRequest';
import { TEACHING_ASSIGNMENT_ROUTES } from '@/constants';
import type {
  AssignmentFilters,
  AssignLecturerRequest,
  ConflictCheckRequest,
  ExportRequest,
  TeachingAssignment,
  AvailableLecturer,
  PaginatedResponse,
  ScheduleConflict,
  ApiResponse,
  ConflictCheckResponse
} from '@/types/TeachingAssignment';

/**
 * Enhanced composable that provides a clean API interface for teaching assignments
 * with business logic, caching, and error handling without using Pinia
 */
export function useTeachingAssignmentsApi() {
  const api = useApi();

  // State management
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

  // Cache management
  const lecturersCache = ref<Map<number, AvailableLecturer[]>>(new Map());
  const conflictsCache = ref<Map<string, ScheduleConflict[]>>(new Map());
  const lastFetchTime = ref<number>(0);
  const cacheTimeout = 5 * 60 * 1000; // 5 minutes

  // Computed properties for reactive state
  const hasAssignments = computed(() => assignments.value?.data?.length > 0);
  const totalAssignments = computed(() => assignments.value?.meta.total ?? 0);
  const hasConflicts = computed(() => conflicts.value.length > 0);
  const isAnyLoading = computed(() =>
    loading.value || lecturersLoading.value || conflictsLoading.value ||
    assignmentLoading.value || exportLoading.value
  );

  // Loading state getters for backward compatibility
  const isLoading = computed(() => isAnyLoading.value);
  const isAssignmentsLoading = computed(() => loading.value);
  const isLecturersLoading = computed(() => lecturersLoading.value);
  const isConflictsLoading = computed(() => conflictsLoading.value);
  const isAssignmentLoading = computed(() => assignmentLoading.value);
  const isExportLoading = computed(() => exportLoading.value);

  // Error states
  const hasErrors = computed(() =>
    !!(error.value || lecturersError.value || conflictsError.value ||
       assignmentError.value || exportError.value)
  );
  const errors = computed(() => ({
    assignments: error.value,
    lecturers: lecturersError.value,
    conflicts: conflictsError.value,
    assignment: assignmentError.value,
    export: exportError.value
  }));

  // Statistics computed properties
  const stats = computed(() => {
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

  // Utility methods for cache and state management
  const isCacheValid = () => {
    return Date.now() - lastFetchTime.value < cacheTimeout;
  };

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

  // Core API methods
  const loadAssignments = async (filters: AssignmentFilters = {}, options: {
    force?: boolean;
    showLoading?: boolean;
  } = {}) => {
    const { force = false, showLoading = true } = options;

    // Skip if already loading and not forced
    if (loading.value && !force) {
      return assignments.value;
    }

    // Use cached data if available and not forced
    if (!force && assignments.value && isCacheValid()) {
      return assignments.value;
    }

    loading.value = true;
    error.value = null;
    currentFilters.value = filters;

    try {
      const response = await api.get<PaginatedResponse<TeachingAssignment>>(
        route(TEACHING_ASSIGNMENT_ROUTES.API.INDEX),
        filters
      );
        console.log('response.data?.value', response.data?.value);
      if (response.data?.value && response.data?.value.data) {
        assignments.value = response.data?.value;
          console.log('response', assignments.value);

        lastFetchTime.value = Date.now();
      } else {
          console.log('error');
        error.value = response.data?.error || 'Failed to fetch assignments';
      }

      return assignments.value;
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error occurred';
      console.error('Failed to load assignments:', err);
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const loadAvailableLecturers = async (
    courseOfferingId: number,
    filters: Partial<AssignmentFilters> = {},
    options: { force?: boolean } = {}
  ) => {
    const { force = false } = options;

    if (lecturersLoading.value && !force) {
      return availableLecturers.value;
    }

    // Check cache first
    const cacheKey = courseOfferingId;
    const cached = lecturersCache.value.get(cacheKey);
    if (cached && Date.now() - lastFetchTime.value < cacheTimeout && !force) {
      availableLecturers.value = cached;
      return availableLecturers.value;
    }

    lecturersLoading.value = true;
    lecturersError.value = null;

    try {
      const response = await api.get<{ data: AvailableLecturer[] }>(
        route(TEACHING_ASSIGNMENT_ROUTES.API.AVAILABLE_LECTURERS, { courseOfferingId }),
        filters
      );

      if (response.data?.success && response.data?.data) {
        availableLecturers.value = response.data.data.data;
        lecturersCache.value.set(cacheKey, response.data.data.data);
      } else {
        lecturersError.value = response.data?.error || 'Failed to fetch lecturers';
      }

      return availableLecturers.value;
    } catch (err) {
      lecturersError.value = err instanceof Error ? err.message : 'Unknown error occurred';
      console.error('Failed to load available lecturers:', err);
      throw err;
    } finally {
      lecturersLoading.value = false;
    }
  };

  const performConflictCheck = async (
    request: ConflictCheckRequest,
    options: { force?: boolean } = {}
  ) => {
    const { force = false } = options;

    if (conflictsLoading.value && !force) {
      return conflicts.value;
    }

    // Check cache first
    const cacheKey = `${request.lecturer_id}-${request.course_offering_id}`;
    const cached = conflictsCache.value.get(cacheKey);
    if (cached && Date.now() - lastFetchTime.value < cacheTimeout && !force) {
      conflicts.value = cached;
      return conflicts.value;
    }

    conflictsLoading.value = true;
    conflictsError.value = null;

    try {
      const response = await api.post<ConflictCheckResponse>(
        route(TEACHING_ASSIGNMENT_ROUTES.API.CHECK_CONFLICTS),
        request
      );

      if (response.data?.success && response.data?.data) {
        conflicts.value = response.data.data.conflicts;
        conflictsCache.value.set(cacheKey, response.data.data.conflicts);
      } else {
        conflictsError.value = response.data?.error || 'Failed to check conflicts';
      }

      return conflicts.value;
    } catch (err) {
      conflictsError.value = err instanceof Error ? err.message : 'Unknown error occurred';
      console.error('Failed to check conflicts:', err);
      throw err;
    } finally {
      conflictsLoading.value = false;
    }
  };

  const performAssignment = async (request: AssignLecturerRequest) => {
    if (assignmentLoading.value) {
      throw new Error('Assignment operation already in progress');
    }

    // Validate request
    if (!request.course_offering_id || !request.lecturer_id) {
      throw new Error('Course offering and lecturer are required');
    }

    assignmentLoading.value = true;
    assignmentError.value = null;

    try {
      const response = await api.post<ApiResponse>(
        route(TEACHING_ASSIGNMENT_ROUTES.API.ASSIGN),
        request
      );

      if (response.data?.success) {
        // Clear related caches and refresh data
        clearCaches();
        clearConflicts();
        clearAvailableLecturers();
        // Refresh assignments with current filters
        await loadAssignments(currentFilters.value, { force: true });
      } else {
        assignmentError.value = response.data?.error || 'Failed to assign lecturer';
      }

      return response.data;
    } catch (err) {
      assignmentError.value = err instanceof Error ? err.message : 'Unknown error occurred';
      console.error('Failed to assign lecturer:', err);
      throw err;
    } finally {
      assignmentLoading.value = false;
    }
  };

  const performUnassignment = async (courseOfferingId: number) => {
    if (assignmentLoading.value) {
      throw new Error('Assignment operation already in progress');
    }

    if (!courseOfferingId) {
      throw new Error('Course offering ID is required');
    }

    assignmentLoading.value = true;
    assignmentError.value = null;

    try {
      const response = await api.delete<ApiResponse>(
        route(TEACHING_ASSIGNMENT_ROUTES.API.UNASSIGN, { courseOfferingId })
      );

      if (response.data?.success) {
        // Clear related caches and refresh data
        clearCaches();
        clearConflicts();
        clearAvailableLecturers();
        // Refresh assignments with current filters
        await loadAssignments(currentFilters.value, { force: true });
      } else {
        assignmentError.value = response.data?.error || 'Failed to unassign lecturer';
      }

      return response.data;
    } catch (err) {
      assignmentError.value = err instanceof Error ? err.message : 'Unknown error occurred';
      console.error('Failed to unassign lecturer:', err);
      throw err;
    } finally {
      assignmentLoading.value = false;
    }
  };

  const performExport = async (request: ExportRequest) => {
    if (exportLoading.value) {
      throw new Error('Export operation already in progress');
    }

    // Validate request
    if (!request.format || !['excel', 'pdf'].includes(request.format)) {
      throw new Error('Valid export format is required');
    }

    exportLoading.value = true;
    exportError.value = null;

    try {
      const response = await api.post<Blob>(
        route(TEACHING_ASSIGNMENT_ROUTES.API.EXPORT),
        request
      );

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
      console.error('Failed to export assignments:', err);
      throw err;
    } finally {
      exportLoading.value = false;
    }
  };

  // Selection management
  const selectAssignment = (assignment: TeachingAssignment | null) => {
    selectedAssignment.value = assignment;

    // Clear related state when selection changes
    if (assignment !== selectedAssignment.value) {
      clearConflicts();
      clearAvailableLecturers();
    }
  };

  const selectLecturer = (lecturer: AvailableLecturer | null) => {
    selectedLecturer.value = lecturer;

    // Clear conflicts when lecturer selection changes
    if (lecturer !== selectedLecturer.value) {
      clearConflicts();
    }
  };

  // Utility methods
  const refreshData = async (options: { clearCache?: boolean } = {}) => {
    const { clearCache = true } = options;

    if (clearCache) {
      clearCaches();
    }

    return loadAssignments(currentFilters.value, { force: true });
  };

  const clearAllErrors = () => {
    clearErrors();
  };

  const clearAllData = () => {
    clearCaches();
    clearConflicts();
    clearAvailableLecturers();
    selectedAssignment.value = null;
    selectedLecturer.value = null;
  };

  // Validation helpers
  const canAssignLecturer = (lecturer: AvailableLecturer): boolean => {
    return lecturer.can_be_assigned && lecturer.availability_status === 'available';
  };

  const hasScheduleConflicts = (): boolean => {
    return conflicts.value.length > 0;
  };

  const hasCriticalConflicts = (): boolean => {
    return conflicts.value.some(c => c.conflict_severity === 'critical');
  };

  const getAssignmentById = (id: number): TeachingAssignment | undefined => {
    return assignments.value?.data.find(a => a.id === id);
  };

  const getLecturerById = (id: number): AvailableLecturer | undefined => {
    return availableLecturers.value.find(l => l.id === id);
  };

  // Filter helpers
  const getUniqueValues = (field: keyof TeachingAssignment) => {
    if (!assignments.value?.data) return [];

    const values = assignments.value.data
      .map(assignment => {
        // Handle nested properties
        if (field.includes('.')) {
          const keys = field.split('.');
          let value: any = assignment;
          for (const key of keys) {
            value = value?.[key];
          }
          return value;
        }
        return assignment[field];
      })
      .filter(Boolean);

    return [...new Set(values)];
  };

  const getFilterSuggestions = () => ({
    semesters: getUniqueValues('semester.name' as keyof TeachingAssignment),
    campuses: getUniqueValues('campus.name' as keyof TeachingAssignment),
    faculties: getUniqueValues('lecturer.faculty' as keyof TeachingAssignment),
    departments: getUniqueValues('lecturer.department' as keyof TeachingAssignment),
  });

  return {
    // State (reactive refs)
    assignments,
    availableLecturers,
    conflicts,
    selectedAssignment,
    selectedLecturer,
    currentFilters,

    // Loading states
    isLoading,
    isAssignmentsLoading,
    isLecturersLoading,
    isConflictsLoading,
    isAssignmentLoading,
    isExportLoading,
    loading, // Direct access to loading states
    lecturersLoading,
    conflictsLoading,
    assignmentLoading,
    exportLoading,

    // Error states
    hasErrors,
    errors,
    error, // Direct access to error states
    lecturersError,
    conflictsError,
    assignmentError,
    exportError,

    // Computed properties
    hasAssignments,
    totalAssignments,
    hasConflicts,
    stats,
    conflictStats,

    // Core API methods
    loadAssignments,
    loadAvailableLecturers,
    performConflictCheck,
    performAssignment,
    performUnassignment,
    performExport,

    // Selection management
    selectAssignment,
    selectLecturer,

    // Utility methods
    refreshData,
    clearAllErrors,
    clearAllData,
    clearCaches,
    clearConflicts,
    clearAvailableLecturers,
    isCacheValid,

    // Validation helpers
    canAssignLecturer,
    hasScheduleConflicts,
    hasCriticalConflicts,
    getAssignmentById,
    getLecturerById,

    // Filter helpers
    getUniqueValues,
    getFilterSuggestions,
  };
}
