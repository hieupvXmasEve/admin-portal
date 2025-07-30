import type { ConflictCheckRequest, ConflictCheckResponse, ScheduleConflict } from '@/types/TeachingAssignment';
import { debounce } from 'lodash-es';
import { computed, ref } from 'vue';
import { useTeachingAssignments } from './useTeachingAssignments';

export function useConflictDetection() {
    const { checkConflicts, loading } = useTeachingAssignments();

    // State
    const conflicts = ref<ScheduleConflict[]>([]);
    const isChecking = ref(false);
    const lastCheckRequest = ref<ConflictCheckRequest | null>(null);
    const checkError = ref<string | null>(null);

    // Computed
    const hasConflicts = computed(() => conflicts.value.length > 0);
    const conflictCount = computed(() => conflicts.value.length);

    const conflictsBySeverity = computed(() => ({
        critical: conflicts.value.filter((c) => c.conflict_severity === 'critical'),
        high: conflicts.value.filter((c) => c.conflict_severity === 'high'),
        medium: conflicts.value.filter((c) => c.conflict_severity === 'medium'),
        low: conflicts.value.filter((c) => c.conflict_severity === 'low'),
    }));

    const mostSevereConflict = computed(() => {
        if (conflictsBySeverity.value.critical.length > 0) return 'critical';
        if (conflictsBySeverity.value.high.length > 0) return 'high';
        if (conflictsBySeverity.value.medium.length > 0) return 'medium';
        if (conflictsBySeverity.value.low.length > 0) return 'low';
        return null;
    });

    const canProceedWithAssignment = computed(() => {
        // Can proceed if no conflicts or only low/medium severity conflicts
        return !hasConflicts.value || mostSevereConflict.value === 'low' || mostSevereConflict.value === 'medium';
    });

    const requiresForceAssignment = computed(() => {
        // Requires force assignment for critical or high severity conflicts
        return hasConflicts.value && (mostSevereConflict.value === 'critical' || mostSevereConflict.value === 'high');
    });

    // Methods
    const performConflictCheck = async (request: ConflictCheckRequest): Promise<ConflictCheckResponse | null> => {
        if (!request.lecturer_id || !request.course_offering_id) {
            return null;
        }

        isChecking.value = true;
        checkError.value = null;
        lastCheckRequest.value = request;

        try {
            const response = await checkConflicts(request);

            if (response.success && response.data) {
                conflicts.value = response.data.conflicts;
                return response.data;
            } else {
                checkError.value = response.error || 'Failed to check conflicts';
                return null;
            }
        } catch (error) {
            checkError.value = error instanceof Error ? error.message : 'Unknown error occurred';
            console.error('Conflict check failed:', error);
            return null;
        } finally {
            isChecking.value = false;
        }
    };

    // Debounced conflict check for real-time checking
    const debouncedConflictCheck = debounce(performConflictCheck, 500);

    const checkConflictsRealTime = (request: ConflictCheckRequest) => {
        // Clear previous conflicts immediately for better UX
        if (
            lastCheckRequest.value?.lecturer_id !== request.lecturer_id ||
            lastCheckRequest.value?.course_offering_id !== request.course_offering_id
        ) {
            conflicts.value = [];
        }

        return debouncedConflictCheck(request);
    };

    const clearConflicts = () => {
        conflicts.value = [];
        checkError.value = null;
        lastCheckRequest.value = null;
    };

    const retryLastCheck = () => {
        if (lastCheckRequest.value) {
            return performConflictCheck(lastCheckRequest.value);
        }
        return Promise.resolve(null);
    };

    // Conflict analysis helpers
    const getConflictSummary = () => {
        if (!hasConflicts.value) {
            return 'No conflicts detected';
        }

        const summary = [];
        const { critical, high, medium, low } = conflictsBySeverity.value;

        if (critical.length > 0) {
            summary.push(`${critical.length} critical`);
        }
        if (high.length > 0) {
            summary.push(`${high.length} high`);
        }
        if (medium.length > 0) {
            summary.push(`${medium.length} medium`);
        }
        if (low.length > 0) {
            summary.push(`${low.length} low`);
        }

        return `${conflictCount.value} conflict${conflictCount.value > 1 ? 's' : ''}: ${summary.join(', ')}`;
    };

    const getRecommendedAction = () => {
        if (!hasConflicts.value) {
            return 'proceed';
        }

        if (conflictsBySeverity.value.critical.length > 0) {
            return 'block'; // Cannot proceed
        }

        if (conflictsBySeverity.value.high.length > 0) {
            return 'force'; // Requires force assignment
        }

        if (conflictsBySeverity.value.medium.length > 0) {
            return 'warn'; // Proceed with warning
        }

        return 'proceed'; // Low severity conflicts can proceed
    };

    const getConflictsByType = () => {
        const byType: Record<string, ScheduleConflict[]> = {};

        conflicts.value.forEach((conflict) => {
            if (!byType[conflict.conflict_type]) {
                byType[conflict.conflict_type] = [];
            }
            byType[conflict.conflict_type].push(conflict);
        });

        return byType;
    };

    const getConflictResolutionSuggestions = () => {
        const suggestions: string[] = [];
        const { critical, high, medium } = conflictsBySeverity.value;

        if (critical.length > 0) {
            suggestions.push('Critical conflicts detected - assignment cannot proceed without resolution');
            suggestions.push('Consider reassigning one of the conflicting courses to a different lecturer');
            suggestions.push('Contact administration to reschedule conflicting time slots');
        }

        if (high.length > 0) {
            suggestions.push('High priority conflicts require attention before assignment');
            suggestions.push('Review lecturer workload and consider redistribution');
            suggestions.push('Check if any courses can be moved to different time slots');
        }

        if (medium.length > 0) {
            suggestions.push('Medium priority conflicts should be reviewed');
            suggestions.push('Ensure adequate travel time between locations');
            suggestions.push('Consider lecturer preferences and workload balance');
        }

        if (suggestions.length === 0) {
            suggestions.push('No major conflicts detected - assignment can proceed');
            suggestions.push('Monitor lecturer workload for optimal distribution');
        }

        return suggestions;
    };

    return {
        // State
        conflicts: conflicts,
        isChecking: isChecking,
        checkError: checkError,
        lastCheckRequest: lastCheckRequest,

        // Computed
        hasConflicts,
        conflictCount,
        conflictsBySeverity,
        mostSevereConflict,
        canProceedWithAssignment,
        requiresForceAssignment,

        // Methods
        performConflictCheck,
        checkConflictsRealTime,
        clearConflicts,
        retryLastCheck,

        // Analysis helpers
        getConflictSummary,
        getRecommendedAction,
        getConflictsByType,
        getConflictResolutionSuggestions,
    };
}
