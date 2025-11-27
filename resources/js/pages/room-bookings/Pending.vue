<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { PaginatedResponse } from '@/types';
import type { RoomBooking } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Building2, Calendar, Check, Clock, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    bookings: PaginatedResponse<RoomBooking>;
    filters?: {
        search?: string;
        room_id?: string;
        building_id?: string;
        booking_type?: string;
        start_date?: string;
        end_date?: string;
    };
    statistics?: {
        total: number;
        pending: number;
        approved: number;
        rejected: number;
        cancelled: number;
    };
    booking_types?: Array<{ value: string; label: string }>;
    buildings?: Array<{ id: number; value: string; label: string }>;
    rooms?: Array<{ id: number; value: string; label: string }>;
}>();

const data = computed(() => props.bookings.data);

const filters = ref({
    search: props.filters?.search || '',
    room_id: props.filters?.room_id || '',
    building_id: props.filters?.building_id || '',
    booking_type: props.filters?.booking_type || '',
    per_page: 15,
});

// Dialogs
const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const selectedBooking = ref<RoomBooking | null>(null);
const approvalNote = ref('');
const rejectionReason = ref('');
const processing = ref(false);

const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();
    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.room_id) params.set('room_id', newFilters.room_id);
    if (newFilters.building_id) params.set('building_id', newFilters.building_id);
    if (newFilters.booking_type) params.set('booking_type', newFilters.booking_type);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    router.visit(`/room-bookings-pending${params.toString() ? '?' + params.toString() : ''}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['bookings', 'filters', 'statistics'],
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const updateFilter = (key: keyof typeof filters.value, value: string | number) => {
    const stringValue = String(value);
    (filters.value as Record<string, string | number>)[key] = stringValue === 'all' ? '' : stringValue;
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = { search: '', room_id: '', building_id: '', booking_type: '', per_page: 15 };
    router.visit('/room-bookings-pending', { preserveState: true, preserveScroll: true });
};

const hasActiveFilters = computed(() => filters.value.search || filters.value.room_id || filters.value.building_id || filters.value.booking_type);

const formatDate = (date: string) => new Date(date).toLocaleDateString('en-US', { 
    weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' 
});

const formatTime = (time: string) => {
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

const openApproveDialog = (booking: RoomBooking) => {
    selectedBooking.value = booking;
    approvalNote.value = '';
    showApproveDialog.value = true;
};

const openRejectDialog = (booking: RoomBooking) => {
    selectedBooking.value = booking;
    rejectionReason.value = '';
    showRejectDialog.value = true;
};

const handleApprove = () => {
    if (!selectedBooking.value) return;
    processing.value = true;
    router.post(`/room-bookings/${selectedBooking.value.id}/approve`, { note: approvalNote.value }, {
        onSuccess: () => {
            toast.success('Booking approved successfully');
            showApproveDialog.value = false;
            selectedBooking.value = null;
        },
        onError: () => toast.error('Failed to approve booking'),
        onFinish: () => processing.value = false,
    });
};

const handleReject = () => {
    if (!selectedBooking.value || !rejectionReason.value.trim()) {
        toast.error('Please provide a reason for rejection');
        return;
    }
    processing.value = true;
    router.post(`/room-bookings/${selectedBooking.value.id}/reject`, { reason: rejectionReason.value }, {
        onSuccess: () => {
            toast.success('Booking rejected');
            showRejectDialog.value = false;
            selectedBooking.value = null;
        },
        onError: () => toast.error('Failed to reject booking'),
        onFinish: () => processing.value = false,
    });
};

const viewBooking = (id: number) => router.visit(`/room-bookings/${id}`);

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
        cell: ({ row }) => row.original.booker_name || 'Unknown',
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const booking = row.original;
            return h('div', { class: 'flex items-center space-x-2' }, [
                h(Button, { variant: 'default', size: 'sm', onClick: () => openApproveDialog(booking) }, 
                    () => [h(Check, { class: 'mr-1 w-4 h-4' }), 'Approve']),
                h(Button, { variant: 'destructive', size: 'sm', onClick: () => openRejectDialog(booking) }, 
                    () => [h(X, { class: 'mr-1 w-4 h-4' }), 'Reject']),
                h(Button, { variant: 'ghost', size: 'sm', onClick: () => viewBooking(booking.id) }, 
                    () => 'View'),
            ]);
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
    <Head title="Pending Approvals" />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Pending Approvals</h1>
            <div v-if="statistics" class="text-muted-foreground mt-2 flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1">
                    <Clock class="h-4 w-4" />
                    <span>{{ statistics.pending }} Pending</span>
                </div>
            </div>
        </div>
        <Button variant="outline" @click="router.visit('/room-bookings')">
            All Bookings
        </Button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <DebouncedInput placeholder="Search bookings..." v-model="filters.search" @debounced="handleSearch" />
        </div>

        <Select :model-value="filters.booking_type || 'all'" @update:model-value="(v) => updateFilter('booking_type', v)">
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

    <!-- Empty state -->
    <div v-if="data.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center">
        <Check class="text-muted-foreground h-12 w-12" />
        <h3 class="mt-4 text-lg font-semibold">No Pending Bookings</h3>
        <p class="text-muted-foreground mt-2">All bookings have been reviewed. Great job!</p>
    </div>

    <DataTable v-else :data="data" :columns="columns" :show-column-toggle="false">
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
                <div class="text-muted-foreground text-sm">
                    {{ formatTime(row.original.start_time) }} - {{ formatTime(row.original.end_time) }}
                </div>
            </div>
        </template>
    </DataTable>

    <DataPagination v-if="data.length > 0" :pagination-data="bookings" item-name="bookings" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Approve Dialog -->
    <Dialog v-model:open="showApproveDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Approve Booking</DialogTitle>
                <DialogDescription>
                    Are you sure you want to approve "{{ selectedBooking?.title }}"?
                </DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <Label>Note (Optional)</Label>
                <Textarea v-model="approvalNote" placeholder="Add a note for the requester..." class="mt-2" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showApproveDialog = false">Cancel</Button>
                <Button @click="handleApprove" :disabled="processing">
                    {{ processing ? 'Approving...' : 'Approve Booking' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Reject Dialog -->
    <Dialog v-model:open="showRejectDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject Booking</DialogTitle>
                <DialogDescription>
                    Please provide a reason for rejecting "{{ selectedBooking?.title }}".
                </DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <Label>Rejection Reason *</Label>
                <Textarea v-model="rejectionReason" placeholder="Enter the reason for rejection..." class="mt-2" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showRejectDialog = false">Cancel</Button>
                <Button variant="destructive" @click="handleReject" :disabled="processing || !rejectionReason.trim()">
                    {{ processing ? 'Rejecting...' : 'Reject Booking' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

