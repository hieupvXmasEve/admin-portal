<script setup lang="ts">
import StatsCard from '@/components/StatsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInitials } from '@/composables/useInitials';
import type { Event, EventParticipant } from '@/types/event';
import { formatDateTimeToShort } from '@/utils/date';
import type { ColumnDef } from '@tanstack/vue-table';
import { Clock, Coins, Eye, MoreHorizontal, RefreshCw, Search, X } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import DataTable from './DataTable.vue';

interface ParticipantFilters {
    search: string;
    status: string;
    gold_awarded: string;
}

interface ParticipantStatistics {
    total_registered: number;
    checked_in: number;
    completed: number;
    cancelled: number;
    gold_awarded_count: number;
    total_gold_awarded: number;
    participation_rate: number;
    available_spots: number | null;
    capacity_reached: boolean;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface ParticipantsResponse {
    success: boolean;
    data: EventParticipant[];
    meta: PaginationMeta;
    message?: string;
}

interface EventStatisticsResponse {
    success: boolean;
    data: ParticipantStatistics;
    message?: string;
}

interface CheckinApiResponse {
    success: boolean;
    message: string;
    data?: {
        participant: EventParticipant;
    };
}

const props = defineProps<{
    event: Event;
}>();

const emit = defineEmits<{
    participantUpdated: [participant: EventParticipant];
}>();

// State
const participants = ref<EventParticipant[]>([]);
const statistics = ref<ParticipantStatistics>({
    total_registered: 0,
    checked_in: 0,
    completed: 0,
    cancelled: 0,
    gold_awarded_count: 0,
    total_gold_awarded: 0,
    participation_rate: 0,
    available_spots: null,
    capacity_reached: false,
});

const isLoading = ref(false);
const isLoadingStats = ref(false);
const processingId = ref<number | null>(null);

// Filters and pagination
const filters = ref<ParticipantFilters>({
    search: '',
    status: '',
    gold_awarded: '',
});

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
});

// Modal state
const showDetailsModal = ref(false);
const selectedParticipant = ref<EventParticipant | null>(null);

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const getQueryString = (params: Record<string, any>) => {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query.append(key, String(value));
        }
    });
    return query.toString();
};

const fetchParticipants = async (eventId: number, params: Record<string, any>) => {
    const queryString = getQueryString(params);
    const response = await fetch(`/api/events/${eventId}/participants?${queryString}`, {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    });

    const data = (await response.json()) as ParticipantsResponse;

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Failed to load participants');
    }

    return data;
};

const fetchStatistics = async (eventId: number) => {
    const response = await fetch(`/api/events/${eventId}/statistics`, {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    });

    const data = (await response.json()) as EventStatisticsResponse;

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Failed to load statistics');
    }

    return data.data;
};

const postJson = async <T,>(url: string, body: unknown): Promise<T> => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data = (await response.json()) as T & { message?: string };

    if (!response.ok) {
        throw new Error((data as { message?: string })?.message || 'Request failed');
    }

    return data;
};

// Composables
const { getInitials } = useInitials();

// Table columns
const columns: ColumnDef<EventParticipant>[] = [
    {
        id: 'student',
        header: 'Student',
        cell: ({ row }) => row.original,
        enableSorting: false,
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'registered_at',
        header: 'Registered',
    },
    {
        accessorKey: 'checkin_time',
        header: 'Check-in Time',
    },
    {
        id: 'gold_status',
        header: 'Gold Status',
        cell: ({ row }) => row.original,
        enableSorting: false,
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => row.original,
        enableSorting: false,
    },
];

// Methods
const loadParticipants = async () => {
    try {
        isLoading.value = true;

        const params = {
            page: pagination.value.current_page,
            per_page: pagination.value.per_page,
            ...filters.value,
        };

        const response = await fetchParticipants(props.event.id, params);

        participants.value = response.data || [];
        pagination.value = response.meta || pagination.value;
    } catch (error) {
        console.error('Failed to load participants:', error);
    } finally {
        isLoading.value = false;
    }
};

const loadStatistics = async () => {
    try {
        isLoadingStats.value = true;

        const response = await fetchStatistics(props.event.id);
        statistics.value = response || statistics.value;
    } catch (error) {
        console.error('Failed to load statistics:', error);
    } finally {
        isLoadingStats.value = false;
    }
};

const refreshData = async () => {
    await Promise.all([loadParticipants(), loadStatistics()]);
};

// Filter handlers
let searchTimeout: NodeJS.Timeout;

const onSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
};

const applyFilters = () => {
    pagination.value.current_page = 1;
    loadParticipants();
};

const onPageChange = (page: number) => {
    pagination.value.current_page = page;
    loadParticipants();
};

const onSortChange = (column: string, direction: 'asc' | 'desc') => {
    // Implement sorting logic
    loadParticipants();
};

// Participant actions
const canCheckIn = (participant: EventParticipant): boolean => {
    return participant.status === 'registered' && props.event.can_check_in;
};

