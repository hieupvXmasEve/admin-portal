<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { getActionTypeBadgeClass, getActionTypeLabel, type ActionTypeOption, type Campus, type Semester, type StudentActionLog, type User } from '@/types/student-action';
import { studentRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Download, ExternalLink, FileWarning, Loader2, Search, X } from 'lucide-vue-next';
import { h, ref } from 'vue';

interface Props {
    actionLogs: {
        data: StudentActionLog[];
        meta: any;
        links: any;
    };
    filters: {
        action_type: string | null;
        date_from: string | null;
        date_to: string | null;
        signed_date_from: string | null;
        signed_date_to: string | null;
        semester_id: number | null;
        campus_id: number | null;
        to_campus_id: number | null;
        from_campus_id: number | null;
        actor_id: number | null;
        missing_documents: string | null;
        search: string | null;
        sort: string;
        direction: 'asc' | 'desc';
        per_page: number;
    };
    options: {
        actionTypes: ActionTypeOption[];
        semesters: Semester[];
        campuses: Campus[];
        actors: User[];
    };
}

const props = defineProps<Props>();

const { filters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, clearFilters } = useInertiaFilters({
    baseUrl: route('reports.student-actions.index'),
    initialFilters: props.filters,
    defaultValues: {
        action_type: null,
        date_from: null,
        date_to: null,
        signed_date_from: null,
        signed_date_to: null,
        semester_id: null,
        campus_id: props.filters.campus_id,
        to_campus_id: null,
        from_campus_id: null,
        actor_id: null,
        missing_documents: null,
        search: '',
        sort: 'created_at',
        direction: 'desc',
        per_page: 15,
    },
    only: ['actionLogs', 'filters'],
});

const isExporting = ref(false);

const handleExport = () => {
    isExporting.value = true;
    const url = route('reports.student-actions.export', {
        ...filters,
    });
    window.location.href = url;
    setTimeout(() => {
        isExporting.value = false;
    }, 2000);
};

const formatDate = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateOnly = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
};

const columns: ColumnDef<StudentActionLog>[] = [
    {
        accessorKey: 'student.student_id',
        header: 'Student ID',
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: route('students.actions.index', { student: row.original.student_id }),
                    class: 'text-blue-600 hover:underline font-medium',
                },
                () => row.original.student?.student_id ?? '-',
            ),
    },
    {
        accessorKey: 'student.full_name',
        header: 'Student Name',
        cell: ({ row }) => h('span', { class: 'truncate max-w-[150px] block' }, row.original.student?.full_name ?? '-'),
    },
    {
        accessorKey: 'previous_status',
        header: 'Previous Type',
        cell: ({ row }) => {
            const previous_status = row.original.previous_status || 'none';
            return h(Badge, { class: getActionTypeBadgeClass(previous_status) }, () => getActionTypeLabel(previous_status));
        },
    },
    {
        accessorKey: 'action_type',
        header: 'Action Type',
        cell: ({ row }) => {
            const actionType = row.original.action_type;
            return h(Badge, { class: getActionTypeBadgeClass(actionType) }, () => getActionTypeLabel(actionType));
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Changed At',
        cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
        accessorKey: 'changed_by.name',
        header: 'Changed By',
        cell: ({ row }) => row.original.changed_by?.name ?? '-',
    },
    {
        accessorKey: 'signed_at',
        header: 'Signed At',
        cell: ({ row }) => formatDateOnly(row.original.signed_at),
    },
    {
        accessorKey: 'reason',
        header: 'Reason',
        cell: ({ row }) => h('span', { class: 'truncate max-w-[200px] block', title: row.original.reason }, row.original.reason),
    },
    {
        accessorKey: 'missing_documents',
        header: 'Missing Docs',
        cell: ({ row }) => {
            if (row.original.missing_documents) {
                return h('div', { class: 'flex items-center text-amber-600' }, [h(FileWarning, { class: 'h-4 w-4 mr-1' }), 'Yes']);
            }
            return h('span', { class: 'text-muted-foreground' }, 'No');
        },
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: studentRoutes.studentStatusActionShow(row.original.id),
                    class: 'text-blue-600 hover:text-blue-800',
                },
                () => h(ExternalLink, { class: 'h-4 w-4' }),
            ),
    },
];
</script>

