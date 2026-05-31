<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { getActionTypeBadgeClass, getActionTypeLabel, type ActionTypeOption, type EgcDeferBlockOption, type Semester, type StudentActionLog, type User } from '@/types/student-action';
import { studentRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Download, ExternalLink, FileWarning, Filter, Loader2, Search, Upload, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

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
        from_semester_id: number | null;
        egc_defer_from_block_number: number | null;
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
        actors: User[];
        egcDeferBlocks: EgcDeferBlockOption[];
    };
}

const props = defineProps<Props>();

const { filters, handleSearch, handlePaginationNavigate, handlePageSizeChange, clearFilters } = useInertiaFilters({
    baseUrl: route('reports.student-actions.index'),
    initialFilters: props.filters,
    defaultValues: {
        action_type: null,
        date_from: null,
        date_to: null,
        signed_date_from: null,
        signed_date_to: null,
        from_semester_id: null,
        egc_defer_from_block_number: null,
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
const showAdvancedFilters = ref(false);

const hasFilterValue = (value: unknown): boolean => value !== undefined && value !== null && value !== '';

const advancedFilterCount = computed(() => {
    let count = 0;

    if (hasFilterValue(filters.date_from)) count++;
    if (hasFilterValue(filters.date_to)) count++;
    if (hasFilterValue(filters.signed_date_from)) count++;
    if (hasFilterValue(filters.signed_date_to)) count++;
    if (hasFilterValue(filters.actor_id)) count++;
    if (hasFilterValue(filters.missing_documents)) count++;

    return count;
});

const hasActiveReportFilters = computed(() => {
    return hasFilterValue(filters.search) || hasFilterValue(filters.action_type) || hasFilterValue(filters.from_semester_id) || hasFilterValue(filters.egc_defer_from_block_number) || advancedFilterCount.value > 0;
});

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
        accessorKey: 'from_semester.name',
        header: 'From Semester',
        cell: ({ row }) => row.original.from_semester?.name ?? '-',
    },
    {
        accessorKey: 'egc_defer_from_block_number',
        header: 'EGC Block',
        cell: ({ row }) => (row.original.egc_defer_from_block_number ? `Block ${row.original.egc_defer_from_block_number}` : '-'),
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
        accessorKey: 'decision_number',
        header: 'Decision No.',
        cell: ({ row }) => row.original.decision_number ?? '-',
    },
    {
        accessorKey: 'decision_signed_at',
        header: 'Decision Signed At',
        cell: ({ row }) => formatDateOnly(row.original.decision_signed_at),
    },
    {
        accessorKey: 'decision_signer',
        header: 'Decision Signer',
        cell: ({ row }) => row.original.decision_signer ?? '-',
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
            const hasDocuments = (row.original.attachments?.length ?? 0) > 0;
            const isMissingDocuments = row.original.missing_documents && !hasDocuments;

            if (isMissingDocuments) {
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
            <div class="flex items-center gap-2">
                <Link :href="studentRoutes.studentStatusActionImport()">
                    <Button variant="outline">
                        <Upload class="mr-2 h-4 w-4" />
                        Import Excel
                    </Button>
                </Link>
                <Button variant="outline" @click="handleExport" :disabled="isExporting">
                    <Loader2 v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
                    <Download v-else class="mr-2 h-4 w-4" />
                    Export Excel
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Action Logs</CardTitle>
                <CardDescription> Complete audit trail of all student administrative actions. Use filters to find specific records. </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="mb-4 flex flex-col gap-3 xl:flex-row xl:items-start">
                    <div class="grid flex-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                            <Input placeholder="Search student..." class="pl-8" :model-value="filters.search" @update:model-value="handleSearch" />
                        </div>

                        <Select :model-value="filters.action_type ?? 'all'" @update:model-value="(v) => (filters.action_type = v === 'all' ? null : v)">
                            <SelectTrigger>
                                <SelectValue placeholder="Action Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Action Types</SelectItem>
                                <SelectItem v-for="actionType in options.actionTypes" :key="actionType.value" :value="actionType.value">
                                    {{ actionType.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select :model-value="filters.from_semester_id ? String(filters.from_semester_id) : 'all'" @update:model-value="(v) => (filters.from_semester_id = v === 'all' ? null : Number(v))">
                            <SelectTrigger>
                                <SelectValue placeholder="From Semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All From Semesters</SelectItem>
                                <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="String(sem.id)">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select :model-value="filters.egc_defer_from_block_number ? String(filters.egc_defer_from_block_number) : 'all'" @update:model-value="(v) => (filters.egc_defer_from_block_number = v === 'all' ? null : Number(v))">
                            <SelectTrigger>
                                <SelectValue placeholder="EGC From Block" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All EGC Blocks</SelectItem>
                                <SelectItem v-for="block in options.egcDeferBlocks" :key="block.value" :value="String(block.value)">
                                    {{ block.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button variant="outline" class="gap-2" @click="showAdvancedFilters = true">
                            <Filter class="h-4 w-4" />
                            Advanced Filters
                            <Badge v-if="advancedFilterCount" variant="secondary">{{ advancedFilterCount }}</Badge>
                        </Button>
                        <Button v-if="hasActiveReportFilters" variant="ghost" size="sm" class="h-9 px-2 lg:px-3" @click="clearFilters">
                            Reset
                            <X class="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <Sheet v-model:open="showAdvancedFilters">
                    <SheetContent class="w-full overflow-y-auto sm:max-w-xl">
                        <SheetHeader class="px-6 pt-6">
                            <SheetTitle>Advanced Filters</SheetTitle>
                            <SheetDescription>Use additional report filters without crowding the main toolbar.</SheetDescription>
                        </SheetHeader>

                        <div class="grid gap-5 px-6 pb-6">
                            <section class="space-y-3">
                                <h3 class="text-sm font-medium">Date Filters</h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="text-muted-foreground mb-1 block text-xs">Changed From</label>
                                        <Input type="date" v-model="filters.date_from" />
                                    </div>
                                    <div>
                                        <label class="text-muted-foreground mb-1 block text-xs">Changed To</label>
                                        <Input type="date" v-model="filters.date_to" />
                                    </div>
                                    <div>
                                        <label class="text-muted-foreground mb-1 block text-xs">Signed From</label>
                                        <Input type="date" v-model="filters.signed_date_from" />
                                    </div>
                                    <div>
                                        <label class="text-muted-foreground mb-1 block text-xs">Signed To</label>
                                        <Input type="date" v-model="filters.signed_date_to" />
                                    </div>
                                </div>
                            </section>

                            <section class="space-y-3">
                                <h3 class="text-sm font-medium">Audit Fields</h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <Select :model-value="filters.actor_id ? String(filters.actor_id) : 'all'" @update:model-value="(v) => (filters.actor_id = v === 'all' ? null : Number(v))">
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

                                    <Select :model-value="filters.missing_documents ?? 'all'" @update:model-value="(v) => (filters.missing_documents = v === 'all' ? null : v)">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Missing Documents" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Document States</SelectItem>
                                            <SelectItem value="true">Missing Documents</SelectItem>
                                            <SelectItem value="false">Complete Documents</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </section>
                        </div>

                        <SheetFooter class="mt-auto border-t px-6 py-4 sm:flex-row sm:justify-between">
                            <Button variant="ghost" type="button" @click="clearFilters">Reset all</Button>
                            <Button type="button" @click="showAdvancedFilters = false">Done</Button>
                        </SheetFooter>
                    </SheetContent>
                </Sheet>

                <DataTable :data="actionLogs.data" :columns="columns" :loading="false" empty-message="No action logs found matching your filters." />

                <div class="mt-4">
                    <DataPagination :pagination-data="actionLogs" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
