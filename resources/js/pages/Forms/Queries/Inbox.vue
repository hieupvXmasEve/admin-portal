<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatDateTime } from '@/utils/date';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Inbox, MessageSquare, Filter, Search, ChevronRight } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { route } from 'ziggy-js';

// Interfaces
interface Ticket {
    id: number;
    student?: { full_name: string; student_id: string };
    form: { title: string };
    query_status: string; 
    assigned_to?: { name: string };
    created_at: string;
}

interface Props {
    tickets: { data: Ticket[]; links: any[]; meta?: any };
    filters: { status?: string; };
    statusOptions: string[];
}

const props = defineProps<Props>();
const selectedStatus = ref(props.filters.status || 'all');

const applyFilter = () => {
    router.get(route('forms.admin.inbox.index'), {
        status: selectedStatus.value === 'all' ? undefined : selectedStatus.value
    }, { preserveState: true });
};

const getStatusVariant = (status: string) => {
    if (!status) return 'secondary';
    switch (status.toLowerCase()) {
        case 'open': return 'secondary';
        case 'pending': return 'outline';
        case 'answered': return 'default';
        case 'closed': return 'destructive';
        default: return 'secondary';
    }
};

const columns: ColumnDef<Ticket>[] = [
    {
        accessorKey: 'id',
        header: '#',
        cell: ({ row }) => h('span', { class: 'font-mono text-xs text-muted-foreground' }, `#${row.original.id}`)
    },
    {
        accessorKey: 'form.title',
        header: 'Query Type',
        cell: ({ row }) => h('div', { class: 'font-semibold text-sm' }, row.original.form.title)
    },
    {
        accessorKey: 'student',
        header: 'Student',
        cell: ({ row }) => row.original.student 
            ? h('div', { class: 'flex-col flex' }, [
                h('span', { class: 'text-sm font-medium' }, row.original.student.full_name),
                h('span', { class: 'text-[10px] text-primary font-mono' }, row.original.student.student_id)
              ])
            : h('span', { class: 'text-muted-foreground italic text-sm' }, 'Anonymous')
    },
    {
        accessorKey: 'assigned_to',
        header: 'Assigned To',
        cell: ({ row }) => row.original.assigned_to 
            ? h('div', { class: 'flex items-center gap-2 text-sm' }, [
                h('div', { class: 'h-6 w-6 rounded-full bg-accent flex items-center justify-center text-[10px] font-bold' }, row.original.assigned_to.name[0]),
                h('span', row.original.assigned_to.name)
              ])
            : h('span', { class: 'text-muted-foreground italic text-xs' }, 'Unassigned')
    },
    {
        accessorKey: 'query_status',
        header: 'Status',
        cell: ({ row }) => h(Badge, { 
            variant: getStatusVariant(row.original.query_status),
            class: 'capitalize text-[10px] px-2 py-0'
        }, () => row.original.query_status || 'open')
    },
    {
        accessorKey: 'created_at',
        header: 'Submitted',
        cell: ({ row }) => h('span', { class: 'text-xs text-muted-foreground' }, formatDateTime(row.original.created_at))
    },
    {
        id: 'actions',
        cell: ({ row }) => {
            return h(Link, { href: route('forms.admin.inbox.show', row.original.id) }, () => 
                h(Button, { variant: 'ghost', size: 'sm', class: 'h-8 px-2' }, () => [
                    h(MessageSquare, { class: 'h-3.5 w-3.5 mr-2' }), 
                    h('span', 'View Detail'),
                    h(ChevronRight, { class: 'h-3 w-3 ml-1 opacity-50' })
                ])
            )
        }
    }
];
</script>

<template>
    <Head title="Support Inbox" />
    
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Support Inbox</h1>
                <p class="text-muted-foreground text-sm mt-1">Manage and respond to student academic queries.</p>
            </div>
        </div>

        <!-- Toolbar / Filters -->
        <Card class="bg-muted/30 border-none shadow-none">
            <CardContent class="p-4 flex flex-col sm:flex-row items-center gap-4">
                <div class="flex items-center gap-2 flex-1 w-full">
                    <Filter class="h-4 w-4 text-muted-foreground" />
                    <span class="text-sm font-medium mr-2">Status:</span>
                    <Select v-model="selectedStatus" @update:model-value="applyFilter">
                        <SelectTrigger class="w-full sm:w-[180px] h-9 bg-background">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Requests</SelectItem>
                            <SelectItem v-for="opt in statusOptions" :key="opt" :value="opt" class="capitalize">{{ opt }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="text-xs text-muted-foreground italic hidden md:block">
                    Total: {{ tickets.data.length }} items showing
                </div>
            </CardContent>
        </Card>

        <!-- Main Data Table -->
        <Card>
            <CardContent class="p-0">
                <DataTable :columns="columns" :data="tickets.data" />
                <div v-if="!tickets.data.length" class="p-12 text-center text-muted-foreground">
                    <Inbox class="h-10 w-10 mx-auto mb-4 opacity-20" />
                    <p>No queries found matching your filters.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
