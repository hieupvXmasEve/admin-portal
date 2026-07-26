<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import type { RoomBookingAction } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    logs: PaginatedResponse<RoomBookingAction>;
    filters?: {
        booking_id?: string;
        action_type?: string;
        start_date?: string;
        end_date?: string;
    };
    action_types?: Array<{ value: string; label: string }>;
}>();

const data = computed(() => props.logs.data);

const filters = ref({
    booking_id: props.filters?.booking_id || '',
    action_type: props.filters?.action_type || '',
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    per_page: 25,
});

const applyFilters = () => {
    const params = new URLSearchParams();
    if (filters.value.booking_id) params.set('booking_id', filters.value.booking_id);
    if (filters.value.action_type) params.set('action_type', filters.value.action_type);
    if (filters.value.start_date) params.set('start_date', filters.value.start_date);
    if (filters.value.end_date) params.set('end_date', filters.value.end_date);
    if (filters.value.per_page) params.set('per_page', filters.value.per_page.toString());

    router.visit(`/room-bookings-logs${params.toString() ? '?' + params.toString() : ''}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['logs', 'filters'],
    });
};

const updateFilter = (key: keyof typeof filters.value, value: string) => {
    const stringValue = String(value);
    (filters.value as Record<string, string | number>)[key] = stringValue === 'all' ? '' : stringValue;
    applyFilters();
};

const clearFilters = () => {
    filters.value = { booking_id: '', action_type: '', start_date: '', end_date: '', per_page: 25 };
    router.visit('/room-bookings-logs', { preserveState: true, preserveScroll: true });
};

const hasActiveFilters = computed(() => filters.value.booking_id || filters.value.action_type || filters.value.start_date || filters.value.end_date);

const getActionBadgeVariant = (actionType: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (actionType) {
        case 'created':
            return 'outline';
        case 'approved':
            return 'default';
        case 'rejected':
            return 'destructive';
        case 'cancelled':
            return 'outline';
        case 'updated':
            return 'secondary';
        case 'completed':
            return 'default';
        default:
            return 'outline';
    }
};

const formatDateTime = (datetime: string) =>
    new Date(datetime).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

const viewBooking = (id: number) => router.visit(`/room-bookings/${id}`);

const columns: ColumnDef<RoomBookingAction>[] = [
    {
        header: 'No',
        id: 'no',
        cell: ({ row }) => (props.logs.current_page - 1) * props.logs.per_page + row.index + 1,
    },
    {
        header: 'Booking',
        id: 'booking',
        cell: ({ row }) => ({ template: 'booking', data: row.original }),
    },
    {
        header: 'Action',
        id: 'action',
        accessorKey: 'action_type',
        cell: ({ row }) => ({ template: 'action', data: row.original }),
    },
    {
        header: 'Performed By',
        id: 'actor',
        cell: ({ row }) => row.original.actor_name || 'System',
    },
    {
        header: 'Note',
        accessorKey: 'note',
        cell: ({ row }) => row.original.note || '-',
    },
    {
        header: 'Date & Time',
        accessorKey: 'created_at',
        cell: ({ row }) => formatDateTime(row.original.created_at),
    },
];

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true, only: ['logs'] });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters();
};
</script>

<template>
    <Head title="Booking Activity Logs" />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Booking Activity Logs</h1>
            <p class="text-muted-foreground mt-1">View all booking actions and changes</p>
        </div>
        <Button variant="outline" @click="router.visit('/room-bookings')">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to Bookings
        </Button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <Input v-model="filters.booking_id" placeholder="Booking ID" type="number" class="w-32" @change="applyFilters" />

        <Select :model-value="filters.action_type || 'all'" @update:model-value="(v) => updateFilter('action_type', v)">
            <SelectTrigger class="w-40">
                <SelectValue placeholder="Action Type" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All Actions</SelectItem>
                <SelectItem v-for="option in action_types" :key="option.value" :value="option.value">
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Input v-model="filters.start_date" type="date" class="w-40" @change="applyFilters" />
        <span class="text-muted-foreground">to</span>
        <Input v-model="filters.end_date" type="date" class="w-40" @change="applyFilters" />

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear
        </Button>
    </div>

    <DataTable :data="data" :columns="columns" :show-column-toggle="false">
        <template #cell-booking="{ row }">
            <div class="cursor-pointer" @click="viewBooking(row.original.room_booking_id)">
                <div class="text-primary font-medium hover:underline">#{{ row.original.room_booking_id }}</div>
                <div class="text-muted-foreground text-sm">
                    {{ row.original.room_booking?.title || '' }}
                </div>
            </div>
        </template>

        <template #cell-action="{ row }">
            <Badge :variant="getActionBadgeVariant(row.original.action_type)" class="capitalize">
                {{ row.original.action_label || row.original.action_type }}
            </Badge>
        </template>
    </DataTable>

    <DataPagination :pagination-data="logs" item-name="logs" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
