<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ScheduleConflict } from '@/types/TeachingAssignment';
import { computed, ref, watch } from 'vue';

// Props
interface Props {
    conflicts: ScheduleConflict[];
    showStatistics?: boolean;
    showNoConflictsMessage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showStatistics: true,
    showNoConflictsMessage: true,
});

// Emits
const emit = defineEmits<{
    'resolution-selected': [option: string];
    'view-conflict': [conflict: ScheduleConflict];
    'find-alternatives': [conflict: ScheduleConflict];
}>();

// State
const expandedConflicts = ref<number[]>([]);
const resolutionOption = ref<string>('');

// Computed
const criticalConflicts = computed(() => props.conflicts.filter((c) => c.conflict_severity === 'critical').length);

const highConflicts = computed(() => props.conflicts.filter((c) => c.conflict_severity === 'high').length);

const mediumConflicts = computed(() => props.conflicts.filter((c) => c.conflict_severity === 'medium').length);

const lowConflicts = computed(() => props.conflicts.filter((c) => c.conflict_severity === 'low').length);

// Methods
const toggleConflictDetails = (index: number) => {
    const expandedIndex = expandedConflicts.value.indexOf(index);
    if (expandedIndex > -1) {
        expandedConflicts.value.splice(expandedIndex, 1);
    } else {
        expandedConflicts.value.push(index);
    }
};

const getConflictBorderClass = (severity: string) => {
    switch (severity) {
        case 'critical':
            return 'border-red-300';
        case 'high':
            return 'border-orange-300';
        case 'medium':
            return 'border-yellow-300';
        case 'low':
            return 'border-blue-300';
        default:
            return 'border-gray-300';
    }
};

const getConflictHeaderClass = (severity: string) => {
    switch (severity) {
        case 'critical':
            return 'bg-red-50 text-red-900';
        case 'high':
            return 'bg-orange-50 text-orange-900';
        case 'medium':
            return 'bg-yellow-50 text-yellow-900';
        case 'low':
            return 'bg-blue-50 text-blue-900';
        default:
            return 'bg-gray-50 text-gray-900';
    }
};

const getConflictIcon = (severity: string) => {
    switch (severity) {
        case 'critical':
            return 'x-circle';
        case 'high':
            return 'alert-triangle';
        case 'medium':
            return 'alert-circle';
        case 'low':
            return 'info';
        default:
            return 'help-circle';
    }
};

const getConflictIconClass = (severity: string) => {
    switch (severity) {
        case 'critical':
            return 'text-red-500';
        case 'high':
            return 'text-orange-500';
        case 'medium':
            return 'text-yellow-500';
        case 'low':
            return 'text-blue-500';
        default:
            return 'text-gray-500';
    }
};

const getSeverityBadgeVariant = (severity: string) => {
    switch (severity) {
        case 'critical':
            return 'destructive';
        case 'high':
            return 'destructive';
        case 'medium':
            return 'secondary';
        case 'low':
            return 'outline';
        default:
            return 'outline';
    }
};

const formatSeverity = (severity: string) => {
    return severity.charAt(0).toUpperCase() + severity.slice(1);
};

