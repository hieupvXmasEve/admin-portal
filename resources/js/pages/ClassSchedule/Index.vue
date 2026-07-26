<script setup lang="ts">
import ScheduleEditDrawer from '@/components/schedule/ScheduleEditDrawer.vue';
import ScheduleGridView from '@/components/schedule/ScheduleGridView.vue';
import { useAdminSchedule } from '@/composables/useAdminSchedule';
import type { ScheduleSession } from '@/types/schedule';
import { Head } from '@inertiajs/vue3';
import { addDays, format, startOfWeek } from 'date-fns';
import { Calendar } from 'lucide-vue-next';
import { ref } from 'vue';

// Composable
const scheduleApi = useAdminSchedule();

// Local state
const selectedSession = ref<ScheduleSession | null>(null);
const isEditDrawerOpen = ref(false);
const selectedWeek = ref(new Date());

// Methods
const handleSessionClick = (session: ScheduleSession) => {
    selectedSession.value = session;
    scheduleApi.setSelectedSession(session);
    isEditDrawerOpen.value = true;
};

const handleSessionUpdated = async () => {
    // Close drawer first
    isEditDrawerOpen.value = false;
    selectedSession.value = null;

    // Refresh the grid for the current week
    const startDate = startOfWeek(selectedWeek.value, { weekStartsOn: 1 });
    const endDate = addDays(startDate, 6);
    await scheduleApi.fetchSessions(
        {
            date_range: {
                start: format(startDate, 'yyyy-MM-dd'),
                end: format(endDate, 'yyyy-MM-dd'),
            },
        },
        { force: true },
    );
};

const handleWeekChange = (date: Date) => {
    selectedWeek.value = date;
};

const handleEditDrawerClose = () => {
    isEditDrawerOpen.value = false;
    selectedSession.value = null;
};

const handleOverlappingSessionsClick = () => {
    // The overlapping sessions modal will handle the UI
    // Individual session clicks from the modal will trigger handleSessionClick
};

// Initialize on mount
// onMounted(async () => {
//     // Load filter options and initial data
//     await scheduleApi.fetchFilterOptions();
// });
</script>

<template>
    <Head title="Schedule Management" />

    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <h1 class="flex items-center space-x-3 text-3xl font-bold tracking-tight">
                <Calendar class="text-primary h-8 w-8" />
                <span>Schedule Management</span>
            </h1>
            <p class="text-muted-foreground">View and manage class schedules across all campuses and semesters</p>
        </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <!-- Filters Sidebar -->
        <!--        <aside class="lg:col-span-1">-->
        <!--            <div class="sticky top-6">-->
        <!--                <ScheduleFilterPanel />-->
        <!--            </div>-->
        <!--        </aside>-->

        <!-- Schedule Grid -->
        <main class="lg:col-span-4">
            <ScheduleGridView :selected-week="selectedWeek" @session-click="handleSessionClick" @week-change="handleWeekChange" @overlapping-sessions-click="handleOverlappingSessionsClick" />
        </main>
    </div>

    <!-- Edit Session Drawer -->
    <ScheduleEditDrawer v-model:open="isEditDrawerOpen" :session="selectedSession" @session-updated="handleSessionUpdated" @update:open="handleEditDrawerClose" />
</template>

<style scoped></style>
