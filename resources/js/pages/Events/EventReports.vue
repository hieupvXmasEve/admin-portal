<script setup lang="ts">
import EventHistoryTable from '@/components/Events/EventHistoryTable.vue';
import EventSuccessTable from '@/components/Events/EventSuccessTable.vue';
import GoldDistributionChart from '@/components/Events/GoldDistributionChart.vue';
import MonthlyTrendsChart from '@/components/Events/MonthlyTrendsChart.vue';
import ParticipationPatternChart from '@/components/Events/ParticipationPatternChart.vue';
import ParticipationTrendsChart from '@/components/Events/ParticipationTrendsChart.vue';
import StatusBreakdownChart from '@/components/Events/StatusBreakdownChart.vue';
import TopPerformingEventsTable from '@/components/Events/TopPerformingEventsTable.vue';
import StatsCard from '@/components/StatsCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useApi } from '@/composables';
import { Download, History, RefreshCw } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

interface Props {
    analytics: any;
    filters: {
        date_from: string;
        date_to: string;
        status: string;
    };
    campus: {
        id: number;
        name: string;
    };
}

const props = defineProps<Props>();

const analytics = ref(props.analytics);
const filters = reactive({ ...props.filters });
const eventHistory = ref({ events: [], pagination: {} });
const loading = ref(false);

const api = useApi();

// Computed properties
const getTrend = computed(() => (type: string) => {
    // This would calculate trend based on historical data
    // For now, returning neutral trend
    return { value: 0, direction: 'neutral' as const };
});

// Methods
const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

const formatPercentage = (value: number): string => {
    return `${Math.round(value)}%`;
};

const applyFilters = async () => {
    loading.value = true;
    try {
        const response = await api.get('/events/reports/analytics', {
            params: filters,
        });
        if (response.data?.value?.success) {
            analytics.value = response.data.value?.data;
        }
    } catch (error) {
        console.error('Failed to load analytics:', error);
    } finally {
        loading.value = false;
    }
};

const resetFilters = () => {
    filters.date_from = new Date(Date.now() - 6 * 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    filters.date_to = new Date().toISOString().split('T')[0];
    filters.status = 'all';
    applyFilters();
};

const refreshData = () => {
    applyFilters();
    loadEventHistory();
};

const exportEvents = () => {
    const params = new URLSearchParams(filters as any);
    window.open(`/events/reports/export?${params.toString()}`, '_blank');
};

const exportParticipants = (eventId: number) => {
    window.open(`/events/${eventId}/export-participants`, '_blank');
};

const loadEventHistory = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/events/reports/history', {
            params: { ...filters, page, per_page: 15 },
        });

        if (response.data?.value?.success) {
            eventHistory.value = response.data.value.data;
        }
    } catch (error) {
        console.error('Failed to load event history:', error);
    } finally {
        loading.value = false;
    }
};

const handlePageChange = (page: number) => {
    loadEventHistory(page);
};

// Lifecycle
onMounted(() => {
    loadEventHistory();
});
</script>

