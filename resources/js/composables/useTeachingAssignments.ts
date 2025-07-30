import { router } from '@inertiajs/vue3';
import { useTeachingAssignmentsApi } from './useTeachingAssignmentsApi';
import { TEACHING_ASSIGNMENT_ROUTES } from '@/constants';
import type {
  AssignmentFilters,
  AssignLecturerRequest,
  ConflictCheckRequest,
  ExportRequest
} from '@/types/TeachingAssignment';

/**
 * Main composable for teaching assignments functionality
 * Provides a simplified interface to the core API methods
 */
export function useTeachingAssignments() {
  const api = useTeachingAssignmentsApi();

  // Expose core state with convenient naming
  const assignments = api.assignments;
  const availableLecturers = api.availableLecturers;
  const conflicts = api.conflicts;
  const loading = api.isAssignmentsLoading;
  const error = api.errors;

  // Computed properties for easy access
  const hasAssignments = api.hasAssignments;
  const totalAssignments = api.totalAssignments;
  const hasConflicts = api.hasScheduleConflicts;

  // Core methods with simpler naming
  const fetchAssignments = (filters: AssignmentFilters = {}) => {
    return api.loadAssignments(filters);
  };

  const assignLecturer = (request: AssignLecturerRequest) => {
    return api.performAssignment(request);
  };

  const unassignLecturer = (courseOfferingId: number) => {
    return api.performUnassignment(courseOfferingId);
  };

  const fetchAvailableLecturers = (courseOfferingId: number, filters: Partial<AssignmentFilters> = {}) => {
    return api.loadAvailableLecturers(courseOfferingId, filters);
  };

  const checkConflicts = (request: ConflictCheckRequest) => {
    return api.performConflictCheck(request);
  };

  const exportAssignments = (request: ExportRequest) => {
    return api.performExport(request);
  };

  const clearConflicts = () => {
    api.clearConflicts();
  };

  const clearAvailableLecturers = () => {
    api.clearAvailableLecturers();
  };

  const refreshAssignments = () => {
    return api.refreshData();
  };

  // Navigation helpers
  const goToAssignments = (filters?: AssignmentFilters) => {
    router.visit(route(TEACHING_ASSIGNMENT_ROUTES.INDEX), {
      data: filters,
      preserveState: true,
      preserveScroll: true
    });
  };

  return {
    // Core state
    assignments,
    availableLecturers,
    conflicts,
    currentFilters: api.currentFilters,
    loading,
    error,

    // Computed properties
    hasAssignments,
    totalAssignments,
    hasConflicts,

    // Core methods
    fetchAssignments,
    assignLecturer,
    unassignLecturer,
    fetchAvailableLecturers,
    checkConflicts,
    exportAssignments,
    clearConflicts,
    clearAvailableLecturers,
    refreshAssignments,
    goToAssignments,

    // Access to full API for advanced usage
    api
  };
}
