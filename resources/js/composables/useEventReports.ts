import { reactive, ref } from 'vue';
import { useApi } from './useApiRequest';

export interface EventAnalytics {
    summary: {
        total_events: number;
        total_participants: number;
        total_gold_awarded: number;
        average_participation_per_event: number;
        published_events: number;
        completed_events: number;
        cancelled_events: number;
    };
    participation_trends: Array<{
        week_start: string;
        week_end: string;
        events_count: number;
        total_registrations: number;
        total_checkins: number;
        total_completions: number;
    }>;
    gold_distribution: {
        total_gold_distributed: number;
        average_gold_per_event: number;
        highest_reward_event: any;
        gold_by_event: Array<{
            event_id: number;
            event_title: string;
            gold_per_participant: number;
            total_awarded: number;
            participants_awarded: number;
        }>;
    };
    event_success_metrics: {
        average_checkin_rate: number;
        average_completion_rate: number;
        average_overall_success_rate: number;
        event_metrics: Array<{
            event_id: number;
            event_title: string;
            start_time: string;
            registrations: number;
            checkins: number;
            completions: number;
            checkin_rate: number;
            completion_rate: number;
            overall_success_rate: number;
        }>;
    };
    status_breakdown: {
        draft: number;
        published: number;
        completed: number;
        cancelled: number;
    };
    monthly_trends: Array<{
        month: string;
        month_name: string;
        events_count: number;
        total_participants: number;
        total_gold_awarded: number;
        completion_rate: number;
    }>;
    top_performing_events: Array<{
        event_id: number;
        title: string;
        start_time: string;
        status: string;
        registrations: number;
        completions: number;
        success_rate: number;
        gold_awarded: number;
    }>;
    participation_patterns: {
        by_day_of_week: Array<{
            day: string;
            events_count: number;
            avg_participation: number;
            avg_success_rate: number;
        }>;
        by_time_of_day: Array<{
            time_slot: string;
            events_count: number;
            avg_participation: number;
            avg_success_rate: number;
        }>;
        by_duration: Array<{
            duration_range: string;
            events_count: number;
            avg_participation: number;
            avg_success_rate: number;
        }>;
    };
}

export interface EventReportFilters {
    date_from: string;
    date_to: string;
    status: string;
}

export interface EventHistory {
    events: Array<{
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
    }>;
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number;
        to: number;
    };
}

export interface RealTimeEventStats {
    event_id: number;
    title: string;
    status: string;
    start_time: string;
    end_time: string;
    max_participants: number | null;
    statistics: {
        registered: number;
        checked_in: number;
        completed: number;
        cancelled: number;
        available_spots: number | null;
        participation_rate: number;
        total_gold_awarded: number;
        capacity_reached: boolean;
    };
    status_breakdown: {
        registered: number;
        checked_in: number;
        completed: number;
        cancelled: number;
    };
    recent_activity: Array<{
        student_name: string;
        action: string;
        timestamp: string;
        status: string;
    }>;
}

export function useEventReports() {
    const api = useApi();

    const loading = ref(false);
    const analytics = ref<EventAnalytics | null>(null);
    const eventHistory = ref<EventHistory | null>(null);
    const realTimeStats = ref<RealTimeEventStats | null>(null);

    const filters = reactive<EventReportFilters>({
        date_from: new Date(Date.now() - 6 * 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        date_to: new Date().toISOString().split('T')[0],
        status: 'all',
    });

    /**
     * Load event analytics data
     */
    const loadAnalytics = async (customFilters?: Partial<EventReportFilters>) => {
        loading.value = true;
        try {
            const params = { ...filters, ...customFilters };
            const response = await api.get('/events/reports/analytics', {
                params,
            });

            if (response.data?.value?.success) {
                analytics.value = response.data?.value.data;
            }

            return response;
        } catch (error) {
            console.error('Failed to load analytics:', error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Load event history with pagination
     */
    const loadEventHistory = async (page = 1, customFilters?: Partial<EventReportFilters>) => {
        loading.value = true;
        try {
            const params = { ...filters, ...customFilters, page, per_page: 15 };
            const response = await api.get('/events/reports/history', {
                method: 'GET',
                params,
            });

            if (response.data?.value?.success) {
                eventHistory.value = response.data?.value.data;
            }

            return response;
        } catch (error) {
            console.error('Failed to load event history:', error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Load real-time statistics for a specific event
     */
    const loadRealTimeStats = async (eventId: number) => {
        loading.value = true;
        try {
            const response = await api.get(`/events/${eventId}/stats`, {});

            if (response.data?.value?.success) {
                realTimeStats.value = response.data?.value.data;
            }

            return response;
        } catch (error) {
            console.error('Failed to load real-time stats:', error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Export events data to CSV
     */
    const exportEvents = (customFilters?: Partial<EventReportFilters>) => {
        const params = new URLSearchParams({ ...filters, ...customFilters } as any);
        const url = `/events/reports/export?${params.toString()}`;
        window.open(url, '_blank');
    };

    /**
     * Export participants data for a specific event
     */
    const exportParticipants = (eventId: number) => {
        const url = `/events/${eventId}/export-participants`;
        window.open(url, '_blank');
    };

    /**
     * Update filters and reload data
     */
    const updateFilters = async (newFilters: Partial<EventReportFilters>) => {
        Object.assign(filters, newFilters);
        await loadAnalytics();
    };

    /**
     * Reset filters to default values
     */
    const resetFilters = async () => {
        filters.date_from = new Date(Date.now() - 6 * 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        filters.date_to = new Date().toISOString().split('T')[0];
        filters.status = 'all';
        await loadAnalytics();
    };

    /**
     * Refresh all data
     */
    const refreshData = async () => {
        await Promise.all([loadAnalytics(), loadEventHistory()]);
    };

    /**
     * Format currency values
     */
    const formatCurrency = (value: number): string => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    /**
     * Format percentage values
     */
    const formatPercentage = (value: number): string => {
        return `${Math.round(value)}%`;
    };

    /**
     * Format date values
     */
    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    /**
     * Format date and time values
     */
    const formatDateTime = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    /**
     * Get color class for success rates
     */
    const getRateColor = (rate: number): string => {
        if (rate >= 80) return 'text-green-600';
        if (rate >= 60) return 'text-yellow-600';
        if (rate >= 40) return 'text-orange-600';
        return 'text-red-600';
    };

    /**
     * Get color class for event status
     */
    const getStatusColor = (status: string): string => {
        const colors = {
            draft: 'bg-gray-100 text-gray-800',
            published: 'bg-blue-100 text-blue-800',
            completed: 'bg-green-100 text-green-800',
            cancelled: 'bg-red-100 text-red-800',
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    return {
        // State
        loading,
        analytics,
        eventHistory,
        realTimeStats,
        filters,

        // Methods
        loadAnalytics,
        loadEventHistory,
        loadRealTimeStats,
        exportEvents,
        exportParticipants,
        updateFilters,
        resetFilters,
        refreshData,

        // Utilities
        formatCurrency,
        formatPercentage,
        formatDate,
        formatDateTime,
        getRateColor,
        getStatusColor,
    };
}
