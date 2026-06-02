<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { CalendarClock, Filter, MoreHorizontal, Plus, Power, XCircle } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { route } from 'ziggy-js';

// Define Run interface locally or import
interface FormRun {
    id: number;
    form_id: number;
    form_version_id?: number | null;
    form: { id: number; code?: string | null; title: string; type: string; status?: string };
    form_version?: { id: number; version_no?: number | null; is_published?: boolean };
    scope_type: string;
    scope_id?: number | string;
    semester?: { name: string };
    semester_id?: string; // from migration string
    start_at: string;
    end_at?: string;
    status: string; // draft, active, closed
    is_mandatory: boolean;
    scope?: any;
    created_at: string;
}

interface Props {
    runs: PaginatedResponse<FormRun>;
    filters: {
        scope_type?: string;
        status?: string;
        created_from?: string | null;
        created_to?: string | null;
        per_page?: number;
    };
}

const props = defineProps<Props>();

// State
const selectedScope = ref(props.filters.scope_type || 'all');
const selectedStatus = ref(props.filters.status || 'all');
const createdFrom = ref(props.filters.created_from || '');
const createdTo = ref(props.filters.created_to || '');
const selectedPerPage = ref(props.filters.per_page || 15);

// Methods
const applyFilters = (options: { resetPage?: boolean } = {}) => {
    router.get(
        route('forms.admin.runs.index'),
        {
            scope_type: selectedScope.value === 'all' ? undefined : selectedScope.value,
            status: selectedStatus.value === 'all' ? undefined : selectedStatus.value,
            created_from: createdFrom.value || undefined,
            created_to: createdTo.value || undefined,
            per_page: selectedPerPage.value,
            page: options.resetPage ? undefined : props.runs.current_page,
        },
        { preserveState: true, replace: true }
    );
};

const clearFilters = () => {
    selectedScope.value = 'all';
    selectedStatus.value = 'all';
    createdFrom.value = '';
    createdTo.value = '';
    selectedPerPage.value = 15;
    applyFilters({ resetPage: true });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        replace: true,
    });
};

const handlePageSizeChange = (pageSize: number) => {
    selectedPerPage.value = pageSize;
    applyFilters({ resetPage: true });
};

const handleAction = (action: string, run: FormRun) => {
    switch (action) {
        case 'activate':
            if (confirm('Activate this run?')) {
                router.post(route('forms.admin.runs.activate', run.id));
            }
            break;
        case 'close':
            if (confirm('Close this run? It will no longer accept submissions.')) {
                router.post(route('forms.admin.runs.close', run.id));
            }
            break;
    }
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active': return 'default'; // primary
        case 'draft': return 'secondary';
        case 'closed': return 'outline';
        default: return 'secondary';
    }
};

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

