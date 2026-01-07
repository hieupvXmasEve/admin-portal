<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAdminSchedule } from '@/composables/useAdminSchedule';
import { useScheduleManagement } from '@/composables/useScheduleManagement';
import type { ScheduleSession } from '@/types/schedule';
import { useDebounceFn } from '@vueuse/core';
import { addDays, format, startOfWeek } from 'date-fns';
import { Calendar, ChevronLeft, ChevronRight, Users } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import OverlappingSessionsModal from './OverlappingSessionsModal.vue';

const scheduleApi = useAdminSchedule();
const scheduleUtils = useScheduleManagement();

// Props
interface Props {
    selectedWeek?: Date;
}

const props = withDefaults(defineProps<Props>(), {
    selectedWeek: () => new Date(),
});

// Emits
const emit = defineEmits<{
    sessionClick: [session: ScheduleSession];
    weekChange: [date: Date];
    overlappingSessionsClick: [sessions: ScheduleSession[], date: string, timeSlot: string];
}>();

// Use schedule management utilities
const timeSlots = scheduleUtils.timeSlots;
const weekDays = scheduleUtils.weekDays;

// Set the selected week in schedule utils
watch(
    () => props.selectedWeek,
    (newWeek) => {
        if (newWeek) {
            scheduleUtils.setWeek(newWeek);
        }
    },
    { immediate: true },
);

// Unit Type Filtering
const selectedUnitType = computed({
    get: () => scheduleApi.filters.value.unit_type || 'all',
    set: (value: string) => {
        const type = value === 'all' ? undefined : value;
        scheduleApi.applyFilters({ unit_type: type });
    },
});

// Computed Schedule Matrix for O(1) access
const scheduleMatrix = computed(() => {
    return scheduleUtils.buildScheduleMatrix(scheduleApi.sessions.value);
});

// Use utility functions from schedule management composable
const getSessionsForSlot = (dateString: string, hour: number) => {
    return scheduleUtils.getSessionsFromMatrix(scheduleMatrix.value, dateString, hour);
};

// Get sessions that actually start at this time slot (for width calculation)
const getSessionsStartingAtSlot = (dateString: string, hour: number) => {
    const allSessions = getSessionsForSlot(dateString, hour);
    return allSessions.filter((session) => scheduleUtils.sessionStartsAtSlot(session, hour));
};

const getSessionSpan = scheduleUtils.getSessionSpan;

// Calculate style for session card
const getSessionStyle = (session: ScheduleSession) => {
    const { colStart, totalCols } = scheduleUtils.getSessionVisualState(session, scheduleMatrix.value);
    const span = getSessionSpan(session);

    // Basic height calculation (60px per hour row - 8px spacing)
    const height = `${span * 60 - 8}px`;

    // Width and Position
    // We want some padding/margin for visual separation
    const leftPercent = ((colStart - 1) / totalCols) * 100;
    const widthPercent = (1 / totalCols) * 100;

    return {
        height,
        minHeight: '52px',
        left: `calc(${leftPercent}% + 2px)`,
        width: `calc(${widthPercent}% - 4px)`,
    };
};

// Local state for overlapping sessions modal
const overlappingModalOpen = ref(false);
const overlappingSessions = ref<ScheduleSession[]>([]);
const overlappingTimeSlot = ref('');
const overlappingDate = ref('');

// Get session badge color based on status
const getSessionBadgeColor = (status: string) => {
    const variant = scheduleUtils.getSessionStatusBadgeVariant(status);
    // Map unsupported badge variants to supported ones
    if (variant === 'warning' || variant === 'success') {
        return variant === 'warning' ? 'secondary' : 'outline';
    }
    return variant;
};

// Navigation functions
const navigatePrevWeek = () => {
    scheduleUtils.navigateWeek('prev');
    emit('weekChange', scheduleUtils.selectedWeek.value);
};

const navigateNextWeek = () => {
    scheduleUtils.navigateWeek('next');
    emit('weekChange', scheduleUtils.selectedWeek.value);
};

const goToToday = () => {
    scheduleUtils.goToToday();
    emit('weekChange', scheduleUtils.selectedWeek.value);
};

// Session click handler
const handleSessionClick = (session: ScheduleSession) => {
    emit('sessionClick', session);
};

// Handle overlapping sessions click
const handleOverlappingSessionsClick = (sessions: ScheduleSession[], date: string, timeSlot: string) => {
    overlappingSessions.value = sessions;
    overlappingDate.value = date;
    overlappingTimeSlot.value = timeSlot;
    overlappingModalOpen.value = true;
};

