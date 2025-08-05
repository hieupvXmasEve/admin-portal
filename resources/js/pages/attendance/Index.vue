<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import type { Attendance } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Calendar, CheckCircle, Clock, Edit, Eye, FileText, MoreHorizontal, QrCode, Trash2, User, UserCheck, X, XCircle } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    attendances: PaginatedResponse<Attendance>;
    filters: {
        search?: string;
        status?: string;
        session_id?: string;
        recording_method?: string;
        date_from?: string;
        date_to?: string;
        participation_score_min?: number;
        participation_score_max?: number;
        per_page?: number;
    };
    statusOptions: Record<string, string>;
    recordingMethodOptions: Record<string, string>;
    classSessions: Array<{
        id: number;
        title: string;
        course: string;
        date: string;
    }>;
    statistics: {
        total_records: number;
        present: number;
        late: number;
        absent: number;
        excused: number;
    };
}

const props = defineProps<Props>();

const filters = ref({
    search: props.filters.search || '',
    status: props.filters.status || 'all',
    session_id: props.filters.session_id || 'all',
    recording_method: props.filters.recording_method || 'all',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    participation_score_min: props.filters.participation_score_min || null,
    participation_score_max: props.filters.participation_score_max || null,
    per_page: props.filters.per_page || 15,
});

const data = computed(() => props.attendances.data);

// Server-side filtering
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.status && newFilters.status !== 'all') params.set('status', newFilters.status);
    if (newFilters.session_id && newFilters.session_id !== 'all') params.set('session_id', newFilters.session_id);
    if (newFilters.recording_method && newFilters.recording_method !== 'all') params.set('recording_method', newFilters.recording_method);
    if (newFilters.date_from) params.set('date_from', newFilters.date_from);
    if (newFilters.date_to) params.set('date_to', newFilters.date_to);
    if (newFilters.participation_score_min) params.set('participation_score_min', newFilters.participation_score_min.toString());
    if (newFilters.participation_score_max) params.set('participation_score_max', newFilters.participation_score_max.toString());
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/attendance${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['attendances', 'filters'],
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        status: 'all',
        session_id: 'all',
        recording_method: 'all',
        date_from: '',
        date_to: '',
        participation_score_min: null,
        participation_score_max: null,
        per_page: 15,
    };
    router.visit('/attendance', {
        preserveState: true,
        preserveScroll: true,
        only: ['attendances', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return (
        filters.value.search ||
        (filters.value.status && filters.value.status !== 'all') ||
        (filters.value.session_id && filters.value.session_id !== 'all') ||
        (filters.value.recording_method && filters.value.recording_method !== 'all') ||
        filters.value.date_from ||
        filters.value.date_to ||
        filters.value.participation_score_min ||
        filters.value.participation_score_max
    );
});

// Column definitions
const columns: ColumnDef<Attendance>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.attendances.current_page;
            const perPage = props.attendances.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Student',
        id: 'student',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'flex items-center gap-2' }, [h(User, { class: 'h-4 w-4' }), h('span', { class: 'font-medium' }, attendance.student?.full_name)]),
                h('div', { class: 'text-xs text-muted-foreground' }, attendance.student?.student_code),
                h('div', { class: 'text-xs text-muted-foreground' }, attendance.student?.email),
            ]);
        },
    },
    {
        header: 'Session Details',
        id: 'session_details',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            const session = attendance.class_session;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'font-medium text-sm' }, session?.session_title),
                h('div', { class: 'text-xs text-muted-foreground' }, `${session?.course_offering?.curriculum_unit?.unit.code} - ${session?.course_offering?.curriculum_unit?.unit.name}`),
                h('div', { class: 'flex items-center gap-1 text-xs text-muted-foreground' }, [h(Calendar, { class: 'h-3 w-3' }), h('span', session?.formatted_date)]),
                h('div', { class: 'text-xs text-muted-foreground' }, session?.instructor?.name),
            ]);
        },
    },
    {
        header: 'Attendance Status',
        id: 'attendance_status',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            const statusIcons = {
                present: CheckCircle,
                late: Clock,
                absent: XCircle,
                excused: UserCheck,
            };
            const IconComponent = statusIcons[attendance.status as keyof typeof statusIcons] || User;

            return h('div', { class: 'flex items-center gap-2' }, [
                h(
                    Badge,
                    {
                        variant: attendance.status_badge_color as any,
                        class: 'capitalize',
                    },
                    () => [h(IconComponent, { class: 'mr-1 h-3 w-3' }), attendance.status?.replace('_', ' ')],
                ),
            ]);
        },
    },
    {
        header: 'Time Details',
        id: 'time_details',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'space-y-1 text-xs' }, [
                attendance.check_in_time && h('div', [h('span', { class: 'text-muted-foreground' }, 'In: '), h('span', attendance.formatted_check_in_time)]),
                attendance.check_out_time && h('div', [h('span', { class: 'text-muted-foreground' }, 'Out: '), h('span', attendance.formatted_check_out_time)]),
                attendance.minutes_late && attendance.minutes_late > 0 && h('div', { class: 'text-orange-600' }, `${attendance.minutes_late} min late`),
                attendance.minutes_present && h('div', { class: 'text-green-600' }, `${attendance.minutes_present} min present`),
            ]);
        },
    },
    {
        header: 'Participation',
        id: 'participation',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'space-y-1' }, [
                attendance.participation_score && h('div', { class: 'font-medium text-sm' }, `Score: ${attendance.participation_score}%`),
                attendance.participation_level &&
                    h(
                        Badge,
                        {
                            variant: 'outline',
                            class: 'text-xs capitalize',
                        },
                        attendance.participation_level,
                    ),
            ]);
        },
    },
    {
        header: 'Recording Method',
        id: 'recording_method',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            const methodIcons = {
                qr_code: QrCode,
                manual: Edit,
                rfid: User,
                geolocation: User,
                biometric: User,
                mobile_app: User,
            };
            const IconComponent = methodIcons[attendance.recording_method as keyof typeof methodIcons] || User;

            return h('div', { class: 'flex items-center gap-2' }, [
                h(IconComponent, { class: 'h-4 w-4' }),
                h('span', { class: 'text-sm capitalize' }, attendance.recording_method?.replace('_', ' ')),
                attendance.is_verified &&
                    h(
                        Badge,
                        {
                            variant: 'secondary',
                            class: 'text-xs',
                        },
                        'Verified',
                    ),
            ]);
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions',
    },
];

