<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';

interface EventMetric {
    event_id: number;
    event_title: string;
    start_time: string;
    registrations: number;
    checkins: number;
    completions: number;
    checkin_rate: number;
    completion_rate: number;
    overall_success_rate: number;
}

interface Props {
    events: EventMetric[];
}

const props = defineProps<Props>();
const showAll = ref(false);

const displayedEvents = computed(() => {
    return showAll.value ? props.events : props.events.slice(0, 10);
});

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatPercentage = (value: number): string => {
    return `${Math.round(value)}%`;
};

const getRateColor = (rate: number): string => {
    if (rate >= 80) return 'text-green-600 font-medium';
    if (rate >= 60) return 'text-yellow-600 font-medium';
    if (rate >= 40) return 'text-orange-600 font-medium';
    return 'text-red-600 font-medium';
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2 text-left">Event</th>
                    <th class="p-2 text-left">Date</th>
                    <th class="p-2 text-right">Registrations</th>
                    <th class="p-2 text-right">Check-ins</th>
                    <th class="p-2 text-right">Completions</th>
                    <th class="p-2 text-right">Check-in Rate</th>
                    <th class="p-2 text-right">Completion Rate</th>
                    <th class="p-2 text-right">Success Rate</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="event in displayedEvents" :key="event.event_id" class="border-b hover:bg-gray-50">
                    <td class="p-2">
                        <div class="font-medium">{{ event.event_title }}</div>
                    </td>
                    <td class="p-2 text-gray-600">
                        {{ formatDate(event.start_time) }}
                    </td>
                    <td class="p-2 text-right">{{ event.registrations }}</td>
                    <td class="p-2 text-right">{{ event.checkins }}</td>
                    <td class="p-2 text-right">{{ event.completions }}</td>
                    <td class="p-2 text-right">
                        <span :class="getRateColor(event.checkin_rate)">
                            {{ formatPercentage(event.checkin_rate) }}
                        </span>
                    </td>
                    <td class="p-2 text-right">
                        <span :class="getRateColor(event.completion_rate)">
                            {{ formatPercentage(event.completion_rate) }}
                        </span>
                    </td>
                    <td class="p-2 text-right">
                        <span :class="getRateColor(event.overall_success_rate)">
                            {{ formatPercentage(event.overall_success_rate) }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="events.length > 10" class="mt-4 text-center">
            <Button @click="showAll = !showAll" variant="outline" size="sm">
                {{ showAll ? 'Show Less' : `Show All (${events.length})` }}
            </Button>
        </div>
    </div>
</template>