const formatTime = (start: string | null, end: string | null) => {
    if (!start || !end) return 'Not specified';
    return `${start} - ${end}`;
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

const formatConflictType = (type: string) => {
    switch (type) {
        case 'same_time':
            return 'Exact Time Overlap';
        case 'time_overlap':
            return 'Partial Time Overlap';
        case 'adjacent_time':
            return 'Adjacent Time Slots';
        case 'same_day_different_time':
            return 'Same Day, Different Time';
        default:
            return type;
    }
};

const viewConflictingCourse = (conflict: ScheduleConflict) => {
    emit('view-conflict', conflict);
};

const suggestAlternatives = (conflict: ScheduleConflict) => {
    emit('find-alternatives', conflict);
};

// Watch for resolution option changes
watch(
    () => resolutionOption.value,
    (newOption) => {
        if (newOption) {
            emit('resolution-selected', newOption);
        }
    },
);
</script>

<template>
    <div v-if="conflicts.length > 0" class="space-y-4">
        <!-- Conflict Summary -->
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <div class="flex items-center">
                <Icon name="alert-triangle" class="mr-3 h-5 w-5 text-red-500" />
                <div>
                    <h3 class="text-sm font-medium text-red-800">
                        {{ conflicts.length }} Schedule Conflict{{ conflicts.length > 1 ? 's' : '' }} Detected
                    </h3>
                    <p class="mt-1 text-sm text-red-700">
                        The selected lecturer has conflicting course assignments that may prevent this assignment.
                    </p>
                </div>
            </div>
        </div>

        <!-- Conflict Details -->
        <div class="space-y-3">
            <div
                v-for="(conflict, index) in conflicts"
                :key="conflict.conflicting_course_id"
                class="overflow-hidden rounded-lg border"
                :class="getConflictBorderClass(conflict.conflict_severity)"
            >
                <!-- Conflict Header -->
                <div
                    class="cursor-pointer px-4 py-3"
                    :class="getConflictHeaderClass(conflict.conflict_severity)"
                    @click="toggleConflictDetails(index)"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <Icon
                                :name="getConflictIcon(conflict.conflict_severity)"
                                class="h-4 w-4"
                                :class="getConflictIconClass(conflict.conflict_severity)"
                            />
                            <div>
                                <h4 class="text-sm font-medium">
                                    {{ conflict.unit.code }} - {{ conflict.unit.name }}
                                    <span v-if="conflict.section_code" class="text-xs opacity-75"> (Section {{ conflict.section_code }}) </span>
                                </h4>
                                <p class="mt-1 text-xs opacity-75">
                                    {{ conflict.conflict_description }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <Badge :variant="getSeverityBadgeVariant(conflict.conflict_severity)">
                                {{ formatSeverity(conflict.conflict_severity) }}
                            </Badge>
                            <Icon :name="expandedConflicts.includes(index) ? 'chevron-up' : 'chevron-down'" class="h-4 w-4 transition-transform" />
                        </div>
                    </div>
                </div>

                <!-- Conflict Details (Expandable) -->
                <div v-if="expandedConflicts.includes(index)" class="border-t bg-gray-50 px-4 py-3">
                    <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <!-- Schedule Information -->
                        <div>
                            <h5 class="mb-2 font-medium text-gray-900">Schedule Details</h5>
                            <div class="space-y-1 text-gray-600">
                                <div>
                                    <span class="font-medium">Days:</span>
                                    {{ conflict.schedule.days.join(', ') || 'Not specified' }}
                                </div>
                                <div>
                                    <span class="font-medium">Time:</span>
                                    {{ formatTime(conflict.schedule.time_start, conflict.schedule.time_end) }}
                                </div>
                                <div>
                                    <span class="font-medium">Location:</span>
                                    {{ conflict.schedule.location || 'TBD' }}
                                </div>
                                <div>
                                    <span class="font-medium">Delivery:</span>
                                    {{ formatDeliveryMode(conflict.delivery_mode) }}
                                </div>
                            </div>
                        </div>

                        <!-- Course Information -->
                        <div>
                            <h5 class="mb-2 font-medium text-gray-900">Course Information</h5>
                            <div class="space-y-1 text-gray-600">
                                <div>
                                    <span class="font-medium">Semester:</span>
                                    {{ conflict.semester.name }}
                                </div>
                                <div>
                                    <span class="font-medium">Enrollment:</span>
                                    {{ conflict.enrollment.current }}/{{ conflict.enrollment.maximum }}
                                </div>
                                <div>
                                    <span class="font-medium">Conflict Type:</span>
                                    {{ formatConflictType(conflict.conflict_type) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resolution Suggestions -->
                    <div v-if="conflict.resolution_suggestions.length > 0" class="mt-4">
                        <h5 class="mb-2 font-medium text-gray-900">Resolution Suggestions</h5>
                        <ul class="space-y-1 text-sm text-gray-600">
                            <li v-for="suggestion in conflict.resolution_suggestions" :key="suggestion" class="flex items-start">
                                <Icon name="lightbulb" class="mt-0.5 mr-2 h-4 w-4 flex-shrink-0 text-yellow-500" />
                                {{ suggestion }}
                            </li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4 flex items-center space-x-2">
                        <Button variant="outline" size="sm" @click="viewConflictingCourse(conflict)">
                            <Icon name="eye" class="mr-1 h-4 w-4" />
                            View Course
                        </Button>
                        <Button variant="outline" size="sm" @click="suggestAlternatives(conflict)">
                            <Icon name="users" class="mr-1 h-4 w-4" />
                            Find Alternatives
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conflict Actions -->
        <div class="rounded-lg bg-gray-50 p-4">
            <h4 class="mb-3 text-sm font-medium text-gray-900">Conflict Resolution Options</h4>
            <div class="space-y-2">
                <label class="flex items-start">
                    <input
                        v-model="resolutionOption"
                        type="radio"
                        value="cancel"
                        class="focus:ring-opacity-50 mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                    />
                    <div class="ml-3">
                        <span class="text-sm font-medium text-gray-900">Cancel Assignment</span>
                        <p class="text-xs text-gray-600">Do not assign this lecturer due to conflicts</p>
                    </div>
                </label>

                <label class="flex items-start">
                    <input
                        v-model="resolutionOption"
                        type="radio"
                        value="force"
                        class="focus:ring-opacity-50 mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                    />
                    <div class="ml-3">
                        <span class="text-sm font-medium text-gray-900">Force Assignment</span>
                        <p class="text-xs text-gray-600">Proceed with assignment despite conflicts (requires approval)</p>
                    </div>
                </label>

                <label class="flex items-start">
                    <input
                        v-model="resolutionOption"
                        type="radio"
                        value="reschedule"
                        class="focus:ring-opacity-50 mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                    />
                    <div class="ml-3">
                        <span class="text-sm font-medium text-gray-900">Request Reschedule</span>
                        <p class="text-xs text-gray-600">Contact administration to reschedule conflicting courses</p>
                    </div>
                </label>

                <label class="flex items-start">
                    <input
                        v-model="resolutionOption"
                        type="radio"
                        value="alternative"
                        class="focus:ring-opacity-50 mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                    />
                    <div class="ml-3">
                        <span class="text-sm font-medium text-gray-900">Find Alternative Lecturer</span>
                        <p class="text-xs text-gray-600">Search for another available lecturer without conflicts</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div v-if="showStatistics" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h4 class="mb-2 text-sm font-medium text-blue-900">Conflict Analysis</h4>
            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                <div class="text-center">
                    <div class="text-lg font-bold text-blue-900">{{ criticalConflicts }}</div>
                    <div class="text-xs text-blue-700">Critical</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-blue-900">{{ highConflicts }}</div>
                    <div class="text-xs text-blue-700">High</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-blue-900">{{ mediumConflicts }}</div>
                    <div class="text-xs text-blue-700">Medium</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-blue-900">{{ lowConflicts }}</div>
                    <div class="text-xs text-blue-700">Low</div>
                </div>
            </div>
        </div>
    </div>

    <!-- No Conflicts State -->
    <div v-else-if="showNoConflictsMessage" class="rounded-lg border border-green-200 bg-green-50 p-4">
        <div class="flex items-center">
            <Icon name="check-circle" class="mr-3 h-5 w-5 text-green-500" />
            <div>
                <h3 class="text-sm font-medium text-green-800">No Conflicts Detected</h3>
                <p class="mt-1 text-sm text-green-700">The selected lecturer can be assigned without any schedule conflicts.</p>
            </div>
        </div>
    </div>
</template>
