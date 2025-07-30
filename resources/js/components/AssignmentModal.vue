<script setup lang="ts">
import ConflictDisplay from '@/components/ConflictDisplay.vue';
import Icon from '@/components/Icon.vue';
import LecturerSelector from '@/components/LecturerSelector.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useConflictDetection } from '@/composables/useConflictDetection';
import { useTeachingAssignments } from '@/composables/useTeachingAssignments';
import type { AssignmentFilters, AvailableLecturer, TeachingAssignment } from '@/types/TeachingAssignment';
import { computed, ref, watch } from 'vue';

// Props
interface Props {
    courseOffering: TeachingAssignment;
    isOpen: boolean;
}

const props = defineProps<Props>();

// Emits
const emit = defineEmits<{
    close: [];
    assigned: [lecturerId: number];
    unassigned: [];
}>();

// Composables
const { availableLecturers, loading, fetchAvailableLecturers, assignLecturer, unassignLecturer } = useTeachingAssignments();

const {
    conflicts: detectedConflicts,
    isChecking: conflictChecking,
    hasConflicts,
    requiresForceAssignment,
    checkConflictsRealTime,
    clearConflicts,
    getRecommendedAction,
} = useConflictDetection();

// State
const selectedLecturerId = ref<number | null>(null);
const forceAssignment = ref(false);
const lecturersLoading = ref(false);

// Computed
const selectedLecturer = computed(() => availableLecturers.value.find((l) => l.id === selectedLecturerId.value));

// Methods
const handleClose = () => {
    emit('close');
    resetState();
};

const resetState = () => {
    selectedLecturerId.value = null;
    forceAssignment.value = false;
};

const handleLecturerSelected = (lecturer: AvailableLecturer) => {
    selectedLecturerId.value = lecturer.id;
    forceAssignment.value = false;

    // Perform real-time conflict check
    if (lecturer.id && props.courseOffering.id) {
        checkConflictsRealTime({
            lecturer_id: lecturer.id,
            course_offering_id: props.courseOffering.id,
        });
    }
};

const handleSelectionCleared = () => {
    selectedLecturerId.value = null;
    forceAssignment.value = false;
    clearConflicts();
};

const handleFiltersChanged = async (filters: Partial<AssignmentFilters>) => {
    lecturersLoading.value = true;
    try {
        await fetchAvailableLecturers(props.courseOffering.id, filters);
    } finally {
        lecturersLoading.value = false;
    }
};

const handleAssign = async () => {
    if (!selectedLecturerId.value) return;

    try {
        await assignLecturer({
            course_offering_id: props.courseOffering.id,
            lecturer_id: selectedLecturerId.value,
            force_assignment: forceAssignment.value,
        });

        emit('assigned', selectedLecturerId.value);
        handleClose();
    } catch (error) {
        console.error('Failed to assign lecturer:', error);
        // Error handling will be implemented in Task 13
    }
};

const handleUnassign = async () => {
    try {
        await unassignLecturer(props.courseOffering.id);
        emit('unassigned');
        handleClose();
    } catch (error) {
        console.error('Failed to unassign lecturer:', error);
        // Error handling will be implemented in Task 13
    }
};

const formatSchedule = (courseOffering: TeachingAssignment) => {
    const { schedule } = courseOffering;
    if (!schedule.days.length || !schedule.time_start || !schedule.time_end) {
        return 'Not scheduled';
    }

    const days = schedule.days.join(', ');
    const time = `${schedule.time_start} - ${schedule.time_end}`;
    return `${days}, ${time}`;
};

const formatDeliveryMode = (mode: string) => {
    switch (mode) {
        case 'in_person':
            return 'In Person';
        case 'online':
            return 'Online';
        case 'hybrid':
            return 'Hybrid';
        case 'blended':
            return 'Blended';
        default:
            return mode;
    }
};

const handleResolutionSelected = (option: string) => {
    switch (option) {
        case 'cancel':
            handleClose();
            break;
        case 'force':
            forceAssignment.value = true;
            break;
        case 'reschedule':
            // TODO: Implement reschedule request functionality
            console.log('Reschedule request not yet implemented');
            break;
        case 'alternative':
            handleSelectionCleared();
            break;
    }
};

const handleViewConflict = (conflict: any) => {
    // TODO: Implement view conflict details functionality
    console.log('View conflict details:', conflict);
};

const handleFindAlternatives = (conflict: any) => {
    // TODO: Implement find alternative lecturers functionality
    console.log('Find alternatives for conflict:', conflict);
};