const canAwardGold = (participant: EventParticipant): boolean => {
    return participant.status === 'completed' && !participant.gold_awarded && props.event.gold_reward_amount > 0;
};

const canCancel = (participant: EventParticipant): boolean => {
    return ['registered', 'checked_in'].includes(participant.status) && !props.event.has_ended;
};

const checkinParticipant = async (participant: EventParticipant) => {
    try {
        processingId.value = participant.id;

        const response = await postJson<CheckinApiResponse>('/api/events/checkin', {
            event_id: props.event.id,
            student_id: participant.student.id,
        });

        if (response.success && response.data?.participant) {
            const updatedParticipant = response.data.participant;

            const index = participants.value.findIndex((p) => p.id === participant.id);
            if (index !== -1) {
                participants.value[index] = { ...participants.value[index], ...updatedParticipant };
            }

            emit('participantUpdated', updatedParticipant);
            await loadStatistics();
        } else {
            throw new Error(response.message || 'Check-in failed');
        }
    } catch (error) {
        console.error('Check-in failed:', error);
    } finally {
        processingId.value = null;
    }
};

const awardGold = async (participant: EventParticipant) => {
    // This would call an API to award gold
    console.log('Award gold to:', participant);
};

const cancelParticipation = async (participant: EventParticipant) => {
    // This would call an API to cancel participation
    console.log('Cancel participation:', participant);
};

const viewDetails = (participant: EventParticipant) => {
    selectedParticipant.value = participant;
    showDetailsModal.value = true;
};