// Pagination handlers
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['attendances'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};

// Delete attendance record
const deleteAttendance = (attendanceId: number) => {
    if (!confirm('Are you sure you want to delete this attendance record?')) return;

    router.delete(`/attendance/${attendanceId}`, {
        onSuccess: () => {
            toast.success('Attendance record deleted successfully');
        },
        onError: () => {
            toast.error('Failed to delete attendance record');
        },
    });
};

// Navigate to sessions
const viewSessions = () => {
    router.visit('/class-sessions');
};
</script>

<template>
    <Head title="Attendance Tracking" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Attendance Tracking</h1>
                <p class="text-muted-foreground">Monitor student attendance and participation across all sessions</p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" @click="viewSessions">
                    <Calendar class="mr-2 h-4 w-4" />
                    View Sessions
                </Button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <Card>
                <CardContent class="flex items-center p-6">
                    <FileText class="text-muted-foreground h-8 w-8" />
                    <div class="ml-4">
                        <p class="text-sm leading-none font-medium">Total Records</p>
                        <p class="text-2xl font-bold">{{ statistics.total_records }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex items-center p-6">
                    <CheckCircle class="h-8 w-8 text-green-600" />
                    <div class="ml-4">
                        <p class="text-sm leading-none font-medium">Present</p>
                        <p class="text-2xl font-bold text-green-600">{{ statistics.present }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex items-center p-6">
                    <Clock class="h-8 w-8 text-orange-600" />
                    <div class="ml-4">
                        <p class="text-sm leading-none font-medium">Late</p>
                        <p class="text-2xl font-bold text-orange-600">{{ statistics.late }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex items-center p-6">
                    <XCircle class="h-8 w-8 text-red-600" />
                    <div class="ml-4">
                        <p class="text-sm leading-none font-medium">Absent</p>
                        <p class="text-2xl font-bold text-red-600">{{ statistics.absent }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="flex items-center p-6">
                    <UserCheck class="h-8 w-8 text-purple-600" />
                    <div class="ml-4">
                        <p class="text-sm leading-none font-medium">Excused</p>
                        <p class="text-2xl font-bold text-purple-600">{{ statistics.excused }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle>Attendance Records</CardTitle>
                <CardDescription> {{ attendances.total }} records found </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-center gap-4">
                    <!-- Search -->
                    <div class="min-w-[200px] flex-1">
                        <DebouncedInput placeholder="Search students or sessions..." v-model="filters.search" @debounced="handleSearch" />
                    </div>

                    <!-- Status Filter -->
                    <Select
                        :model-value="filters.status"
                        @update:model-value="
                            (value) => {
                                filters.status = String(value || 'all');
                                applyFilters(filters);
                            }
                        "
                    >
                        <SelectTrigger class="w-[140px]">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem v-for="(label, value) in statusOptions" :key="value" :value="value">
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Session Filter -->
                    <Select
                        :model-value="filters.session_id"
                        @update:model-value="
                            (value) => {
                                filters.session_id = String(value || 'all');
                                applyFilters(filters);
                            }
                        "
                    >
                        <SelectTrigger class="w-[200px]">
                            <SelectValue placeholder="All Sessions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Sessions</SelectItem>
                            <SelectItem v-for="session in classSessions" :key="session.id" :value="session.id.toString()"> {{ session.course }} - {{ session.title }} </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Recording Method Filter -->
                    <Select
                        :model-value="filters.recording_method"
                        @update:model-value="
                            (value) => {
                                filters.recording_method = String(value || 'all');
                                applyFilters(filters);
                            }
                        "
                    >
                        <SelectTrigger class="w-[140px]">
                            <SelectValue placeholder="All Methods" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Methods</SelectItem>
                            <SelectItem v-for="(label, value) in recordingMethodOptions" :key="value" :value="value">
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Clear Filters -->
                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                        <X class="mr-2 h-4 w-4" />
                        Clear Filters
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Data Table -->
        <DataTable :data="data" :columns="columns">
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <Link :href="route('attendance.show', row.original.id)">
                                <DropdownMenuItem>
                                    <Eye class="mr-2 h-4 w-4" />
                                    View Details
                                </DropdownMenuItem>
                            </Link>
                            <Link :href="route('attendance.edit', row.original.id)">
                                <DropdownMenuItem>
                                    <Edit class="mr-2 h-4 w-4" />
                                    Edit Record
                                </DropdownMenuItem>
                            </Link>
                            <DropdownMenuItem class="text-destructive" @click="deleteAttendance(row.original.id)">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete Record
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
        </DataTable>

        <!-- Pagination -->
        <DataPagination :pagination-data="attendances" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