// Load available lecturers when modal opens
watch(
    () => props.isOpen,
    async (isOpen) => {
        if (isOpen) {
            lecturersLoading.value = true;
            try {
                await fetchAvailableLecturers(props.courseOffering.id);
            } finally {
                lecturersLoading.value = false;
            }
        } else {
            resetState();
            clearConflicts();
        }
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleClose">
        <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle> {{ courseOffering.lecturer ? 'Reassign' : 'Assign' }} Lecturer </DialogTitle>
                <DialogDescription>
                    {{ courseOffering.unit.code }} - {{ courseOffering.unit.name }}
                    <span v-if="courseOffering.section_code">(Section {{ courseOffering.section_code }})</span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6">
                <!-- Course Information -->
                <div class="rounded-lg bg-gray-50 p-4">
                    <h3 class="mb-3 text-sm font-medium text-gray-900">Course Information</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">Semester:</span>
                            <span class="ml-2">{{ courseOffering.semester.name }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Campus:</span>
                            <span class="ml-2">{{ courseOffering.campus.name }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Schedule:</span>
                            <span class="ml-2">{{ formatSchedule(courseOffering) }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Enrollment:</span>
                            <span class="ml-2">{{ courseOffering.enrollment.current }}/{{ courseOffering.enrollment.maximum }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Delivery Mode:</span>
                            <span class="ml-2">{{ formatDeliveryMode(courseOffering.delivery_mode) }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Location:</span>
                            <span class="ml-2">{{ courseOffering.schedule.location || 'TBD' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Current Assignment -->
                <div v-if="courseOffering.lecturer" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <h3 class="mb-2 text-sm font-medium text-yellow-800">Current Assignment</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-yellow-700">
                                <strong>{{ courseOffering.lecturer.display_name }}</strong>
                                ({{ courseOffering.lecturer.employee_id }})
                            </p>
                            <p class="text-xs text-yellow-600">{{ courseOffering.lecturer.department }} • {{ courseOffering.lecturer.faculty }}</p>
                        </div>
                        <Button variant="outline" size="sm" @click="handleUnassign" :disabled="loading">
                            <Icon name="user-x" class="mr-1 h-4 w-4" />
                            Unassign
                        </Button>
                    </div>
                </div>

                <!-- Assignment Priority Warning -->
                <div v-if="courseOffering.assignment_priority === 'urgent'" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="flex items-center">
                        <Icon name="alert-triangle" class="mr-2 h-5 w-5 text-red-500" />
                        <div>
                            <h3 class="text-sm font-medium text-red-800">Urgent Assignment Required</h3>
                            <p class="mt-1 text-sm text-red-700">
                                This course offering requires immediate attention as classes have already started.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Lecturer Selection -->
                <div>
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Select Lecturer</h3>
                    <LecturerSelector
                        :course-offering-id="courseOffering.id"
                        :available-lecturers="availableLecturers"
                        :loading="lecturersLoading"
                        :selected-lecturer-id="selectedLecturerId"
                        @lecturer-selected="handleLecturerSelected"
                        @selection-cleared="handleSelectionCleared"
                        @filters-changed="handleFiltersChanged"
                    />
                </div>

                <!-- Conflict Detection and Display -->
                <div v-if="selectedLecturer">
                    <ConflictDisplay
                        :conflicts="detectedConflicts"
                        :show-statistics="true"
                        :show-no-conflicts-message="true"
                        @resolution-selected="handleResolutionSelected"
                        @view-conflict="handleViewConflict"
                        @find-alternatives="handleFindAlternatives"
                    />

                    <!-- Force Assignment Option -->
                    <div v-if="requiresForceAssignment" class="mt-4">
                        <label class="flex items-center">
                            <input
                                v-model="forceAssignment"
                                type="checkbox"
                                class="focus:ring-opacity-50 rounded border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200"
                            />
                            <span class="ml-2 text-sm text-red-800"> I understand the conflicts and want to proceed anyway (requires approval) </span>
                        </label>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-6">
                <Button variant="outline" @click="handleClose" :disabled="loading"> Cancel </Button>
                <Button v-if="selectedLecturer" @click="handleAssign" :disabled="loading || (requiresForceAssignment && !forceAssignment)">
                    <Icon v-if="loading" name="loader-2" class="mr-2 h-4 w-4 animate-spin" />
                    {{ requiresForceAssignment ? 'Force Assign' : 'Assign Lecturer' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
