<script setup lang="ts">
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import { createColumns } from '@/lib/table-utils';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, CheckCircle2, Zap } from 'lucide-vue-next';
import { h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface SettlementInvoice {
    id: number;
    invoice_number: string;
    semester_name: string | null;
    status: string;
    due_date: string | null;
    total_amount: number;
    paid_amount: number;
    remaining_amount: number;
}

interface SettlementStudent {
    student_id: number;
    student_code: string;
    student_name: string;
    invoice_count: number;
    overdue_invoice_count: number;
    active_due: number;
    unapplied_balance: number;
    allocated_amount: number;
    total_payments: number;
    net_amount_to_collect: number;
    actionable: boolean;
    invoices: SettlementInvoice[];
}

interface SettlementFilters {
    search?: string;
    readiness?: 'all' | 'ready' | 'no_cash';
    per_page?: number;
    page?: number;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
}

interface Props {
    students: PaginatedResponse<SettlementStudent>;
    summary: {
        students_with_unpaid_invoices: number;
        ready_students: number;
        total_active_due: number;
        total_unapplied_balance: number;
    };
    filters: SettlementFilters;
}

const props = defineProps<Props>();

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);

const readinessOptions = [
    { value: 'ready', label: 'Ready to settle' },
    { value: 'no_cash', label: 'No unapplied cash' },
];

const { filters, hasActiveFilters, clearFilters, applySearch, setFilter, apply, handleSortChange, handlePageChange, handlePageSizeChange, currentSort, currentDirection } = useServerTableQuery<SettlementFilters>({
    baseUrl: route('finance.operations.settlement.index'),
    initialFilters: {
        search: props.filters.search ?? '',
        readiness: props.filters.readiness ?? 'all',
        per_page: props.filters.per_page ?? 15,
        page: props.filters.page ?? 1,
        sort: props.filters.sort ?? 'active_due',
        direction: props.filters.direction ?? 'desc',
    },
    emptyFilters: {
        search: '',
        readiness: 'all',
        per_page: 15,
        page: 1,
        sort: 'active_due',
        direction: 'desc',
    },
    defaultValues: {
        readiness: 'all',
        per_page: 15,
        page: 1,
        sort: 'active_due',
        direction: 'desc',
    },
    only: ['students', 'summary', 'filters'],
});

const selectedStudentIds = ref<number[]>(props.students.data.filter((student) => student.actionable).map((student) => student.student_id));
const isApplying = ref(false);

watch(
    () => props.students.data,
    (students) => {
        selectedStudentIds.value = students.filter((student) => student.actionable).map((student) => student.student_id);
    },
    { deep: true },
);

const toggleStudent = (studentId: number, checked: boolean | 'indeterminate') => {
    if (checked === true) {
        selectedStudentIds.value = [...new Set([...selectedStudentIds.value, studentId])];

        return;
    }

    selectedStudentIds.value = selectedStudentIds.value.filter((id) => id !== studentId);
};

const currentPageActionableIds = () => props.students.data.filter((student) => student.actionable).map((student) => student.student_id);

const toggleAllCurrentPage = (checked: boolean | 'indeterminate') => {
    selectedStudentIds.value = checked === true ? currentPageActionableIds() : [];
};

const applySettlement = (studentIds: number[]) => {
    if (studentIds.length === 0) {
        toast.error('No actionable students selected.');

        return;
    }

    isApplying.value = true;

    router.post(
        route('finance.operations.settlement.apply'),
        {
            priority_order: ['tuition_term', 'egc_level_fee', 'retake_fee', 'manual_fee'],
            student_ids: studentIds,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Settlement completed successfully.');
            },
            onError: () => {
                toast.error('Settlement failed.');
            },
            onFinish: () => {
                isApplying.value = false;
            },
        },
    );
};

const getReadinessBadge = (student: SettlementStudent) => {
    if (student.actionable) {
        return { label: 'Ready', class: 'bg-green-50 text-green-700 border-green-200' };
    }

    return { label: 'No cash', class: 'bg-amber-50 text-amber-700 border-amber-200' };
};

