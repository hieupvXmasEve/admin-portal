<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermission } from '@/composables/usePermission';
import { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { h, reactive } from 'vue';
import { route } from 'ziggy-js';
import { Edit, Eye } from 'lucide-vue-next';

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface BillingCycle {
    id: number;
    semester_id: number;
    name: string;
    start_date: string;
    end_date: string;
    due_date: string;
    status: 'draft' | 'active' | 'closed';
    invoices_count: number;
    semester: Semester;
}

interface Props {
    billingCycles: PaginatedResponse<BillingCycle>;
    semesters: Semester[];
    filters: {
        search?: string;
        semester_id?: number | null;
        status?: string;
    };
}

const props = defineProps<Props>();
const { can } = usePermission();

const searchForm = reactive({
    search: props.filters.search || '',
    semester_id: props.filters.semester_id || null,
    status: props.filters.status || 'all',
});

const applyFilters = () => {
    const params = new URLSearchParams();

    if (searchForm.search) params.set('search', searchForm.search);
    if (searchForm.semester_id) params.set('semester_id', searchForm.semester_id.toString());
    if (searchForm.status && searchForm.status !== 'all') params.set('status', searchForm.status);

    const url = `/billing-cycles${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['billingCycles', 'filters'],
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['billingCycles', 'filters'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', pageSize.toString());
    params.delete('page');

    const url = `/billing-cycles?${params.toString()}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['billingCycles', 'filters'],
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'closed':
            return 'secondary';
        case 'draft':
            return 'outline';
        default:
            return 'outline';
    }
};

const columns: ColumnDef<BillingCycle>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
    },
    {
        accessorKey: 'semester',
        header: 'Semester',
        cell: ({ row }) => row.original.semester.name,
    },
    {
        accessorKey: 'start_date',
        header: 'Start Date',
        cell: ({ row }) => formatDate(row.original.start_date),
    },
    {
        accessorKey: 'end_date',
        header: 'End Date',
        cell: ({ row }) => formatDate(row.original.end_date),
    },
    {
        accessorKey: 'due_date',
        header: 'Due Date',
        cell: ({ row }) => formatDate(row.original.due_date),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.status;
            return h(
                Badge,
                {
                    variant: getStatusVariant(status),
                },
                () => status.charAt(0).toUpperCase() + status.slice(1),
            );
        },
    },
    {
        accessorKey: 'invoices_count',
        header: 'Invoices',
        cell: ({ row }) => `${row.original.invoices_count} invoice(s)`,
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            return h('div', { class: 'flex gap-2' }, [
                h(
                    Link,
                    {
                        href: route('billing-cycles.show', row.original.id),
                        class: 'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 hover:bg-accent hover:text-accent-foreground h-9 w-9',
                    },
                    () => h(Eye, { class: 'h-4 w-4' }),
                ),
                h(
                    Link,
                    {
                        href: route('billing-cycles.edit', row.original.id),
                        class: 'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 hover:bg-accent hover:text-accent-foreground h-9 w-9',
                    },
                    () => h(Edit, { class: 'h-4 w-4' }),
                ),
            ]);
        },
    },
];
</script>

<template>
    <Head title="Billing Cycles" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Billing Cycles</h1>
                <p class="text-muted-foreground">Manage billing cycles aligned with academic semesters</p>
            </div>
            <Link v-if="can('create_billing_cycle')" :href="route('billing-cycles.create')">
                <Button>Create Billing Cycle</Button>
            </Link>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="search">Search</Label>
                        <Input id="search" v-model="searchForm.search" placeholder="Search billing cycles..." @input="debouncedSearch" />
                    </div>

                    <div class="space-y-2">
                        <Label for="semester">Semester</Label>
                        <Select v-model="searchForm.semester_id" @update:model-value="applyFilters">
                            <SelectTrigger id="semester">
                                <SelectValue placeholder="All Semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">All Semesters</SelectItem>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="searchForm.status" @update:model-value="applyFilters">
                            <SelectTrigger id="status">
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="closed">Closed</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <DataTable :columns="columns" :data="billingCycles.data" />
                <div class="mt-4">
                    <DataPagination
                        :pagination-data="billingCycles"
                        @navigate="handlePaginationNavigate"
                        @page-size-change="handlePageSizeChange"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
