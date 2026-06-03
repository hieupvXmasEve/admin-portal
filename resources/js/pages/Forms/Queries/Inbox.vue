<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatDateTime } from '@/utils/date';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Building, ChevronRight, Filter, Inbox, MessageSquare } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { route } from 'ziggy-js';

// Interfaces
interface Ticket {
    id: number;
    query_ticket?: { id: number } | null;
    student?: { full_name: string; student_id: string };
    form: { title: string };
    query_status: string;
    assigned_to?: { name: string };
    created_at: string;
}

interface Department {
    id: number;
    name: string;
    code: string;
}

interface Props {
    tickets: {
        data: Ticket[];
        links: any[];
        from: number | null;
        to: number | null;
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        per_page: number;
    };
    filters: { status?: string; department_id?: string | number };
    statusOptions: string[];
    departments: Department[];
}

const props = defineProps<Props>();
const selectedStatus = ref(props.filters.status || 'all');
const selectedDept = ref(props.filters.department_id ? String(props.filters.department_id) : 'all');

const applyFilters = () => {
    router.get(
        route('forms.admin.inbox.index'),
        {
            status: selectedStatus.value === 'all' ? undefined : selectedStatus.value,
            department_id: selectedDept.value === 'all' ? undefined : selectedDept.value,
        },
        { preserveState: true },
    );
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        replace: true,
    });
};

const getStatusVariant = (status: string) => {
    if (!status) return 'secondary';
    switch (status.toLowerCase()) {
        case 'open':
            return 'secondary';
        case 'pending':
            return 'outline';
        case 'answered':
            return 'default';
        case 'closed':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const columns: ColumnDef<Ticket>[] = [
    {
        accessorKey: 'id',
        header: '#',
        cell: ({ row }) => h('span', { class: 'font-mono text-xs text-muted-foreground' }, row.original.query_ticket?.id ? `#${row.original.query_ticket.id}` : '—'),
    },
    {
        accessorKey: 'form.title',
        header: 'Query Type',
        cell: ({ row }) => h('div', { class: 'font-semibold text-sm' }, row.original.form.title),
    },
    {
        accessorKey: 'student',
        header: 'Student',
        cell: ({ row }) =>
            row.original.student
                ? h('div', { class: 'flex-col flex' }, [h('span', { class: 'text-sm font-medium' }, row.original.student.full_name), h('span', { class: 'text-[10px] text-primary font-mono' }, row.original.student.student_id)])
                : h('span', { class: 'text-muted-foreground italic text-sm' }, 'Anonymous'),
    },
    {
        accessorKey: 'assigned_to',
        header: 'Assigned To',
        cell: ({ row }) =>
            row.original.assigned_to
                ? h('div', { class: 'flex items-center gap-2 text-sm' }, [
                      h('div', { class: 'h-6 w-6 rounded-full bg-accent flex items-center justify-center text-[10px] font-bold' }, row.original.assigned_to.name[0]),
                      h('span', row.original.assigned_to.name),
                  ])
                : h('span', { class: 'text-muted-foreground italic text-xs' }, 'Unassigned'),
    },
    {
        accessorKey: 'query_status',
        header: 'Status',
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: getStatusVariant(row.original.query_status),
                    class: 'capitalize text-[10px] px-2 py-0',
                },
                () => row.original.query_status || 'open',
            ),
    },
    {
        accessorKey: 'created_at',
        header: 'Submitted',
        cell: ({ row }) => h('span', { class: 'text-xs text-muted-foreground' }, formatDateTime(row.original.created_at)),
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            return h(Link, { href: route('forms.admin.inbox.show', row.original.id) }, () =>
                h(Button, { variant: 'ghost', size: 'sm', class: 'h-8 px-2' }, () => [h(MessageSquare, { class: 'h-3.5 w-3.5 mr-2' }), h('span', 'View Detail'), h(ChevronRight, { class: 'h-3 w-3 ml-1 opacity-50' })]),
            );
        },
    },
];
</script>

<template>
    <Head title="Support Inbox" />

    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Support Inbox</h1>
                <p class="text-muted-foreground mt-1 text-sm">Manage and respond to student academic queries.</p>
            </div>
        </div>

        <!-- Toolbar / Filters -->
        <Card class="bg-muted/30 border-none shadow-none">
            <CardContent class="flex flex-col items-center gap-4 p-4 sm:flex-row">
                <div class="flex w-full flex-1 flex-col items-start gap-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-2">
                        <Filter class="text-muted-foreground h-4 w-4" />
                        <span class="mr-2 text-sm font-medium">Status:</span>
                        <Select v-model="selectedStatus" @update:model-value="applyFilters">
                            <SelectTrigger class="bg-background h-9 w-[160px]">
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <template v-for="opt in statusOptions" :key="opt">
                                    <SelectItem v-if="opt" :value="opt" class="capitalize">
                                        {{ opt }}
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="departments && departments.length > 0" class="flex items-center gap-2">
                        <Building class="text-muted-foreground h-4 w-4" />
                        <span class="mr-2 text-sm font-medium">Department:</span>
                        <Select v-model="selectedDept" @update:model-value="applyFilters">
                            <SelectTrigger class="bg-background h-9 w-[200px]">
                                <SelectValue placeholder="All Departments" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Departments</SelectItem>
                                <template v-for="dept in departments" :key="dept.id">
                                    <SelectItem v-if="dept.id" :value="String(dept.id)"> {{ dept.name }} ({{ dept.code }}) </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="text-muted-foreground hidden text-xs italic md:block">Total: {{ tickets.total }} queries</div>
            </CardContent>
        </Card>

        <!-- Main Data Table -->
        <Card>
            <CardContent class="p-0">
                <DataTable :columns="columns" :data="tickets.data" />
                <div v-if="!tickets.data.length" class="text-muted-foreground p-12 text-center">
                    <Inbox class="mx-auto mb-4 h-10 w-10 opacity-20" />
                    <p>No queries found matching your filters.</p>
                </div>
                <DataPagination v-if="tickets.data.length" class="border-t px-4" :pagination-data="tickets" item-name="queries" :show-page-size-selector="false" @navigate="handlePaginationNavigate" />
            </CardContent>
        </Card>
    </div>
</template>
