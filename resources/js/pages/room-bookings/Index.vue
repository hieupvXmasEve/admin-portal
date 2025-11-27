<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useGlobalConfirmDialog, usePermissions } from '@/composables';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import type { RoomBooking } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Building2, Calendar, CalendarClock, Clock, Plus, X } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { toast } from 'vue-sonner';

interface RoomBookingFilters {
    search: string;
    room_id: string;
    building_id: string;
    status: string;
    booking_type: string;
    start_date: string;
    end_date: string;
    per_page: number;
}

const props = defineProps<{
    bookings: PaginatedResponse<RoomBooking>;
    filters?: Partial<RoomBookingFilters>;
    statistics?: {
        total: number;
        pending: number;
        approved: number;
        rejected: number;
        cancelled: number;
    };
    booking_types?: Array<{ value: string; label: string }>;
    booking_statuses?: Array<{ value: string; label: string }>;
    buildings?: Array<{ id: number; value: string; label: string }>;
    rooms?: Array<{ id: number; value: string; label: string; building_id: number }>;
}>();

const confirmDialog = useGlobalConfirmDialog();
const { can } = usePermissions();
const data = computed(() => props.bookings.data);

// Permissions computed
const permissions = computed(() => ({
    can_create: can('create_room_booking'),
    can_edit: can('edit_room_booking'),
    can_delete: can('delete_room_booking'),
    can_approve: can('approve_room_booking'),
}));

// Use useInertiaFilters composable
const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handlePaginationNavigate, handlePageSizeChange } = useInertiaFilters<RoomBookingFilters>({
    baseUrl: systemRoutes.roomBookings.index(),
    initialFilters: {
        search: props.filters?.search || '',
        room_id: props.filters?.room_id || '',
        building_id: props.filters?.building_id || '',
        status: props.filters?.status || 'all',
        booking_type: props.filters?.booking_type || 'all',
        start_date: props.filters?.start_date || '',
        end_date: props.filters?.end_date || '',
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        per_page: 15,
        status: 'all',
        booking_type: 'all',
    },
    only: ['bookings', 'filters', 'statistics'],
    debounce: 400,
});

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'approved':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'rejected':
            return 'destructive';
        case 'cancelled':
            return 'outline';
        case 'completed':
            return 'default';
        default:
            return 'outline';
    }
};

const getBookingTypeLabel = (type: string) => {
    const option = props.booking_types?.find((opt) => opt.value === type);
    return option?.label || type;
};

const getStatusLabel = (status: string) => {
    const option = props.booking_statuses?.find((opt) => opt.value === status);
    return option?.label || status;
};

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

