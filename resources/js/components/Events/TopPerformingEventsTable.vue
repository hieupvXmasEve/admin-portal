<script setup lang="ts">
interface TopEvent {
    event_id: number;
    title: string;
    start_time: string;
    status: string;
    registrations: number;
    completions: number;
    success_rate: number;
    gold_awarded: number;
}

interface Props {
    events: TopEvent[];
}

const props = defineProps<Props>();

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

const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const getMedalClass = (index: number): string => {
    const classes = [
        'bg-yellow-500', // Gold
        'bg-gray-400', // Silver
        'bg-amber-600', // Bronze
    ];
    return classes[index] || 'bg-gray-300';
};

const getStatusColor = (status: string): string => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800',
        published: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
};

const getRateColor = (rate: number): string => {
    if (rate >= 80) return 'text-green-600';
    if (rate >= 60) return 'text-yellow-600';
    if (rate >= 40) return 'text-orange-600';
    return 'text-red-600';
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2 text-left">Rank</th>
                    <th class="p-2 text-left">Event</th>
                    <th class="p-2 text-left">Date</th>
                    <th class="p-2 text-left">Status</th>
                    <th class="p-2 text-right">Registrations</th>
                    <th class="p-2 text-right">Completions</th>
                    <th class="p-2 text-right">Success Rate</th>
                    <th class="p-2 text-right">Gold Awarded</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(event, index) in events" :key="event.event_id" class="border-b hover:bg-gray-50">
                    <td class="p-2">
                        <div class="flex items-center">
                            <span v-if="index < 3" :class="getMedalClass(index)" class="mr-2 flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white">
                                {{ index + 1 }}
                            </span>
                            <span v-else class="font-medium text-gray-500">{{ index + 1 }}</span>
                        </div>
                    </td>
                    <td class="p-2">
                        <div class="font-medium">{{ event.title }}</div>
                    </td>
                    <td class="p-2 text-gray-600">
                        {{ formatDate(event.start_time) }}
                    </td>
                    <td class="p-2">
                        <span :class="getStatusColor(event.status)" class="rounded-full px-2 py-1 text-xs font-medium">
                            {{ event.status }}
                        </span>
                    </td>
                    <td class="p-2 text-right">{{ event.registrations }}</td>
                    <td class="p-2 text-right">{{ event.completions }}</td>
                    <td class="p-2 text-right">
                        <span :class="getRateColor(event.success_rate)" class="font-medium">
                            {{ formatPercentage(event.success_rate) }}
                        </span>
                    </td>
                    <td class="p-2 text-right font-medium text-yellow-600">
                        {{ formatCurrency(event.gold_awarded) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