// Columns
const columns: ColumnDef<FormRun>[] = [
    {
        id: 'run',
        header: 'Run',
        cell: ({ row }) => {
            const run = row.original;
            return h('div', { class: 'flex flex-col gap-1' }, [
                h('div', { class: 'flex items-center gap-2' }, [
                    h('span', { class: 'font-semibold' }, `Run #${run.id}`),
                    h(Badge, { variant: run.is_mandatory ? 'destructive' : 'outline', class: 'w-fit' }, () => run.is_mandatory ? 'Mandatory' : 'Optional'),
                ]),
                h('span', { class: 'text-xs text-muted-foreground' }, `Created ${formatDateTime(run.created_at)}`),
            ]);
        },
    },
    {
        id: 'form_template',
        header: 'Form Template',
        cell: ({ row }) => {
            const run = row.original;
            const versionLabel = run.form_version?.version_no ? `v${run.form_version.version_no}` : `version #${run.form_version_id ?? 'N/A'}`;

            return h('div', { class: 'flex min-w-64 flex-col gap-1' }, [
                h('div', { class: 'flex items-center gap-2' }, [
                    h('span', { class: 'font-medium text-primary' }, run.form.title),
                    h(Badge, { variant: 'secondary', class: 'w-fit capitalize' }, () => run.form.type),
                ]),
                h('div', { class: 'flex flex-wrap items-center gap-2 text-xs text-muted-foreground' }, [
                    h('span', run.form.code || `Form #${run.form_id}`),
                    h('span', '•'),
                    h('span', versionLabel),
                ]),
            ]);
        },
    },
    {
        accessorKey: 'scope_type',
        header: 'Context',
        cell: ({ row }) => {
            const run = row.original;
            const scopeType = run.scope_type;
            const scopeData = run.scope;
            
            let detail = '';
            
            if (scopeType === 'semester') {
                detail = scopeData?.name || run.semester?.name || run.scope_id || '';
            } else if (scopeType === 'course') {
                const unitCode = scopeData?.unit?.code || '';
                const unitName = scopeData?.unit?.name || '';
                const section = scopeData?.section_code || '';
                detail = unitCode ? `${unitCode} - ${unitName} [${section}]` : `ID: ${run.scope_id}`;
            } else if (scopeType === 'department') {
                detail = scopeData?.name || `ID: ${run.scope_id}`;
                if (run.semester?.name) {
                    detail += ` (${run.semester.name})`;
                }
            } else if (scopeType === 'global') {
                detail = 'All Campus';
            }

            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'capitalize font-medium text-xs text-muted-foreground' }, scopeType),
                h('span', { class: 'text-sm' }, detail)
            ]);
        }
    },
    {
        accessorKey: 'start_at',
        header: 'Time Window',
        cell: ({ row }) => {
            const start = formatDateTime(row.original.start_at);
            const end = row.original.end_at ? formatDateTime(row.original.end_at) : 'Forever';
            return h('div', { class: 'text-xs text-muted-foreground' }, [
                h('div', { class: 'font-medium text-foreground' }, start),
                h('div', `to ${end}`),
            ]);
        }
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => h(Badge, { variant: getStatusVariant(row.original.status) }, () => row.original.status.toUpperCase())
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            return h(DropdownMenu, {}, () => [
                h(DropdownMenuTrigger, { asChild: true }, () => 
                    h(Button, { variant: 'ghost', size: 'sm' }, () => h(MoreHorizontal, { class: 'h-4 w-4' }))
                ),
                h(DropdownMenuContent, { align: 'end' }, () => [
                    row.original.status === 'draft' ? h(DropdownMenuItem, { onClick: () => handleAction('activate', row.original) }, () => [h(Power, { class: 'mr-2 h-4 w-4' }), 'Activate']) : null,
                    row.original.status === 'active' ? h(DropdownMenuItem, { onClick: () => handleAction('close', row.original) }, () => [h(XCircle, { class: 'mr-2 h-4 w-4 text-destructive' }), 'Close']) : null,
                ])
            ]);
        }
    }
];

</script>

<template>
    <Head title="Form Runs" />
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Form Runs</h1>
                <p class="text-muted-foreground">Manage active surveys and query campaigns.</p>
            </div>
            <Link :href="route('forms.admin.runs.create')">
                <Button>
                    <Plus class="mr-2 h-4 w-4" /> New Run
                </Button>
            </Link>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2"><Filter class="h-4 w-4"/> Filters</CardTitle>
                <CardDescription>Filter run history by audience, status, and creation time.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-4">
                    <div class="w-48 space-y-2">
                        <label class="text-sm font-medium">Context</label>
                        <Select v-model="selectedScope" @update:model-value="applyFilters({ resetPage: true })">
                             <SelectTrigger><SelectValue placeholder="All" /></SelectTrigger>
                             <SelectContent>
                                 <SelectItem value="all">All</SelectItem>
                                 <SelectItem value="global">Global</SelectItem>
                                 <SelectItem value="course">Course</SelectItem>
                                 <SelectItem value="semester">Semester</SelectItem>
                                 <SelectItem value="department">Department</SelectItem>
                             </SelectContent>
                        </Select>
                    </div>
                    <div class="w-48 space-y-2">
                        <label class="text-sm font-medium">Status</label>
                        <Select v-model="selectedStatus" @update:model-value="applyFilters({ resetPage: true })">
                             <SelectTrigger><SelectValue placeholder="All" /></SelectTrigger>
                             <SelectContent>
                                 <SelectItem value="all">All</SelectItem>
                                 <SelectItem value="draft">Draft</SelectItem>
                                 <SelectItem value="active">Active</SelectItem>
                                 <SelectItem value="closed">Closed</SelectItem>
                             </SelectContent>
                        </Select>
                    </div>
                    <div class="w-56 space-y-2">
                        <label class="flex items-center gap-2 text-sm font-medium">
                            <CalendarClock class="h-4 w-4" />
                            Created From
                        </label>
                        <Input v-model="createdFrom" type="datetime-local" @change="applyFilters({ resetPage: true })" />
                    </div>
                    <div class="w-56 space-y-2">
                        <label class="text-sm font-medium">Created To</label>
                        <Input v-model="createdTo" type="datetime-local" @change="applyFilters({ resetPage: true })" />
                    </div>
                    <div class="pt-7">
                        <Button variant="outline" @click="clearFilters">Clear</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="px-6">
                <DataTable :columns="columns" :data="runs.data" />
                <DataPagination
                    :pagination-data="runs"
                    item-name="runs"
                    @navigate="handlePaginationNavigate"
                    @page-size-change="handlePageSizeChange"
                />
            </CardContent>
        </Card>

    </div>
</template>
