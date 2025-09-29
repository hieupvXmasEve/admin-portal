<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { computed } from 'vue';

interface EventHistory {
    id: number;
    title: string;
    start_time: string;
    end_time: string;
    location: string;
    status: string;
    creator: string;
    gold_reward_amount: number;
    max_participants: number | null;
    statistics: {
        registered: number;
        checked_in: number;
        completed: number;
        cancelled: number;
        success_rate: number;
        total_gold_awarded: number;
    };
    financial_impact: {
        potential_gold_cost: number;
        actual_gold_cost: number;
        cost_efficiency: number;
    };
}

interface Pagination {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
}

interface Props {
    events: EventHistory[];
    pagination: Pagination;
}

interface Emits {
    (e: 'page-change', page: number): void;
    (e: 'export-participants', eventId: number): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const formatDateTime = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatTime = (dateString: string): string => {
    return new Date(dateString).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
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

const getVisiblePages = computed(() => {
    const current = props.pagination.current_page;
    const last = props.pagination.last_page;
    const pages: number[] = [];

    // Always show first page
    if (current > 3) {
        pages.push(1);
        if (current > 4) pages.push(-1); // Ellipsis marker
    }

    // Show pages around current
    for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
        pages.push(i);
    }

    // Always show last page
    if (current < last - 2) {
        if (current < last - 3) pages.push(-1); // Ellipsis marker
        pages.push(last);
    }

    return pages.filter((page, index, arr) => arr.indexOf(page) === index);
});

const viewEvent = (eventId: number) => {
    router.visit(`/events/${eventId}`);
};
</script>

<template>
    <div class="space-y-4">
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="p-2 text-left">Event</th>
                        <th class="p-2 text-left">Date & Time</th>
                        <th class="p-2 text-left">Location</th>
                        <th class="p-2 text-left">Status</th>
                        <th class="p-2 text-left">Creator</th>
                        <th class="p-2 text-right">Participants</th>
                        <th class="p-2 text-right">Success Rate</th>
                        <th class="p-2 text-right">Gold Impact</th>
                        <th class="p-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="event in events" :key="event.id" class="border-b hover:bg-gray-50">
                        <td class="p-2">
                            <div class="font-medium">{{ event.title }}</div>
                            <div class="text-xs text-gray-500">ID: {{ event.id }}</div>
                        </td>
                        <td class="p-2">
                            <div class="text-sm">{{ formatDateTime(event.start_time) }}</div>
                            <div class="text-xs text-gray-500">to {{ formatTime(event.end_time) }}</div>
                        </td>
                        <td class="p-2 text-gray-600">{{ event.location }}</td>
                        <td class="p-2">
                            <span :class="getStatusColor(event.status)" class="rounded-full px-2 py-1 text-xs font-medium">
                                {{ event.status }}
                            </span>
                        </td>
                        <td class="p-2 text-gray-600">{{ event.creator }}</td>
                        <td class="p-2 text-right">
                            <div class="text-sm">
                                <span class="font-medium">{{ event.statistics.registered }}</span> reg
                            </div>
                            <div class="text-xs text-gray-500">{{ event.statistics.completed }} completed</div>
                        </td>
                        <td class="p-2 text-right">
                            <span :class="getRateColor(event.statistics.success_rate)" class="font-medium">
                                {{ formatPercentage(event.statistics.success_rate) }}
                            </span>
                        </td>
                        <td class="p-2 text-right">
                            <div class="text-sm font-medium text-yellow-600">
                                {{ formatCurrency(event.statistics.total_gold_awarded) }}
                            </div>
                            <div class="text-xs text-gray-500">of {{ formatCurrency(event.financial_impact.potential_gold_cost) }}</div>
                        </td>
                        <td class="p-2 text-center">
                            <div class="flex items-center justify-center space-x-1">
                                <!-- <Button @click="$emit('export-participants', event.id)" variant="ghost" size="sm" class="h-8 w-8 p-0" title="Export Participants">
                                    <Download class="h-4 w-4" />
                                </Button> -->
                                <Button @click="viewEvent(event.id)" variant="ghost" size="sm" class="h-8 w-8 p-0" title="View Event">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between">
            <div class="text-sm text-gray-600">Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} events</div>
            <div class="flex items-center space-x-2">
                <Button @click="$emit('page-change', pagination.current_page - 1)" :disabled="pagination.current_page <= 1" variant="outline" size="sm"> Previous </Button>

                <div class="flex items-center space-x-1">
                    <Button v-for="page in getVisiblePages()" :key="page" @click="$emit('page-change', page)" :variant="page === pagination.current_page ? 'default' : 'outline'" size="sm" class="h-8 w-8 p-0">
                        {{ page }}
                    </Button>
                </div>

                <Button @click="$emit('page-change', pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" variant="outline" size="sm"> Next </Button>
            </div>
        </div>
    </div>
</template>
