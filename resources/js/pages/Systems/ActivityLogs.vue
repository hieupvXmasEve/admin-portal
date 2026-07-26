<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Eye, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

// Campus Option Interface
interface CampusOption {
    id: number;
    name: string;
    code: string;
}

// Current Campus Interface
interface CurrentCampus {
    id: number;
    name: string;
    code: string;
}

// Activity Log Interface
interface ActivityLog {
    id: number;
    log_name: string | null;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    event: string | null;
    causer_type: string | null;
    causer_id: number | null;
    properties: Record<string, any> | null;
    batch_uuid: string | null;
    created_at: string;
    updated_at: string;
    // Relationships
    subject?: {
        id: number;
        name?: string;
        title?: string;
        full_name?: string;
        student_id?: string;
        code?: string;
    } | null;
    causer?: {
        id: number;
        name: string;
        email?: string;
    } | null;
}

interface Props {
    activities: {
        current_page: number;
        data: ActivityLog[];
        first_page_url: string;
        from: number | null;
        last_page: number;
        last_page_url: string;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number | null;
        total: number;
    };
    filters: {
        search?: string;
        subject_type?: string;
        event?: string;
        campus_id?: string | number;
        per_page?: number;
    };
    subject_types: string[];
    events: string[];
    campus_options?: CampusOption[];
    is_system_admin: boolean;
    current_campus: CurrentCampus;
}

const props = defineProps<Props>();

// Reactive data
const data = computed(() => props.activities.data);

const filters = ref({
    search: props.filters.search || '',
    subject_type: props.filters.subject_type || 'all',
    event: props.filters.event || 'all',
    campus_id: props.filters.campus_id ? String(props.filters.campus_id) : 'all',
});

// Selected activity for viewing details
const selectedActivity = ref<ActivityLog | null>(null);
const showActivityDialog = ref(false);

// Helper function to format subject name
const formatSubjectName = (activity: ActivityLog): string => {
    if (!activity.subject) return 'N/A';

    const subject = activity.subject;
    return subject.name || subject.title || subject.full_name || subject.student_id || subject.code || `ID: ${subject.id}`;
};

// Helper function to format causer name
const formatCauserName = (activity: ActivityLog): string => {
    if (!activity.causer) return 'System';

    return activity.causer.name || activity.causer.email || `ID: ${activity.causer.id}`;
};

// Helper function to get event badge variant
const getEventBadgeVariant = (event: string | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (!event) return 'outline';

    switch (event.toLowerCase()) {
        case 'created':
            return 'default';
        case 'updated':
            return 'secondary';
        case 'deleted':
            return 'destructive';
        default:
            return 'outline';
    }
};

// Helper function to format properties
const formatProperties = (properties: Record<string, any> | null): string => {
    if (!properties || Object.keys(properties).length === 0) return 'None';

    try {
        return JSON.stringify(properties, null, 2);
    } catch {
        return 'Invalid JSON';
    }
};

// Helper function to get campus information from properties
const getCampusFromProperties = (properties: Record<string, any> | null): string => {
    if (!properties) return 'N/A';

    const campus_name = properties.campus_name;
    const campus_code = properties.campus_code;

    if (campus_name && campus_code) {
        return `${campus_name} (${campus_code})`;
    } else if (campus_name) {
        return campus_name;
    } else if (campus_code) {
        return campus_code;
    }

    return 'N/A';
};

// Helper function to format date
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
};

// Column definitions
const columns: ColumnDef<ActivityLog>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.activities.current_page;
            const perPage = props.activities.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        accessorKey: 'id',
        header: 'ID',
        enableSorting: true,
        cell: ({ row }) => {
            const activity = row.original;
            return h('div', { class: 'font-mono text-sm' }, activity.id.toString());
        },
    },
    {
        accessorKey: 'log_name',
        header: 'Log Name',
        enableSorting: false,
        cell: ({ row }) => {
            const logName = row.original.log_name;
            return h('div', { class: 'text-sm' }, logName || 'Default');
        },
    },
    {
        accessorKey: 'description',
        header: 'Description',
        enableSorting: false,
        cell: ({ row }) => {
            const description = row.original.description;
            return h(
                'div',
                {
                    class: 'text-sm max-w-xs truncate',
                    title: description,
                },
                description,
            );
        },
    },
    {
        accessorKey: 'event',
        header: 'Event',
        enableSorting: false,
        cell: ({ row }) => {
            const event = row.original.event;
            const variant = getEventBadgeVariant(event);

            return event ? h(Badge, { variant }, () => event) : h('span', { class: 'text-gray-400' }, 'N/A');
        },
    },
    {
        accessorKey: 'subject_type',
        header: 'Subject Type',
        enableSorting: false,
        cell: ({ row }) => {
            const subjectType = row.original.subject_type;
            return subjectType ? h('div', { class: 'text-sm font-medium' }, subjectType) : h('span', { class: 'text-gray-400' }, 'N/A');
        },
    },
    {
        header: 'Subject',
        id: 'subject',
        enableSorting: false,
        cell: ({ row }) => {
            const activity = row.original;
            const subjectName = formatSubjectName(activity);
            return h('div', { class: 'text-sm' }, subjectName);
        },
    },
    {
        header: 'Causer',
        id: 'causer',
        enableSorting: false,
        cell: ({ row }) => {
            const activity = row.original;
            const causerName = formatCauserName(activity);
            return h('div', { class: 'text-sm' }, causerName);
        },
    },
    {
        header: 'Campus',
        id: 'campus',
        enableSorting: false,
        cell: ({ row }) => {
            const activity = row.original;
            const campusInfo = getCampusFromProperties(activity.properties);
            return h('div', { class: 'text-sm' }, campusInfo);
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        enableSorting: true,
        cell: ({ row }) => {
            const createdAt = row.original.created_at;
            return h('div', { class: 'text-sm' }, formatDate(createdAt));
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
    },
];

// Filter handlers
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    updateFilters();
};

