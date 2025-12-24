<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Filter, MoreHorizontal, Plus, Power, XCircle } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { route } from 'ziggy-js';

// Define Run interface locally or import
interface FormRun {
    id: number;
    form: { title: string; type: string };
    scope_type: string;
    scope_id?: number | string;
    semester?: { name: string };
    semester_id?: string; // from migration string
    start_at: string;
    end_at?: string;
    status: string; // draft, active, closed
    is_mandatory: boolean;
}

interface Props {
    runs: { data: FormRun[]; links: any[]; meta?: any }; // Pagination wrapper
    filters: {
        scope_type?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

// State
const selectedScope = ref(props.filters.scope_type || 'all');
const selectedStatus = ref(props.filters.status || 'all');

// Methods
const applyFilters = () => {
    router.get(
        route('forms.admin.runs.index'),
        {
            scope_type: selectedScope.value === 'all' ? undefined : selectedScope.value,
            status: selectedStatus.value === 'all' ? undefined : selectedStatus.value,
        },
        { preserveState: true, replace: true }
    );
};

const clearFilters = () => {
    selectedScope.value = 'all';
    selectedStatus.value = 'all';
    applyFilters();
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

// Columns
const columns: ColumnDef<FormRun>[] = [
    {
        accessorKey: 'form.title',
        header: 'Form',
        cell: ({ row }) => h('div', [
             h('div', { class: 'font-medium' }, row.original.form.title),
             h('span', { class: 'text-xs text-muted-foreground capitalize' }, row.original.form.type)
        ]),
    },
    {
        accessorKey: 'scope_type',
        header: 'Context',
        cell: ({ row }) => {
            const scope = row.original.scope_type;
            let detail = '';
            if (scope === 'semester' && row.original.semester_id) detail = ` (${row.original.semester_id})`; // or lookup name if loaded
            if (scope === 'course') detail = ` (ID: ${row.original.scope_id})`;
            if (scope === 'department') detail = ` (Dept ID: ${row.original.scope_id})`; 
            if (row.original.semester_id && scope === 'department') detail += ` [${row.original.semester_id}]`;

            return h('div', { class: 'capitalize' }, scope + detail);
        }
    },
    {
        accessorKey: 'start_at',
        header: 'Time Window',
        cell: ({ row }) => {
            const start = new Date(row.original.start_at).toLocaleDateString();
            const end = row.original.end_at ? new Date(row.original.end_at).toLocaleDateString() : 'Forever';
            return h('div', { class: 'text-xs' }, `${start} - ${end}`);
        }
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => h(Badge, { variant: getStatusVariant(row.original.status) }, () => row.original.status.toUpperCase())
    },
    {
        accessorKey: 'is_mandatory',
        header: 'Mandatory',
        cell: ({ row }) => row.original.is_mandatory ? h(Badge, { variant: 'destructive' }, () => 'Yes') : h('span', 'No')
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
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-4">
                    <div class="w-48 space-y-2">
                        <label class="text-sm font-medium">Context</label>
                        <Select v-model="selectedScope" @update:model-value="applyFilters">
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
                        <Select v-model="selectedStatus" @update:model-value="applyFilters">
                             <SelectTrigger><SelectValue placeholder="All" /></SelectTrigger>
                             <SelectContent>
                                 <SelectItem value="all">All</SelectItem>
                                 <SelectItem value="draft">Draft</SelectItem>
                                 <SelectItem value="active">Active</SelectItem>
                                 <SelectItem value="closed">Closed</SelectItem>
                             </SelectContent>
                        </Select>
                    </div>
                    <div class="pt-7">
                        <Button variant="outline" @click="clearFilters">Clear</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <DataTable :columns="columns" :data="runs.data" />
            </CardContent>
        </Card>
    </div>
</template>