// Utility functions
const getStatusVariant = (status: string) => {
    switch (status) {
        case 'registered':
            return 'default';
        case 'checked_in':
            return 'success';
        case 'completed':
            return 'success';
        case 'cancelled':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const formatStatus = (status: string) => {
    switch (status) {
        case 'registered':
            return 'Registered';
        case 'checked_in':
            return 'Checked In';
        case 'completed':
            return 'Completed';
        case 'cancelled':
            return 'Cancelled';
        default:
            return status;
    }
};

const getTimeAgo = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));

    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h ago`;

    const diffInDays = Math.floor(diffInHours / 24);
    return `${diffInDays}d ago`;
};

// Lifecycle
onMounted(() => {
    refreshData();
});

// Watch for event changes
watch(
    () => props.event.id,
    () => {
        refreshData();
    },
);
</script>
<template>
    <div class="event-participant-list">
        <!-- Header with Statistics -->
        <div class="mb-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Event Participants</h3>
                    <p class="text-muted-foreground text-sm">Manage check-in status and view participant details</p>
                </div>
                <Button @click="refreshData" :loading="isLoading" variant="outline">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Refresh
                </Button>
            </div>

            <!-- Statistics Cards -->
            <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatsCard title="Total Registered" :value="statistics.total_registered" :loading="isLoadingStats" />
                <StatsCard title="Checked In" :value="statistics.checked_in" :loading="isLoadingStats" />
                <StatsCard title="Completed" :value="statistics.completed" :loading="isLoadingStats" />
                <StatsCard title="Participation Rate" :value="statistics.participation_rate ? `${statistics.participation_rate.toFixed(1)}%` : '0%'" :loading="isLoadingStats" />
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 flex flex-wrap gap-4">
            <div class="min-w-64 flex-1">
                <Input v-model="filters.search" placeholder="Search by name, student ID, or email..." @input="onSearchInput" class="w-full">
                    <template #prefix>
                        <Search class="h-4 w-4" />
                    </template>
                </Input>
            </div>

            <Select v-model="filters.status" @update:model-value="applyFilters">
                <SelectTrigger class="w-48">
                    <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    <SelectItem value="registered">Registered</SelectItem>
                    <SelectItem value="checked_in">Checked In</SelectItem>
                    <SelectItem value="completed">Completed</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="filters.gold_awarded" @update:model-value="applyFilters">
                <SelectTrigger class="w-48">
                    <SelectValue placeholder="Filter by gold status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Participants</SelectItem>
                    <SelectItem value="true">Gold Awarded</SelectItem>
                    <SelectItem value="false">Gold Pending</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Participants Table -->
        <DataTable :data="participants" :columns="columns" :loading="isLoading" :pagination="pagination" @page-change="onPageChange" @sort-change="onSortChange">
            <!-- Student Info Column -->
            <template #cell-student="{ row }">
                <div class="flex items-center gap-3">
                    <div class="bg-muted flex h-10 w-10 items-center justify-center rounded-full">
                        <span class="text-sm font-medium">
                            {{ getInitials(row.original.student?.full_name || '') }}
                        </span>
                    </div>
                    <div>
                        <p class="font-medium">{{ row.original.student?.full_name || '' }}</p>
                        <p class="text-muted-foreground text-sm">{{ row.original.student?.student_id || '' }}</p>
                        <p class="text-muted-foreground text-xs">{{ row.original.student?.email || '' }}</p>
                    </div>
                </div>
            </template>

            <!-- Status Column -->
            <template #cell-status="{ row }">
                <Badge :variant="getStatusVariant(row.original.status)">
                    {{ formatStatus(row.original.status) }}
                </Badge>
            </template>

            <!-- Registration Time Column -->
            <template #cell-registered_at="{ row }">
                <div class="text-sm">
                    <p>{{ formatDateTimeToShort(row.original.registered_at) }}</p>
                    <p class="text-muted-foreground">
                        {{ getTimeAgo(row.original.registered_at) }}
                    </p>
                </div>
            </template>

            <!-- Check-in Time Column -->
            <template #cell-checkin_time="{ row }">
                <div v-if="row.original.checkin_time" class="text-sm">
                    <p>{{ formatDateTimeToShort(row.original.checkin_time) }}</p>
                    <p class="text-muted-foreground">
                        {{ getTimeAgo(row.original.checkin_time) }}
                    </p>
                    <p v-if="row.original.checkin_staff" class="text-muted-foreground text-xs">by {{ row.original.checkin_staff?.name }}</p>
                </div>
                <span v-else class="text-muted-foreground">-</span>
            </template>

            <!-- Gold Status Column -->
            <template #cell-gold_status="{ row }">
                <div class="flex items-center gap-2">
                    <Badge v-if="row.original.gold_awarded" variant="success">
                        <Coins class="mr-1 h-3 w-3" />
                        Awarded
                    </Badge>
                    <Badge v-else-if="row.original.status === 'completed'" variant="outline">
                        <Clock class="mr-1 h-3 w-3" />
                        Pending
                    </Badge>
                    <span v-else class="text-muted-foreground">-</span>
                </div>
            </template>

            <!-- Actions Column -->
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <Button v-if="canCheckIn(row.original)" @click="checkinParticipant(row.original)" size="sm" :loading="processingId === row.original.id"> Check In </Button>

                    <Button v-if="canAwardGold(row.original)" @click="awardGold(row.original)" size="sm" variant="outline" :loading="processingId === row.original.id">
                        <Coins class="mr-1 h-4 w-4" />
                        Award Gold
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="viewDetails(row.original)">
                                <Eye class="mr-2 h-4 w-4" />
                                View Details
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="canCancel(row.original)" @click="cancelParticipation(row.original)" class="text-destructive">
                                <X class="mr-2 h-4 w-4" />
                                Cancel Registration
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
        </DataTable>

        <!-- Participant Details Modal -->
        <Dialog v-model:open="showDetailsModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Participant Details</DialogTitle>
                </DialogHeader>

                <div v-if="selectedParticipant" class="space-y-6">
                    <!-- Student Information -->
                    <div>
                        <h4 class="mb-3 font-medium">Student Information</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Full Name</Label>
                                <p class="text-sm">{{ selectedParticipant.student.full_name }}</p>
                            </div>
                            <div>
                                <Label>Student ID</Label>
                                <p class="text-sm">{{ selectedParticipant.student.student_id }}</p>
                            </div>
                            <div>
                                <Label>Email</Label>
                                <p class="text-sm">{{ selectedParticipant.student.email }}</p>
                            </div>
                            <div>
                                <Label>Status</Label>
                                <Badge :variant="getStatusVariant(selectedParticipant.status)">
                                    {{ formatStatus(selectedParticipant.status) }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Participation Timeline -->
                    <div>
                        <h4 class="mb-3 font-medium">Participation Timeline</h4>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="bg-primary h-2 w-2 rounded-full"></div>
                                <div>
                                    <p class="text-sm font-medium">Registered</p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ formatDateTimeToShort(selectedParticipant.registered_at) }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="selectedParticipant.checkin_time" class="flex items-center gap-3">
                                <div class="bg-success h-2 w-2 rounded-full"></div>
                                <div>
                                    <p class="text-sm font-medium">Checked In</p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ formatDateTimeToShort(selectedParticipant.checkin_time) }}
                                        <span v-if="selectedParticipant.checkin_staff"> by {{ selectedParticipant.checkin_staff.name }} </span>
                                    </p>
                                </div>
                            </div>

                            <div v-if="selectedParticipant.gold_awarded" class="flex items-center gap-3">
                                <div class="h-2 w-2 rounded-full bg-yellow-500"></div>
                                <div>
                                    <p class="text-sm font-medium">Gold Awarded</p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ formatDateTimeToShort(selectedParticipant.awarded_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Device Information -->
                    <div v-if="selectedParticipant.checkin_device_info">
                        <h4 class="mb-3 font-medium">Check-in Device Information</h4>
                        <div class="bg-muted rounded-lg p-3">
                            <pre class="text-xs">{{ JSON.stringify(selectedParticipant.checkin_device_info, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<!--
<style scoped>
.event-participant-list {
  @apply space-y-6;
}
</style> -->
