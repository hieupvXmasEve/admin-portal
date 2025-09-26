<script setup lang="ts">
import EventParticipantList from '@/components/EventParticipantList.vue';
import QRScanner from '@/components/QRScanner.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useQRScanner } from '@/composables/useQRScanner';
import type { Event, EventParticipant } from '@/types/event';
import { formatDateTime } from '@/utils/date';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    event: Event;
}

interface ActivityItem {
    id: string;
    student_name: string;
    action: string;
    event_title: string;
    timestamp: string;
    status: string;
}

const props = defineProps<Props>();

// State
const selectedEvent = ref<Event>(props.event);
const eventStatistics = ref<any>(null);
const recentActivity = ref<ActivityItem[]>([]);

// Success toast
const showSuccessToast = ref(false);
const successMessage = ref('');

// Composables
const { isCameraSupported, getEventStatistics } = useQRScanner();

// Methods
const onEventChanged = (event: Event) => {
    selectedEvent.value = event;
    eventStatistics.value = null;
    recentActivity.value = [];

    loadEventStatistics();
};

const onCheckinSuccess = (participant: EventParticipant) => {
    // Add to recent activity
    recentActivity.value.unshift({
        id: `${participant.id}-${Date.now()}`,
        student_name: participant.student?.full_name || 'Unknown Student',
        action: 'checked into',
        event_title: selectedEvent.value?.title || 'Unknown Event',
        timestamp: participant.checkin_time || new Date().toISOString(),
        status: 'Checked In',
    });

    // Keep only last 10 activities
    if (recentActivity.value.length > 10) {
        recentActivity.value = recentActivity.value.slice(0, 10);
    }

    // Show success toast
    showSuccessToast.value = true;
    successMessage.value = `${participant.student?.full_name} checked in successfully!`;

    setTimeout(() => {
        showSuccessToast.value = false;
    }, 3000);

    // Refresh statistics
    loadEventStatistics();
};

const onParticipantUpdated = (participant: EventParticipant) => {
    // Refresh statistics when participant is updated
    loadEventStatistics();
};

const loadEventStatistics = async () => {
    if (!selectedEvent.value) return;

    try {
        const response = await getEventStatistics(selectedEvent.value.id);
        eventStatistics.value = response.data;
    } catch (error) {
        console.error('Failed to load event statistics:', error);
    }
};

// Auto-refresh statistics every 30 seconds
let statisticsInterval: NodeJS.Timeout;

const startStatisticsRefresh = () => {
    statisticsInterval = setInterval(() => {
        if (selectedEvent.value) {
            loadEventStatistics();
        }
    }, 30000); // 30 seconds
};

const stopStatisticsRefresh = () => {
    if (statisticsInterval) {
        clearInterval(statisticsInterval);
    }
};

// Lifecycle
onMounted(() => {
    onEventChanged(props.event);
    startStatisticsRefresh();
});

onUnmounted(() => {
    stopStatisticsRefresh();
});
</script>

<template>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Event QR Scanner</h1>
            <p class="text-muted-foreground">Scan QR codes to check students into events</p>
        </div>
        <div class="flex items-center gap-2">
            <Button variant="outline" @click="router.visit(route('events.show', selectedEvent.id))">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Event
            </Button>
        </div>
    </div>

    <div class="space-y-8">
        <!-- Camera Support Check -->
        <div v-if="!isCameraSupported" class="mb-6">
            <Alert variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Camera Not Supported</AlertTitle>
                <AlertDescription> Your browser or device doesn't support camera access. You can still use manual check-in below. </AlertDescription>
            </Alert>
        </div>

        <!-- QR Scanner Component -->
        <div class="bg-card rounded-lg border p-6">
            <QRScanner :event="selectedEvent" @checkin-success="onCheckinSuccess" />
        </div>

        <!-- Participant Management -->
        <div v-if="selectedEvent" class="bg-card rounded-lg border p-6">
            <EventParticipantList :event="selectedEvent" @participant-updated="onParticipantUpdated" />
        </div>

        <!-- Event Statistics Summary -->
        <div v-if="selectedEvent && eventStatistics" class="bg-card rounded-lg border p-6">
            <h3 class="mb-4 text-lg font-semibold">Event Summary</h3>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Participation Overview -->
                <div>
                    <h4 class="mb-3 font-medium">Participation Overview</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Total Registered:</span>
                            <span class="font-medium">{{ eventStatistics.total_registered }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Checked In:</span>
                            <span class="font-medium">{{ eventStatistics.checked_in }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Completed:</span>
                            <span class="font-medium">{{ eventStatistics.completed }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Participation Rate:</span>
                            <span class="font-medium">
                                {{ eventStatistics.participation_rate ? `${eventStatistics.participation_rate.toFixed(1)}%` : '0%' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Capacity Information -->
                <div>
                    <h4 class="mb-3 font-medium">Capacity Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Max Participants:</span>
                            <span class="font-medium">
                                {{ selectedEvent.max_participants || 'Unlimited' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Available Spots:</span>
                            <span class="font-medium">
                                {{ eventStatistics.available_spots ?? 'Unlimited' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Capacity Status:</span>
                            <Badge :variant="eventStatistics.capacity_reached ? 'destructive' : 'success'">
                                {{ eventStatistics.capacity_reached ? 'Full' : 'Available' }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <!-- Gold Rewards -->
                <div>
                    <h4 class="mb-3 font-medium">Gold Rewards</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Reward Amount:</span>
                            <span class="font-medium">{{ selectedEvent.gold_reward_amount }} Gold</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Gold Awarded:</span>
                            <span class="font-medium">{{ eventStatistics.gold_awarded_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground text-sm">Total Distributed:</span>
                            <span class="font-medium">{{ eventStatistics.total_gold_awarded }} Gold</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div v-if="recentActivity.length > 0" class="bg-card rounded-lg border p-6">
            <h3 class="mb-4 text-lg font-semibold">Recent Activity</h3>

            <div class="space-y-3">
                <div v-for="activity in recentActivity" :key="activity.id" class="bg-muted flex items-center gap-3 rounded-lg p-3">
                    <div class="bg-success h-2 w-2 rounded-full"></div>
                    <div class="flex-1">
                        <p class="text-sm">
                            <span class="font-medium">{{ activity.student_name }}</span>
                            {{ activity.action }}
                            <span class="font-medium">{{ activity.event_title }}</span>
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{ formatDateTime(activity.timestamp) }}
                        </p>
                    </div>
                    <Badge variant="success">
                        {{ activity.status }}
                    </Badge>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast -->
    <div v-if="showSuccessToast" class="bg-success text-success-foreground fixed right-4 bottom-4 z-50 rounded-lg px-4 py-2 shadow-lg">
        <div class="flex items-center gap-2">
            <CheckCircle class="h-4 w-4" />
            <span>{{ successMessage }}</span>
        </div>
    </div>
</template>

<style scoped>
/* Custom styles for the QR scanner page */
</style>
