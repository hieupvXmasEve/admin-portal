<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useGlobalConfirmDialog } from '@/composables';
import type { PaginatedResponse } from '@/types';
import type { RoomBooking } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Building2, Calendar, Copy, Plus, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    bookings: PaginatedResponse<RoomBooking>;
    filters?: {
        search?: string;
        status?: string;
        start_date?: string;
        end_date?: string;
    };
    booking_statuses?: Array<{ value: string; label: string }>;
    permissions?: {
        can_create: boolean;
        can_edit: boolean;
    };
}>();

const confirmDialog = useGlobalConfirmDialog();
const data = computed(() => props.bookings.data);

const filters = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
    per_page: 15,
});

const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();
    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.status) params.set('status', newFilters.status);
    if (newFilters.start_date) params.set('start_date', newFilters.start_date);
    if (newFilters.end_date) params.set('end_date', newFilters.end_date);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    router.visit(`${systemRoutes.roomBookings.myBookings()}${params.toString() ? '?' + params.toString() : ''}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['bookings', 'filters'],
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const updateStatusFilter = (value: unknown) => {
    const stringValue = String(value);
    filters.value.status = stringValue === 'all' ? '' : stringValue;
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = { search: '', status: '', start_date: '', end_date: '', per_page: 15 };
    router.visit(systemRoutes.roomBookings.myBookings(), { preserveState: true, preserveScroll: true });
};

const hasActiveFilters = computed(() => filters.value.search || filters.value.status || filters.value.start_date || filters.value.end_date);

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

const viewBooking = (id: number) => router.visit(systemRoutes.roomBookings.show(id));
const editBooking = (id: number) => router.visit(systemRoutes.roomBookings.edit(id));
const cloneBooking = (id: number) => router.visit(systemRoutes.roomBookings.create({ source_booking_id: id }));

const cancelBooking = (booking: RoomBooking) => {
    confirmDialog.showConfirmDialog(
        {
            title: 'Cancel Booking',
            message: `Are you sure you want to cancel "${booking.title}"?`,
            confirmText: 'Cancel Booking',
        },
        {
            onConfirm: () => {
                router.post(
                    systemRoutes.roomBookings.cancel(booking.id),
                    {},
                    {
                        preserveState: true,
                        preserveScroll: true,
                        onSuccess: () => toast.success('Booking cancelled successfully'),
                        onError: () => toast.error('Failed to cancel booking'),
                    },
                );
            },
        },
    );
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
        header: 'Status',
        accessorKey: 'status',
        cell: ({ row }) => ({ template: 'status', data: row.original }),
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const booking = row.original;
            const canCancel = ['pending', 'approved'].includes(booking.status);
            const canEdit = booking.status === 'pending' || booking.status === 'approved';
            return h(
                'div',
                { class: 'flex items-center space-x-2' },
                [
                    h(Button, { variant: 'ghost', size: 'sm', onClick: () => viewBooking(booking.id) }, () => [h(Icon, { name: 'eye', class: 'w-4 h-4' })]),
                    props.permissions?.can_create && h(Button, { variant: 'ghost', size: 'sm', onClick: () => cloneBooking(booking.id) }, () => [h(Copy, { class: 'w-4 h-4' })]),
                    canEdit && h(Button, { variant: 'ghost', size: 'sm', onClick: () => editBooking(booking.id) }, () => [h(Icon, { name: 'edit', class: 'w-4 h-4' })]),
                    canCancel && h(Button, { variant: 'ghost', size: 'sm', onClick: () => cancelBooking(booking) }, () => [h(Icon, { name: 'x', class: 'w-4 h-4' })]),
                ].filter(Boolean),
            );
        },
    },
];

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true, only: ['bookings'] });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
</script>

<template>
    <Head title="My Bookings" />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">My Bookings</h1>
            <p class="text-muted-foreground mt-1">View and manage your room bookings</p>
        </div>
        <Button v-if="permissions?.can_create" @click="router.visit(systemRoutes.roomBookings.create())">
            <Plus class="mr-2 h-4 w-4" />
            New Booking
        </Button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <DebouncedInput placeholder="Search bookings..." v-model="filters.search" @debounced="handleSearch" />
        </div>

        <Select :model-value="filters.status || 'all'" @update:model-value="updateStatusFilter">
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

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear
        </Button>
    </div>

    <DataTable :data="data" :columns="columns" :show-column-toggle="false">
        <template #cell-booking="{ row }">
            <div>
                <div class="font-semibold">{{ row.original.title }}</div>
                <Badge variant="outline" class="mt-1 text-xs">{{ row.original.booking_type }}</Badge>
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
            <div class="flex flex-col gap-1">
                <Badge :variant="getStatusBadgeVariant(row.original.status)">
                    {{ getStatusLabel(row.original.status) }}
                </Badge>
                <span v-if="row.original.rejection_reason" class="text-destructive text-xs">
                    {{ row.original.rejection_reason }}
                </span>
            </div>
        </template>
    </DataTable>

    <DataPagination :pagination-data="bookings" item-name="bookings" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