const handleFilter = () => {
    updateFilters();
};

const clearFilters = () => {
    filters.value = {
        search: '',
        subject_type: 'all',
        event: 'all',
        campus_id: 'all',
    };
    router.visit('/systems/activity-logs', {
        preserveState: true,
        preserveScroll: true,
        only: ['activities', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || filters.value.subject_type !== 'all' || filters.value.event !== 'all' || (filters.value.campus_id !== 'all' && props.is_system_admin);
});

const getFilterParams = () => {
    return {
        search: filters.value.search,
        subject_type: filters.value.subject_type === 'all' ? '' : filters.value.subject_type,
        event: filters.value.event === 'all' ? '' : filters.value.event,
        campus_id: filters.value.campus_id === 'all' ? '' : filters.value.campus_id,
    };
};

const updateFilters = () => {
    const params = getFilterParams();

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });

    const url = `/systems/activity-logs${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['activities', 'filters'],
    });
};

// View activity details
const viewActivity = (activity: ActivityLog) => {
    selectedActivity.value = activity;
    showActivityDialog.value = true;
};

// Pagination handlers
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['activities'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = {
        ...getFilterParams(),
        per_page: pageSize.toString(),
    };

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });

    const url = `/systems/activity-logs${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['activities', 'filters'],
    });
};
</script>