<template>
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Event Reports & Analytics</h1>
            <p class="mt-1 text-sm text-gray-600">Comprehensive insights into event performance and participation</p>
        </div>
        <div class="flex items-center space-x-3">
            <Button @click="exportEvents" variant="outline" size="sm">
                <Download class="mr-2 h-4 w-4" />
                Export Events
            </Button>
            <Button @click="refreshData" variant="outline" size="sm">
                <RefreshCw class="mr-2 h-4 w-4" />
                Refresh
            </Button>
        </div>
    </div>

    <!-- Filters -->
    <Card>
        <CardHeader>
            <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <Label for="date_from">From Date</Label>
                    <Input id="date_from" type="date" v-model="filters.date_from" @change="applyFilters" />
                </div>
                <div>
                    <Label for="date_to">To Date</Label>
                    <Input id="date_to" type="date" v-model="filters.date_to" @change="applyFilters" />
                </div>
                <div>
                    <Label for="status">Status</Label>
                    <Select v-model="filters.status" @update:modelValue="applyFilters">
                        <SelectTrigger>
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-end">
                    <Button @click="resetFilters" variant="outline" class="w-full"> Reset Filters </Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Summary Statistics -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatsCard title="Total Events" :value="analytics.summary.total_events" icon="Calendar" :trend="getTrend('events')" />
        <StatsCard title="Total Participants" :value="analytics.summary.total_participants" icon="Users" :trend="getTrend('participants')" />
        <StatsCard title="Gold Distributed" :value="formatCurrency(analytics.summary.total_gold_awarded)" icon="Coins" :trend="getTrend('gold')" />
        <StatsCard title="Avg. Participation" :value="analytics.summary.average_participation_per_event" icon="TrendingUp" :trend="getTrend('avg_participation')" />
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Participation Trends Chart -->
        <Card>
            <CardHeader>
                <CardTitle>Participation Trends</CardTitle>
                <CardDescription>Weekly participation over time</CardDescription>
            </CardHeader>
            <CardContent>
                <ParticipationTrendsChart :data="analytics.participation_trends" />
            </CardContent>
        </Card>

        <!-- Status Breakdown Chart -->
        <Card>
            <CardHeader>
                <CardTitle>Event Status Breakdown</CardTitle>
                <CardDescription>Distribution of event statuses</CardDescription>
            </CardHeader>
            <CardContent>
                <StatusBreakdownChart :data="analytics.status_breakdown" />
            </CardContent>
        </Card>

        <!-- Monthly Trends Chart -->
        <Card>
            <CardHeader>
                <CardTitle>Monthly Trends</CardTitle>
                <CardDescription>Events and participation by month</CardDescription>
            </CardHeader>
            <CardContent>
                <MonthlyTrendsChart :data="analytics.monthly_trends" />
            </CardContent>
        </Card>

        <!-- Gold Distribution Chart -->
        <Card>
            <CardHeader>
                <CardTitle>Gold Distribution</CardTitle>
                <CardDescription>Top events by gold rewards distributed</CardDescription>
            </CardHeader>
            <CardContent>
                <GoldDistributionChart :data="analytics.gold_distribution.gold_by_event" />
            </CardContent>
        </Card>
    </div>

    <!-- Event Success Metrics -->
    <Card>
        <CardHeader>
            <CardTitle>Event Success Metrics</CardTitle>
            <CardDescription>Performance analysis of completed events</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ formatPercentage(analytics.event_success_metrics.average_checkin_rate) }}
                    </div>
                    <div class="text-sm text-gray-600">Average Check-in Rate</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">
                        {{ formatPercentage(analytics.event_success_metrics.average_completion_rate) }}
                    </div>
                    <div class="text-sm text-gray-600">Average Completion Rate</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        {{ formatPercentage(analytics.event_success_metrics.average_overall_success_rate) }}
                    </div>
                    <div class="text-sm text-gray-600">Overall Success Rate</div>
                </div>
            </div>

            <EventSuccessTable :events="analytics.event_success_metrics.event_metrics" />
        </CardContent>
    </Card>

    <!-- Top Performing Events -->
    <Card>
        <CardHeader>
            <CardTitle>Top Performing Events</CardTitle>
            <CardDescription>Events with highest success rates</CardDescription>
        </CardHeader>
        <CardContent>
            <TopPerformingEventsTable :events="analytics.top_performing_events" />
        </CardContent>
    </Card>

    <!-- Participation Patterns -->
    <Card>
        <CardHeader>
            <CardTitle>Participation Patterns</CardTitle>
            <CardDescription>Analysis of when events perform best</CardDescription>
        </CardHeader>
        <CardContent>
            <Tabs defaultValue="day-of-week" class="w-full">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="day-of-week">Day of Week</TabsTrigger>
                    <TabsTrigger value="time-of-day">Time of Day</TabsTrigger>
                    <TabsTrigger value="duration">Duration</TabsTrigger>
                </TabsList>
                <TabsContent value="day-of-week">
                    <ParticipationPatternChart :data="analytics.participation_patterns.by_day_of_week" type="day" />
                </TabsContent>
                <TabsContent value="time-of-day">
                    <ParticipationPatternChart :data="analytics.participation_patterns.by_time_of_day" type="time" />
                </TabsContent>
                <TabsContent value="duration">
                    <ParticipationPatternChart :data="analytics.participation_patterns.by_duration" type="duration" />
                </TabsContent>
            </Tabs>
        </CardContent>
    </Card>

    <!-- Event History -->
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Event History</CardTitle>
                    <CardDescription>Detailed view of all events with financial impact</CardDescription>
                </div>
                <Button @click="loadEventHistory" variant="outline" size="sm">
                    <History class="mr-2 h-4 w-4" />
                    View Full History
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <EventHistoryTable :events="eventHistory.events" :pagination="eventHistory.pagination" @page-change="handlePageChange" @export-participants="exportParticipants" />
        </CardContent>
    </Card>
</template>
