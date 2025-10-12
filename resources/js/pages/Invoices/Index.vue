<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PaginatedResponse } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { computed, h, onMounted, reactive, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { Eye, FileText } from 'lucide-vue-next';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email?: string;
    phone?: string;
}

interface BillingCycle {
    id: number;
    name: string;
    semester: {
        id: number;
        name: string;
    };
}

interface Invoice {
    id: number;
    invoice_number: string;
    student_id: number;
    billing_cycle_id: number;
    subtotal: number;
    discount_total: number;
    total_amount: number;
    paid_amount: number;
    status: 'draft' | 'pending' | 'paid' | 'overdue' | 'cancelled';
    due_date: string;
    student: Student;
    billing_cycle: BillingCycle;
}

interface Props {
    invoices: PaginatedResponse<Invoice>;
    billingCycles: BillingCycle[];
    filters: {
        search?: string;
        billing_cycle_id?: number | null;
        status?: string;
    };
}

const props = defineProps<Props>();

const page = usePage();
const flash = computed(() => page.props.flash as any);

const searchForm = reactive({
    search: props.filters.search || '',
    billing_cycle_id: props.filters.billing_cycle_id || null,
    status: props.filters.status || 'all',
});

// Show toast notifications
onMounted(() => {
    if (flash.value.success) {
        toast.success(flash.value.success);
    }
    if (flash.value.error) {
        toast.error(flash.value.error);
    }
});

watch(flash, (newFlash) => {
    if (newFlash.success) {
        toast.success(newFlash.success);
    }
    if (newFlash.error) {
        toast.error(newFlash.error);
    }
}, { deep: true });

const applyFilters = () => {
    const params = new URLSearchParams();

    if (searchForm.search) params.set('search', searchForm.search);
    if (searchForm.billing_cycle_id) params.set('billing_cycle_id', searchForm.billing_cycle_id.toString());
    if (searchForm.status && searchForm.status !== 'all') params.set('status', searchForm.status);

    const url = `/invoices${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['invoices', 'filters'],
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['invoices', 'filters'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', pageSize.toString());
    params.delete('page');

    const url = `/invoices?${params.toString()}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['invoices', 'filters'],
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

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'paid':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'overdue':
            return 'destructive';
        case 'draft':
            return 'outline';
        case 'cancelled':
            return 'outline';
        default:
            return 'outline';
    }
};

const columns: ColumnDef<Invoice>[] = [
    {
        accessorKey: 'invoice_number',
        header: 'Invoice #',
    },
    {
        accessorKey: 'student',
        header: 'Student',
        cell: ({ row }) => {
            const student = row.original.student;
            return `${student.student_id} - ${student.full_name}`;
        },
    },
    {
        accessorKey: 'billing_cycle',
        header: 'Billing Cycle',
        cell: ({ row }) => row.original.billing_cycle.name,
    },
    {
        accessorKey: 'total_amount',
        header: 'Total Amount',
        cell: ({ row }) => formatCurrency(row.original.total_amount),
    },
    {
        accessorKey: 'paid_amount',
        header: 'Paid Amount',
        cell: ({ row }) => formatCurrency(row.original.paid_amount),
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
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            return h('div', { class: 'flex gap-2' }, [
                h(
                    Link,
                    {
                        href: route('invoices.show', row.original.id),
                        class: 'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 hover:bg-accent hover:text-accent-foreground h-9 w-9',
                    },
                    () => h(Eye, { class: 'h-4 w-4' }),
                ),
            ]);
        },
    },
];
</script>

<template>
    <Head title="Invoices" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Invoices</h1>
                <p class="text-muted-foreground">Manage student invoices and billing</p>
            </div>
            <Link :href="route('invoices.create')">
                <Button>
                    <FileText class="mr-2 h-4 w-4" />
                    Generate Invoices
                </Button>
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
                        <Input id="search" v-model="searchForm.search" placeholder="Search by invoice # or student..." @input="debouncedSearch" />
                    </div>

                    <div class="space-y-2">
                        <Label for="billing_cycle">Billing Cycle</Label>
                        <Select v-model="searchForm.billing_cycle_id" @update:model-value="applyFilters">
                            <SelectTrigger id="billing_cycle">
                                <SelectValue placeholder="All Billing Cycles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">All Billing Cycles</SelectItem>
                                <SelectItem v-for="cycle in billingCycles" :key="cycle.id" :value="cycle.id">
                                    {{ cycle.name }}
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
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="paid">Paid</SelectItem>
                                <SelectItem value="overdue">Overdue</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <DataTable :columns="columns" :data="invoices.data" />
                <div class="mt-4">
                    <DataPagination
                        :pagination-data="invoices"
                        @navigate="handlePaginationNavigate"
                        @page-size-change="handlePageSizeChange"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
