<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import type { Attendance } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Calendar, CheckCircle, Clock, Edit, FileText, QrCode, User, UserCheck, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

// Helper function to map attendance status to badge variant
const getStatusBadgeVariant = (status: string | undefined): 'success' | 'warning' | 'destructive' | 'purple' | 'default' => {
    switch (status) {
        case 'present':
            return 'success'; // Green - student attended
        case 'late':
            return 'warning'; // Yellow - student was late
        case 'absent':
            return 'destructive'; // Red - student did not attend
        case 'excused':
            return 'purple'; // Purple - absence is excused
        default:
            return 'default';
    }
};

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
        cell: 'no',
    },
    {
        header: 'Student',
        id: 'student',
        enableSorting: false,
        cell: 'student',
    },
    {
        header: 'Session Details',
        id: 'session_details',
        enableSorting: false,
        cell: 'session_details',
    },
    {
        header: 'Attendance Status',
        id: 'attendance_status',
        enableSorting: false,
        cell: 'attendance_status',
    },
    {
        header: 'Recording Method',
        id: 'recording_method',
        enableSorting: false,
        cell: 'recording_method',
    },
    {
        id: 'actions',
        header: 'Course Offering',
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
            <template #cell-no="{ row }">
                {{ (attendances.current_page - 1) * attendances.per_page + row.index + 1 }}
            </template>

            <template #cell-student="{ row }">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <User class="h-4 w-4" />
                        <span class="font-medium">{{ row.original.student?.full_name }}</span>
                    </div>
                    <div class="text-muted-foreground text-xs">{{ row.original.student?.student_id }}</div>
                    <div class="text-muted-foreground text-xs">{{ row.original.student?.email }}</div>
                </div>
            </template>

            <template #cell-session_details="{ row }">
                <div class="space-y-1">
                    <div class="text-sm font-medium">{{ row.original.class_session?.session_title }}</div>
                    <div class="text-muted-foreground text-xs">{{ row.original.class_session?.course_offering?.unit?.code }} - {{ row.original.class_session?.course_offering?.unit?.name }}</div>
                    <div class="text-muted-foreground flex items-center gap-1 text-xs">
                        <Calendar class="h-3 w-3" />
                        <span>{{ row.original.class_session?.session_date }}</span>
                    </div>
                    <div class="text-muted-foreground text-xs">{{ row.original.class_session?.lecture?.display_name }}</div>
                </div>
            </template>

            <template #cell-attendance_status="{ row }">
                <Badge :variant="getStatusBadgeVariant(row.original.status)" class="capitalize">
                    <CheckCircle v-if="row.original.status === 'present'" class="mr-1 h-3 w-3" />
                    <Clock v-else-if="row.original.status === 'late'" class="mr-1 h-3 w-3" />
                    <XCircle v-else-if="row.original.status === 'absent'" class="mr-1 h-3 w-3" />
                    <UserCheck v-else-if="row.original.status === 'excused'" class="mr-1 h-3 w-3" />
                    <User v-else class="mr-1 h-3 w-3" />
                    {{ row.original.status?.replace('_', ' ') }}
                </Badge>
            </template>

            <template #cell-recording_method="{ row }">
                <div class="flex items-center gap-2">
                    <QrCode v-if="row.original.recording_method === 'qr_code'" class="h-4 w-4" />
                    <Edit v-else-if="row.original.recording_method === 'manual'" class="h-4 w-4" />
                    <User v-else class="h-4 w-4" />
                    <span class="text-sm capitalize">{{ row.original.recording_method?.replace('_', ' ') }}</span>
                    <Badge v-if="row.original.is_verified" variant="secondary" class="text-xs">Verified</Badge>
                </div>
            </template>

            <template #cell-actions="{ row }">
                <Link v-if="row.original.class_session?.course_offering_id" :href="route('course-offerings.show', row.original.class_session.course_offering_id) + '?tab=sessions'">
                    <Button variant="outline" size="sm">
                        <CheckCircle class="mr-2 h-4 w-4" />
                        View in Course Offering
                    </Button>
                </Link>
                <span v-else class="text-muted-foreground text-xs">—</span>
            </template>
        </DataTable>

        <!-- Pagination -->
        <DataPagination :pagination-data="attendances" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