const columns: ColumnDef<SettlementStudent>[] = createColumns<SettlementStudent>([
    {
        id: 'select',
        header: () =>
            h(Checkbox, {
                modelValue: currentPageActionableIds().length > 0 && selectedStudentIds.value.length === currentPageActionableIds().length,
                'onUpdate:modelValue': toggleAllCurrentPage,
                ariaLabel: 'Select current page students',
            }),
        cell: ({ row }) =>
            h(Checkbox, {
                modelValue: selectedStudentIds.value.includes(row.original.student_id),
                disabled: !row.original.actionable,
                'onUpdate:modelValue': (checked: boolean | 'indeterminate') => toggleStudent(row.original.student_id, checked),
                ariaLabel: `Select ${row.original.student_code}`,
            }),
        enableSorting: false,
        enableHiding: false,
    },
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => (props.students.current_page - 1) * props.students.per_page + row.index + 1,
    },
    {
        accessorKey: 'student_code',
        header: 'Student',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'space-y-1' }, [h('div', { class: 'font-medium' }, row.original.student_name), h('div', { class: 'text-muted-foreground text-xs' }, row.original.student_code)]),
    },
    {
        accessorKey: 'invoice_count',
        header: 'Invoices',
        enableSorting: true,
        cell: ({ row }) =>
            h('div', { class: 'space-y-1 text-center' }, [
                h('div', { class: 'font-medium' }, String(row.original.invoice_count)),
                row.original.overdue_invoice_count > 0 ? h('div', { class: 'text-xs text-red-600' }, `${row.original.overdue_invoice_count} overdue`) : null,
            ]),
    },
    {
        accessorKey: 'active_due',
        header: 'Active due',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'text-right font-medium' }, formatCurrency(row.original.active_due)),
    },
    {
        accessorKey: 'unapplied_balance',
        header: 'Unapplied cash',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'text-right font-medium text-blue-600' }, formatCurrency(row.original.unapplied_balance)),
    },
    {
        accessorKey: 'net_amount_to_collect',
        header: 'Need collect',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: `text-right font-medium ${row.original.net_amount_to_collect > 0 ? 'text-red-600' : 'text-green-600'}` }, formatCurrency(row.original.net_amount_to_collect)),
    },
    {
        id: 'invoices',
        header: 'Open invoices',
        enableSorting: false,
        cell: 'invoices',
    },
    {
        id: 'status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => h(Badge, { variant: 'outline', class: getReadinessBadge(row.original).class }, () => getReadinessBadge(row.original).label),
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        enableHiding: false,
        cell: 'actions',
    },
]);
</script>

<template>
    <div class="space-y-6">
        <Head title="Settlement Worklist" />

        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link :href="route('finance.operations.dashboard')">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Settlement Worklist</h1>
                    <p class="text-muted-foreground text-sm">Quản lý student còn invoice chưa thanh toán và chạy settlement theo batch.</p>
                </div>
            </div>
            <Button @click="applySettlement(selectedStudentIds)" :disabled="isApplying || selectedStudentIds.length === 0">
                <Zap class="mr-2 h-4 w-4" />
                Apply selected
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Students with unpaid invoices</CardDescription>
                    <CardTitle class="text-2xl">{{ props.summary.students_with_unpaid_invoices }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Ready to settle</CardDescription>
                    <CardTitle class="text-2xl text-green-600">{{ props.summary.ready_students }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Total active due</CardDescription>
                    <CardTitle class="text-2xl">{{ formatCurrency(props.summary.total_active_due) }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Total unapplied cash</CardDescription>
                    <CardTitle class="text-2xl text-blue-600">{{ formatCurrency(props.summary.total_unapplied_balance) }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Students ready for settlement</CardTitle>
                <CardDescription>Settlement chạy trực tiếp theo rule priority hiện tại, không còn bước preview.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <FilterPanel :has-active-filters="hasActiveFilters" :columns="4" @clear="clearFilters">
                    <FilterSearchInput :model-value="filters.search ?? ''" placeholder="Search student or invoice..." @update:model-value="(value) => setFilter('search', value)" @search="applySearch" />
                    <FilterSelect
                        :model-value="filters.readiness ?? 'all'"
                        :options="readinessOptions"
                        placeholder="Readiness"
                        all-label="All unpaid students"
                        @update:model-value="(value) => setFilter('readiness', (value || 'all') as SettlementFilters['readiness'])"
                        @change="() => apply({ readiness: filters.readiness, page: 1 })"
                    />
                </FilterPanel>

                <ServerPaginatedDataTable
                    :data="props.students.data"
                    :columns="columns"
                    :pagination-data="props.students"
                    :initial-sort="currentSort"
                    :initial-direction="currentDirection"
                    item-name="students"
                    empty-message="No unpaid invoice candidates matched the current filter."
                    @sort-change="handleSortChange"
                    @page-change="handlePageChange"
                    @page-size-change="handlePageSizeChange"
                >
                    <template #cell-invoices="{ row }">
                        <div class="min-w-[320px] space-y-2">
                            <div v-for="invoice in row.original.invoices" :key="invoice.id" class="bg-background rounded-md border p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-mono text-sm font-semibold">{{ invoice.invoice_number }}</div>
                                        <div class="text-muted-foreground text-xs">
                                            {{ invoice.semester_name || 'No semester' }}<span v-if="invoice.due_date"> • Due {{ invoice.due_date }}</span>
                                        </div>
                                    </div>
                                    <Badge variant="outline">{{ invoice.status }}</Badge>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <div class="text-muted-foreground">Total</div>
                                        <div class="font-medium">{{ formatCurrency(invoice.total_amount) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-muted-foreground">Paid</div>
                                        <div class="font-medium text-green-600">{{ formatCurrency(invoice.paid_amount) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-muted-foreground">Remaining</div>
                                        <div class="font-medium" :class="invoice.remaining_amount > 0 ? 'text-red-600' : 'text-gray-500'">{{ formatCurrency(invoice.remaining_amount) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Button size="sm" :disabled="isApplying || !row.original.actionable" @click="applySettlement([row.original.student_id])">
                                <CheckCircle2 class="mr-2 h-4 w-4" />
                                Apply
                            </Button>
                        </div>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>
    </div>
</template>