// Handle edit session from modal
const handleEditSessionFromModal = (session: ScheduleSession) => {
    emit('sessionClick', session);
};

// Current abort controller for canceling requests
let currentAbortController: AbortController | null = null;

// Create debounced function for fetching sessions
const debouncedFetchSessions = useDebounceFn(async (newWeek: Date) => {
    // Cancel any pending API call
    if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
    }

    const startDate = startOfWeek(newWeek, { weekStartsOn: 1 });
    const endDate = addDays(startDate, 6);

    const range = {
        start: format(startDate, 'yyyy-MM-dd'),
        end: format(endDate, 'yyyy-MM-dd'),
    };

    // Update filters in store so they persist for other filter operations
    scheduleApi.setFilters({ date_range: range });

    // Create new abort controller for this request
    currentAbortController = new AbortController();

    try {
        await scheduleApi.fetchSessions(
            {
                date_range: range,
            },
            { force: true, signal: currentAbortController.signal },
        );
    } catch (error) {
        // Only log non-abort errors
        if (error instanceof Error && error.name !== 'AbortError') {
            console.error('Failed to fetch sessions:', error);
        }
    } finally {
        currentAbortController = null;
    }
}, 300); // 300ms debounce delay

// Watch for week changes and trigger debounced fetch
watch(() => scheduleUtils.selectedWeek.value, debouncedFetchSessions, { immediate: true });

// Initialize
onMounted(async () => {
    if (props.selectedWeek) {
        scheduleUtils.setWeek(props.selectedWeek);
    }

    // Load filter options if not already loaded
    if (!scheduleApi.filterOptions.value) {
        await scheduleApi.fetchFilterOptions();
    }
});
</script>