const formatTime = (time: string) => {
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

const viewBooking = (id: number) => router.visit(`/room-bookings/${id}`);
const editBooking = (id: number) => router.visit(`/room-bookings/${id}/edit`);

const deleteBooking = (booking: RoomBooking) => {
    confirmDialog.confirmDelete(booking.title, 'booking', () => {
        router.delete(`/room-bookings/${booking.id}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => toast.success('Booking deleted successfully'),
            onError: () => toast.error('Failed to delete booking'),
        });
    });
};

const columns: ColumnDef<RoomBooking>[] = [
    {
        header: 'No',
        id: 'no',
        cell: ({ row }) => (props.bookings.current_page - 1) * props.bookings.per_page + row.index + 1,
    },
    {
        header: 'Booking',
        id: 'booking',
        accessorKey: 'title',
        cell: ({ row }) => ({ template: 'booking', data: row.original }),
    },
    {
        header: 'Room',
        id: 'room',
        accessorKey: 'room.name',
        cell: ({ row }) => ({ template: 'room', data: row.original }),
    },
    {
        header: 'Date & Time',
        id: 'datetime',
        accessorKey: 'booking_date',
        cell: ({ row }) => ({ template: 'datetime', data: row.original }),
    },
    {
        header: 'Booked By',
        id: 'bookedBy',
        cell: ({ row }) => {
            const booking = row.original;
            // Try to get name from bookedBy relationship or use booker_name accessor
            if (booking.booked_by) {
                if (booking.booked_by_type === 'user') {
                    const user = booking.booked_by as any;
                    return user.name || 'Unknown User';
                } else if (booking.booked_by_type === 'student') {
                    const student = booking.booked_by as any;
                    return student.full_name || student.name || 'Unknown Student';
                } else if (booking.booked_by_type === 'lecture') {
                    const lecture = booking.booked_by as any;
                    return lecture.name || lecture.full_name || 'Unknown Lecturer';
                }
            }
            // Fallback to booker_name accessor if available
            return (booking as any).booker_name || 'Unknown';
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        cell: ({ row }) => ({ template: 'status', data: row.original }),
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const booking = row.original;
            return h(
                'div',
                { class: 'flex items-center space-x-2' },
                [
                    h(Button, { variant: 'ghost', size: 'sm', onClick: () => viewBooking(booking.id) }, () => [h(Icon, { name: 'eye', class: 'w-4 h-4' })]),
                    permissions.value.can_edit && h(Button, { variant: 'ghost', size: 'sm', onClick: () => editBooking(booking.id) }, () => [h(Icon, { name: 'edit', class: 'w-4 h-4' })]),
                    permissions.value.can_delete && h(Button, { variant: 'ghost', size: 'sm', onClick: () => deleteBooking(booking) }, () => [h(Icon, { name: 'trash', class: 'w-4 h-4' })]),
                ].filter(Boolean),
            );
        },
    },
];

// handlePaginationNavigate and handlePageSizeChange are now provided by useInertiaFilters
</script>

<template>
    <Head title="Room Bookings" />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Room Bookings</h1>
            <div v-if="statistics" class="text-muted-foreground mt-2 flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1">
                    <CalendarClock class="h-4 w-4" />
                    <span>{{ statistics.total }} Total</span>
                </div>
                <Badge variant="secondary" class="px-2 py-0.5 text-xs">{{ statistics.pending }} Pending</Badge>
                <Badge variant="default" class="px-2 py-0.5 text-xs">{{ statistics.approved }} Approved</Badge>
                <Badge variant="destructive" class="px-2 py-0.5 text-xs">{{ statistics.rejected }} Rejected</Badge>
            </div>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" @click="router.visit('/room-bookings-pending')">
                <Clock class="mr-2 h-4 w-4" />
                Pending Approvals
            </Button>
            <Button variant="outline" @click="router.visit('/room-bookings-calendar')">
                <Calendar class="mr-2 h-4 w-4" />
                Calendar
            </Button>
            <Button v-if="permissions.can_create" @click="router.visit('/room-bookings/create')">
                <Plus class="mr-2 h-4 w-4" />
                New Booking
            </Button>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <DebouncedInput placeholder="Search bookings..." :model-value="filters.search" @update:model-value="handleSearch" />
        </div>

        <Select :model-value="filters.status || 'all'" @update:model-value="(v) => handleSelectFilter('status', v, 'all')">
            <SelectTrigger class="w-40">
                <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem v-for="option in booking_statuses" :key="option.value" :value="option.value">
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select :model-value="filters.booking_type || 'all'" @update:model-value="(v) => handleSelectFilter('booking_type', v, 'all')">
            <SelectTrigger class="w-40">
                <SelectValue placeholder="Type" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All Types</SelectItem>
                <SelectItem v-for="option in booking_types" :key="option.value" :value="option.value">
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear
        </Button>
    </div>

    <DataTable :data="data" :columns="columns" :show-column-toggle="false">
        <template #cell-booking="{ row }">
            <div>
                <div class="font-semibold">{{ row.original.title }}</div>
                <Badge variant="outline" class="mt-1 text-xs">{{ getBookingTypeLabel(row.original.booking_type) }}</Badge>
            </div>
        </template>

        <template #cell-room="{ row }">
            <div class="flex flex-col">
                <div class="flex items-center gap-1 font-medium">
                    <Building2 class="h-3 w-3" />
                    {{ row.original.room?.name }}
                </div>
                <div class="text-muted-foreground text-sm">{{ row.original.room?.building?.name }}</div>
            </div>
        </template>

        <template #cell-datetime="{ row }">
            <div class="flex flex-col">
                <div class="flex items-center gap-1">
                    <Calendar class="text-muted-foreground h-3 w-3" />
                    {{ formatDate(row.original.booking_date) }}
                </div>
                <div class="text-muted-foreground text-sm">{{ formatTime(row.original.start_time) }} - {{ formatTime(row.original.end_time) }}</div>
            </div>
        </template>

        <template #cell-status="{ row }">
            <Badge :variant="getStatusBadgeVariant(row.original.status)">
                {{ getStatusLabel(row.original.status) }}
            </Badge>
        </template>
    </DataTable>

    <DataPagination :pagination-data="bookings" item-name="bookings" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
