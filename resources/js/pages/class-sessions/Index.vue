<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import type { ClassSession } from '@/types/models';
import { formatDate } from '@/utils/date';
import { attendanceRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { Calendar, Clock, Edit, Eye, MapPin, MoreHorizontal, Plus, Trash2, Users, Video, X, UserPlus } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    sessions: PaginatedResponse<ClassSession>;
    filters: {
        search?: string;
        status?: string;
        session_type?: string;
        delivery_mode?: string;
        date_from?: string;
        date_to?: string;
        per_page?: number;
    };
    statusOptions: Record<string, string>;
    sessionTypeOptions: Record<string, string>;
    deliveryModeOptions: Record<string, string>;
}

const props = defineProps<Props>();
console.log('props', props);
const filters = ref({
    search: props.filters.search || '',
    status: props.filters.status || 'all',
    session_type: props.filters.session_type || 'all',
    delivery_mode: props.filters.delivery_mode || 'all',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.filters.per_page || 15,
});

const data = computed(() => props.sessions.data);

// Server-side filtering
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.status && newFilters.status !== 'all') params.set('status', newFilters.status);
    if (newFilters.session_type && newFilters.session_type !== 'all') params.set('session_type', newFilters.session_type);
    if (newFilters.delivery_mode && newFilters.delivery_mode !== 'all') params.set('delivery_mode', newFilters.delivery_mode);
    if (newFilters.date_from) params.set('date_from', newFilters.date_from);
    if (newFilters.date_to) params.set('date_to', newFilters.date_to);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/class-sessions${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['sessions', 'filters'],
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
        session_type: 'all',
        delivery_mode: 'all',
        date_from: '',
        date_to: '',
        per_page: 15,
    };
    router.visit('/class-sessions', {
        preserveState: true,
        preserveScroll: true,
        only: ['sessions', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return (
        filters.value.search ||
        (filters.value.status && filters.value.status !== 'all') ||
        (filters.value.session_type && filters.value.session_type !== 'all') ||
        (filters.value.delivery_mode && filters.value.delivery_mode !== 'all') ||
        filters.value.date_from ||
        filters.value.date_to
    );
});

// Column definitions
const columns: ColumnDef<ClassSession>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.sessions.current_page;
            const perPage = props.sessions.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Session Details',
        id: 'session_details',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'font-medium text-sm' }, session.session_title),
                h('div', { class: 'text-xs text-muted-foreground max-w-[200px] truncate' }, session.session_description || 'No description'),
            ]);
        },
    },
    {
        header: 'Course & Instructor',
        id: 'course_instructor',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return h('div', { class: 'space-y-1' }, [
                h(
                    'div',
                    { class: 'font-medium text-sm' },
                    `${session.course_offering?.curriculum_unit?.unit.code} - ${session.course_offering?.curriculum_unit?.unit.name}`,
                ),
                h('div', { class: 'text-xs text-muted-foreground' }, session.instructor?.name || 'No instructor assigned'),
            ]);
        },
    },
    {
        header: 'Schedule',
        id: 'schedule',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'flex items-center gap-1 text-sm' }, [
                    h(Calendar, { class: 'h-3 w-3' }),
                    h('span', formatDate(session.session_date)),
                ]),
                h('div', { class: 'flex items-center gap-1 text-xs text-muted-foreground' }, [
                    h(Clock, { class: 'h-3 w-3' }),
                    h('span', session.start_time),
                    h('span', ' - '),
                    h('span', session.end_time),
                ]),
                h('div', { class: 'text-xs text-muted-foreground' }, `${session.duration_minutes} minutes`),
            ]);
        },
    },
    {
        header: 'Type & Mode',
        id: 'type_mode',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return h('div', { class: 'space-y-2' }, [
                h(
                    Badge,
                    {
                        variant: 'outline',
                        class: 'text-xs',
                    },
                    () => session.session_type?.toUpperCase() || 'N/A',
                ),
                h('div', { class: 'flex items-center gap-1 text-xs' }, [
                    h(session.delivery_mode === 'online' ? Video : MapPin, { class: 'h-3 w-3' }),
                    h('span', { class: 'capitalize' }, session.delivery_mode?.replace('_', ' ')),
                ]),
            ]);
        },
    },
    {
        header: 'Attendance',
        id: 'attendance',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            const stats = session.attendance_stats;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'text-sm font-medium' }, `${stats?.attendance_percentage || 0}%`),
                h(
                    'div',
                    { class: 'text-xs text-muted-foreground' },
                    `${stats!.present + stats!.late || 0}/${stats!.total || session.expected_attendees || 0} students`,
                ),
                session.attendance_required &&
                    h(
                        Badge,
                        {
                            variant: 'secondary',
                            class: 'text-xs',
                        },
                        () => 'Required',
                    ),
            ]);
        },
    },
    {
        header: 'Status',
        id: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return h(
                Badge,
                {
                    variant: (session.status_badge_color as any) || 'default',
                    class: 'capitalize',
                },
                () => session.status?.replace('_', ' ') || 'Unknown',
            );
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
        only: ['sessions'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};

// Delete session
const deleteSession = (sessionId: number) => {
    if (!confirm('Are you sure you want to delete this class session?')) return;

    router.delete(`/class-sessions/${sessionId}`, {
        onSuccess: () => {
            toast.success('Class session deleted successfully');
        },
        onError: () => {
            toast.error('Failed to delete class session');
        },
    });
};

// Navigate to attendance
const viewAttendance = (sessionId: number) => {
    router.visit(`/attendance?session_id=${sessionId}`);
};

// Generate attendance for a session
const generateAttendance = (sessionId: number) => {
    router.post(`/class-sessions/${sessionId}/generate-attendance`, {}, {
        onSuccess: (page) => {
            // Check if there's a success message in the flash data
            if (page.props.flash?.success) {
                toast.success(page.props.flash.success);
            } else {
                toast.success('Attendance records generated successfully');
            }
        },
        onError: (errors) => {
            console.error('Generate attendance errors:', errors);
            // Check if there's an error message in the flash data
            if (errors.flash?.error) {
                toast.error(errors.flash.error);
            } else {
                toast.error('Failed to generate attendance records');
            }
        },
    });
};
</script>

<template>
    <Head title="Class Sessions" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Class Sessions</h1>
                <p class="text-muted-foreground">Manage and monitor class sessions across all courses</p>
            </div>
            <Link :href="attendanceRoutes.classSessions.create()">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    New Session
                </Button>
            </Link>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle>Sessions Overview</CardTitle>
                <CardDescription> {{ sessions.total }} sessions found </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-center gap-4">
                    <!-- Search -->
                    <div class="min-w-[200px] flex-1">
                        <DebouncedInput placeholder="Search sessions..." v-model="filters.search" @debounced="handleSearch" />
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

                    <!-- Session Type Filter -->
                    <Select
                        :model-value="filters.session_type"
                        @update:model-value="
                            (value) => {
                                filters.session_type = String(value || 'all');
                                applyFilters(filters);
                            }
                        "
                    >
                        <SelectTrigger class="w-[140px]">
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem v-for="(label, value) in sessionTypeOptions" :key="value" :value="value">
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Delivery Mode Filter -->
                    <Select
                        :model-value="filters.delivery_mode"
                        @update:model-value="
                            (value) => {
                                filters.delivery_mode = String(value || 'all');
                                applyFilters(filters);
                            }
                        "
                    >
                        <SelectTrigger class="w-[140px]">
                            <SelectValue placeholder="All Modes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Modes</SelectItem>
                            <SelectItem v-for="(label, value) in deliveryModeOptions" :key="value" :value="value">
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
                    <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="viewAttendance(row.original.id)">
                                    <Users class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View Attendance</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <Link :href="attendanceRoutes.classSessions.show(row.original.id)">
                                <DropdownMenuItem>
                                    <Eye class="mr-2 h-4 w-4" />
                                    View Details
                                </DropdownMenuItem>
                            </Link>
                            <Link :href="attendanceRoutes.classSessions.edit(row.original.id)">
                                <DropdownMenuItem>
                                    <Edit class="mr-2 h-4 w-4" />
                                    Edit Session
                                </DropdownMenuItem>
                            </Link>
                            <DropdownMenuItem @click="generateAttendance(row.original.id)">
                                <UserPlus class="mr-2 h-4 w-4" />
                                Generate Attendance
                            </DropdownMenuItem>
                            <DropdownMenuItem class="text-destructive" @click="deleteSession(row.original.id)">
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete Session
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
        </DataTable>

        <!-- Pagination -->
        <DataPagination :pagination-data="sessions" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