<template>
    <div class="w-full space-y-4">
        <!-- Week Navigation Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h2 class="min-w-48 text-2xl font-bold">
                    {{ format(scheduleUtils.selectedWeek.value, 'MMMM yyyy') }}
                </h2>
                <div class="flex items-center space-x-2">
                    <Button variant="outline" size="sm" @click="navigatePrevWeek" :disabled="scheduleApi.isWeekTransitionLoading.value">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="goToToday" :disabled="scheduleApi.isWeekTransitionLoading.value">
                        <Calendar class="mr-2 h-4 w-4" />
                        Today
                    </Button>
                    <Button variant="outline" size="sm" @click="navigateNextWeek" :disabled="scheduleApi.isWeekTransitionLoading.value">
                        <ChevronRight class="h-4 w-4" />
                    </Button>

                    <div class="bg-border mx-2 h-8 w-px"></div>

                    <!-- Unit Type Filter -->
                    <Select v-model="selectedUnitType" :disabled="scheduleApi.isWeekTransitionLoading.value || scheduleApi.isLoading.value">
                        <SelectTrigger class="h-9 w-[160px]">
                            <SelectValue placeholder="Filter by Unit Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Unit Type</SelectLabel>
                                <SelectItem value="all">All Types</SelectItem>
                                <SelectItem v-for="type in scheduleApi.filterOptions.value?.unit_types || []" :key="type" :value="type">
                                    {{ type }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="text-muted-foreground flex items-center space-x-2 text-sm">
                <span>{{ scheduleApi.sessionsCount.value }} sessions this week</span>
                <div v-if="scheduleApi.isWeekTransitionLoading.value || scheduleApi.isLoading.value" class="flex items-center space-x-1">
                    <div class="border-primary h-3 w-3 animate-spin rounded-full border-b-2"></div>
                    <span class="text-xs">Loading...</span>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div v-if="scheduleApi.hasError.value" class="py-8 text-center">
            <p class="text-destructive">{{ scheduleApi.error.value }}</p>
            <Button variant="outline" size="sm" class="mt-2" @click="scheduleApi.clearError"> Dismiss </Button>
        </div>

        <!-- Schedule Grid -->
        <div v-else class="bg-background relative overflow-hidden rounded-lg border">
            <!-- Loading Overlay -->
            <div v-if="scheduleApi.isLoading.value || scheduleApi.isWeekTransitionLoading.value" class="bg-background/60 absolute inset-0 z-50 flex items-center justify-center backdrop-blur-[1px]">
                <div class="bg-background/95 flex items-center space-x-3 rounded-full border px-4 py-2 shadow-sm">
                    <div class="border-primary h-4 w-4 animate-spin rounded-full border-b-2"></div>
                    <span class="text-muted-foreground text-sm font-medium">Loading schedule...</span>
                </div>
            </div>

            <!-- Empty State Overlay -->
            <div v-if="!scheduleApi.isLoading.value && !scheduleApi.isWeekTransitionLoading.value && scheduleApi.sessionsCount.value === 0" class="bg-background/40 absolute inset-0 z-40 flex flex-col items-center justify-center">
                <div class="bg-background/90 text-muted-foreground flex flex-col items-center rounded-xl border p-6 text-center shadow-sm">
                    <Calendar class="mx-auto mb-3 h-10 w-10 opacity-50" />
                    <p class="font-medium">No sessions scheduled</p>
                    <p class="text-xs">Sessions will appear here when scheduled</p>
                </div>
            </div>
            <!-- Header with days -->
            <div class="bg-muted/50 grid grid-cols-8">
                <div class="border-r p-3 font-medium">Time</div>
                <div v-for="day in weekDays" :key="day.dateString" class="border-r p-3 text-center last:border-r-0" :class="{ 'bg-primary/10 font-semibold': day.isToday }">
                    <div class="font-medium">{{ day.displayDate }}</div>
                    <div class="text-muted-foreground mt-1 text-xs">
                        {{ day.fullDate }}
                    </div>
                </div>
            </div>

            <!-- Time slots grid -->
            <div class="relative">
                <div v-for="(timeSlot, slotIndex) in timeSlots" :key="timeSlot.hour" class="grid grid-cols-8 border-b last:border-b-0" :class="{ 'bg-muted/20': slotIndex % 2 === 1 }">
                    <!-- Time column -->
                    <div class="text-muted-foreground my-auto border-r p-3 text-center font-mono text-sm">
                        {{ timeSlot.displayTime }}
                    </div>

                    <!-- Day columns -->
                    <div v-for="day in weekDays" :key="`${day.dateString}-${timeSlot.hour}`" class="relative min-h-[80px] border-r last:border-r-0">
                        <!-- Check for overlapping sessions -->
                        <template v-if="scheduleUtils.shouldShowOverlapIndicator(getSessionsForSlot(day.dateString, timeSlot.hour))">
                            <!-- Overlap indicator for multiple sessions - STATIC POSITIONING to allow expansion -->
                            <div
                                class="bg-primary/10 border-primary/30 hover:bg-primary/20 m-1 flex cursor-pointer flex-col overflow-hidden rounded-lg border-2 border-dashed p-1 transition-colors"
                                @click="handleOverlappingSessionsClick(getSessionsForSlot(day.dateString, timeSlot.hour), day.dateString, timeSlot.displayTime)"
                            >
                                <div class="border-primary/20 mb-0.5 flex items-center justify-between border-b pb-0.5">
                                    <div class="text-primary flex items-center gap-1 text-[10px] font-bold tracking-wider uppercase">
                                        <Users class="h-3 w-3" />
                                        <span>Total ({{ scheduleUtils.getOverlappingSessionsCount(getSessionsForSlot(day.dateString, timeSlot.hour)) }})</span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <div
                                        v-for="session in getSessionsForSlot(day.dateString, timeSlot.hour).slice(0, 10)"
                                        :key="session.id"
                                        class="bg-background/60 text-primary truncate rounded px-1 py-0.5 text-[10px] font-medium shadow-sm"
                                        :title="`${session.unitCode} - ${session.section}`"
                                    >
                                        {{ session.unitCode }} - {{ session.section }}
                                    </div>
                                    <div v-if="getSessionsForSlot(day.dateString, timeSlot.hour).length > 10" class="text-primary text-center text-[10px] italic">+{{ getSessionsForSlot(day.dateString, timeSlot.hour).length - 10 }} more...</div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overlapping Sessions Modal -->
        <OverlappingSessionsModal v-model:open="overlappingModalOpen" :sessions="overlappingSessions" :time-slot="overlappingTimeSlot" :date="overlappingDate" @edit-session="handleEditSessionFromModal" />
    </div>
</template>

<style scoped>
/* Ensure proper grid layout */
.grid-cols-8 {
    grid-template-columns: 120px repeat(7, 1fr);
}

/* Custom scrollbar for the grid container */
.overflow-hidden::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.overflow-hidden::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.overflow-hidden::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.overflow-hidden::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .grid-cols-8 {
        grid-template-columns: 80px repeat(7, 1fr);
    }
}

@media (max-width: 768px) {
    .grid-cols-8 {
        grid-template-columns: 60px repeat(7, minmax(100px, 1fr));
    }
}
</style>