<template>
    <Head title="Activity Logs" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">Activity Logs</h1>
                    <div v-if="!is_system_admin" class="flex items-center gap-2">
                        <Badge variant="outline" class="text-xs"> {{ current_campus.name }} ({{ current_campus.code }}) </Badge>
                    </div>
                </div>
                <p class="text-sm text-gray-500">
                    <span v-if="is_system_admin">View system-wide activity logs and audit trail</span>
                    <span v-else>View activity logs for {{ current_campus.name }} campus</span>
                </p>
            </div>
        </div>

        <!-- Filters Card -->
        <Card>
            <CardContent class="p-4">
                <div class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex w-full items-center gap-2 md:w-auto">
                        <DebouncedInput v-model="filters.search" placeholder="Search by description, log name, causer name..." class="w-full md:w-64" @debounced="handleSearch" />

                        <Select v-model="filters.subject_type" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by subject type..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Subject Types</SelectItem>
                                <SelectItem v-for="subjectType in subject_types" :key="subjectType" :value="subjectType">
                                    {{ subjectType }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select v-model="filters.event" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by event..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Events</SelectItem>
                                <SelectItem v-for="event in events" :key="event" :value="event">
                                    {{ event }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Campus Filter for System Admins -->
                        <Select v-if="is_system_admin && campus_options" v-model="filters.campus_id" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by campus..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Campuses</SelectItem>
                                <SelectItem v-for="campus in campus_options" :key="campus.id" :value="campus.id.toString()"> {{ campus.name }} ({{ campus.code }}) </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button v-if="hasActiveFilters" variant="ghost" @click="clearFilters">
                            <X class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Data Table Card -->
        <Card>
            <CardContent class="p-4">
                <div class="mt-4">
                    <DataTable :columns="columns" :data="data">
                        <template #cell-actions="{ row }">
                            <div class="flex items-center space-x-2">
                                <Button variant="ghost" size="icon" @click="viewActivity(row.original)">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </CardContent>
        </Card>

        <!-- Pagination -->
        <DataPagination :pagination-data="activities" item-name="activities" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>

    <!-- Activity Details Dialog -->
    <Dialog v-model:open="showActivityDialog">
        <DialogContent class="max-h-[90vh] !max-w-7xl">
            <DialogHeader>
                <DialogTitle>Activity Log Details</DialogTitle>
            </DialogHeader>
            <DialogDescription></DialogDescription>
            <ScrollArea class="max-h-[70vh]">
                <div v-if="selectedActivity" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">ID</h4>
                            <p class="font-mono">{{ selectedActivity.id }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Log Name</h4>
                            <p>{{ selectedActivity.log_name || 'Default' }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Event</h4>
                            <Badge v-if="selectedActivity.event" :variant="getEventBadgeVariant(selectedActivity.event)">
                                {{ selectedActivity.event }}
                            </Badge>
                            <span v-else class="text-gray-400">N/A</span>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Created At</h4>
                            <p>{{ formatDate(selectedActivity.created_at) }}</p>
                        </div>
                        <div class="col-span-2">
                            <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Description</h4>
                            <p>{{ selectedActivity.description }}</p>
                        </div>
                    </div>

                    <!-- Campus Context -->
                    <div v-if="selectedActivity.properties && (selectedActivity.properties.campus_name || selectedActivity.properties.campus_code)" class="border-t pt-4">
                        <h3 class="mb-3 text-lg font-semibold">Campus Context</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Campus</h4>
                                <p>{{ getCampusFromProperties(selectedActivity.properties) }}</p>
                            </div>
                            <div v-if="selectedActivity.properties.academic_year">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Academic Year</h4>
                                <p>{{ selectedActivity.properties.academic_year }}</p>
                            </div>
                            <div v-if="selectedActivity.properties.local_timestamp">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Local Timestamp</h4>
                                <p>{{ formatDate(selectedActivity.properties.local_timestamp) }}</p>
                            </div>
                            <div v-if="selectedActivity.properties.acting_campus && selectedActivity.properties.acting_campus !== getCampusFromProperties(selectedActivity.properties)">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Acting Campus</h4>
                                <Badge variant="secondary">{{ selectedActivity.properties.acting_campus }}</Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Subject & Causer Information -->
                    <div class="border-t pt-4">
                        <h3 class="mb-3 text-lg font-semibold">Subject & Causer</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Subject Type</h4>
                                <p>{{ selectedActivity.subject_type || 'N/A' }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Subject</h4>
                                <p>{{ formatSubjectName(selectedActivity) }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Causer Type</h4>
                                <p>{{ selectedActivity.causer_type || 'N/A' }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Causer</h4>
                                <p>{{ formatCauserName(selectedActivity) }}</p>
                            </div>
                            <div v-if="selectedActivity.batch_uuid" class="col-span-2">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Batch UUID</h4>
                                <p class="font-mono text-sm">{{ selectedActivity.batch_uuid }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Change Information -->
                    <div
                        v-if="selectedActivity.properties && (selectedActivity.properties.changed_fields || selectedActivity.properties.change_count || selectedActivity.properties.old || selectedActivity.properties.attributes)"
                        class="border-t pt-4"
                    >
                        <h3 class="mb-3 text-lg font-semibold">Change Information</h3>
                        <div class="mb-4 grid grid-cols-2 gap-4">
                            <div v-if="selectedActivity.properties.change_count">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Changes Count</h4>
                                <Badge variant="outline">{{ selectedActivity.properties.change_count }} field(s)</Badge>
                            </div>
                            <div v-if="selectedActivity.properties.changed_fields">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Changed Fields</h4>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="field in selectedActivity.properties.changed_fields" :key="field" variant="secondary" class="text-xs">
                                        {{ field }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedActivity.properties.old || selectedActivity.properties.attributes" class="grid grid-cols-2 gap-4">
                            <div v-if="selectedActivity.properties.old">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">Old Values</h4>
                                <ScrollArea class="h-32 w-full rounded border">
                                    <pre class="p-2 text-xs">{{ JSON.stringify(selectedActivity.properties.old, null, 2) }}</pre>
                                </ScrollArea>
                            </div>
                            <div v-if="selectedActivity.properties.attributes">
                                <h4 class="text-sm font-medium tracking-wide text-gray-500 uppercase">New Values</h4>
                                <ScrollArea class="h-32 w-full rounded border">
                                    <pre class="p-2 text-xs">{{ JSON.stringify(selectedActivity.properties.attributes, null, 2) }}</pre>
                                </ScrollArea>
                            </div>
                        </div>
                    </div>

                    <!-- Full Properties -->
                    <div v-if="selectedActivity.properties" class="border-t pt-4">
                        <h3 class="mb-3 text-lg font-semibold">Full Properties</h3>
                        <ScrollArea class="h-64 w-full rounded border">
                            <pre class="p-4 text-sm">{{ formatProperties(selectedActivity.properties) }}</pre>
                        </ScrollArea>
                    </div>
                </div>
            </ScrollArea>
        </DialogContent>
    </Dialog>
</template>