<template>

    <Head title="Student Actions Audit" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Actions Audit</h1>
                <p class="text-muted-foreground mt-1">View and export student administrative action history.</p>
            </div>
            <Button variant="outline" @click="handleExport" :disabled="isExporting">
                <Loader2 v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
                <Download v-else class="mr-2 h-4 w-4" />
                Export CSV
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Action Logs</CardTitle>
                <CardDescription> Complete audit trail of all student administrative actions. Use filters to find
                    specific records. </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- Filters Row 1 -->
                <div class="mb-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                        <Input placeholder="Search student..." class="pl-8" :model-value="filters.search"
                            @update:model-value="handleSearch" />
                    </div>

                    <Select :model-value="filters.action_type ?? 'all'"
                        @update:model-value="(v) => (filters.action_type = v === 'all' ? null : v)">
                        <SelectTrigger>
                            <SelectValue placeholder="Action Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Action Types</SelectItem>
                            <SelectItem v-for="actionType in options.actionTypes" :key="actionType.value"
                                :value="actionType.value">
                                {{ actionType.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="filters.semester_id ? String(filters.semester_id) : 'all'"
                        @update:model-value="(v) => (filters.semester_id = v === 'all' ? null : Number(v))">
                        <SelectTrigger>
                            <SelectValue placeholder="Semester" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="String(sem.id)">
                                {{ sem.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="filters.actor_id ? String(filters.actor_id) : 'all'"
                        @update:model-value="(v) => (filters.actor_id = v === 'all' ? null : Number(v))">
                        <SelectTrigger>
                            <SelectValue placeholder="Changed By" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Actors</SelectItem>
                            <SelectItem v-for="actor in options.actors" :key="actor.id" :value="String(actor.id)">
                                {{ actor.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Filters Row 2 -->
                <div class="mb-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="text-muted-foreground mb-1 block text-xs">Date From</label>
                        <Input type="date" v-model="filters.date_from" />
                    </div>
                    <div>
                        <label class="text-muted-foreground mb-1 block text-xs">Date To</label>
                        <Input type="date" v-model="filters.date_to" />
                    </div>

                    <Select :model-value="filters.missing_documents ?? 'all'"
                        @update:model-value="(v) => (filters.missing_documents = v === 'all' ? null : v)">
                        <SelectTrigger>
                            <SelectValue placeholder="Missing Documents" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem value="true">Missing Documents</SelectItem>
                            <SelectItem value="false">Complete Documents</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="filters.campus_id ? String(filters.campus_id) : 'all'"
                        @update:model-value="(v) => (filters.campus_id = v === 'all' ? null : Number(v))">
                        <SelectTrigger>
                            <SelectValue placeholder="Campus" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Campuses</SelectItem>
                            <SelectItem v-for="campus in options.campuses" :key="campus.id" :value="String(campus.id)">
                                {{ campus.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="filters.action_type || filters.semester_id || filters.actor_id || filters.missing_documents || filters.search || filters.date_from || filters.date_to"
                    class="mb-4 flex items-center gap-2">
                    <Button variant="ghost" size="sm" class="h-8 px-2 lg:px-3" @click="clearFilters">
                        Reset Filters
                        <X class="ml-2 h-4 w-4" />
                    </Button>
                </div>

                <DataTable :data="actionLogs.data" :columns="columns" :loading="false"
                    empty-message="No action logs found matching your filters." />

                <div class="mt-4">
                    <DataPagination :pagination-data="actionLogs" @navigate="handlePaginationNavigate"
                        @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
