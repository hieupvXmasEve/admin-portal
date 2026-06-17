<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import FilterDateRange from '@/components/filters/FilterDateRange.vue';
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useGlobalConfirmDialog } from '@/composables';
import { useDataTable } from '@/composables/useDataTable';
import { usePermission } from '@/composables/usePermission';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, formatDate } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, ArrowRight, Ban, CheckCircle2, Clock3, Send, XCircle } from 'lucide-vue-next';
import { h } from 'vue';
import { route } from 'ziggy-js';

interface DngPaymentRequestRow {
    id: number;
    created_at: string | null;
    student: {
        id: number;
        student_code: string;
        full_name: string;
        status_label: string;
        status_color: string;
    } | null;
    campus_code: string;
    student_code: string;
    fee_type: string;
    description: string | null;
    semester: {
        id: number;
        name: string;
        code: string;
    } | null;
    due_date: string | null;
    item_id: string;
    amount: number;
    status: string;
    dng_payment_id: string | null;
    dng_transaction_id: string | null;
    payment: {
        id: number;
        status: string;
        amount: number;
        paid_at: string | null;
    } | null;
    webhook_events_count: number;
    latest_webhook: {
        id: number;
        processing_status: string;
        event_type: string;
        is_valid_checksum: boolean;
        created_at: string | null;
    } | null;
}

interface Filters {
    search?: string;
    status?: string;
    has_payment?: string;
    has_webhook?: string;
    created_from?: string;
    created_to?: string;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    per_page?: number;
    page?: number;
}

interface Props {
    items: PaginatedResponse<DngPaymentRequestRow>;
    stats: {
        total: number;
        pending_count: number;
        pushed_count: number;
        paid_uninvoiced_count: number;
        paid_invoiced_count: number;
        failed_count: number;
        cancelled_count: number;
        bridged_count: number;
    };
    filters: Filters;
}

const props = defineProps<Props>();
const permission = usePermission();
const confirmDialog = useGlobalConfirmDialog();

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'pushed_to_dng', label: 'Pushed to DNG' },
    { value: 'paid_uninvoiced', label: 'Paid Uninvoiced' },
    { value: 'paid_invoiced', label: 'Paid Invoiced' },
    { value: 'reconciled', label: 'Reconciled' },
    { value: 'failed', label: 'Failed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'cancel_pushed_to_dng', label: 'Cancel Pushed to DNG' },
];

const yesNoOptions = [
    { value: 'yes', label: 'Yes' },
    { value: 'no', label: 'No' },
];

const { filters, hasActiveFilters, clearAllFilters, setFilter, apply, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useDataTable<Filters>({
    baseUrl: route('finance.dng.payment-requests.index'),
    initialFilters: {
        search: props.filters.search ?? '',
        status: props.filters.status ?? '',
        has_payment: props.filters.has_payment ?? 'all',
        has_webhook: props.filters.has_webhook ?? 'all',
        created_from: props.filters.created_from ?? '',
        created_to: props.filters.created_to ?? '',
        per_page: props.filters.per_page ?? 15,
        sort: typeof props.filters.sort === 'string' ? props.filters.sort : 'created_at',
        direction: (props.filters.direction as 'asc' | 'desc') || 'desc',
    },
    defaultValues: {
        search: '',
        status: '',
        has_payment: 'all',
        has_webhook: 'all',
        created_from: '',
        created_to: '',
        per_page: 15,
        sort: 'created_at',
        direction: 'desc',
    },
    only: ['items', 'stats', 'filters'],
    fieldDebounce: { search: 400 },
    immediateFields: ['status', 'has_payment', 'has_webhook'],
});

const onDateChange = (from: string, to: string) => {
    apply({ created_from: from, created_to: to, page: 1 });
};

const canCancelRequest = (request: DngPaymentRequestRow) => {
    return permission.can('create_finance_payments') && ['pending', 'pushed_to_dng'].includes(request.status);
};

const cancelRequest = (request: DngPaymentRequestRow) => {
    const isPushed = request.status === 'pushed_to_dng';
    const extraNote = isPushed ? ' A reversal call (amount = -1) will be sent to DNG to remove the charge.' : '';
    confirmDialog.showConfirmDialog(
        {
            title: 'Cancel DNG request',
            message: `Cancel DNG payment request #${request.id} for ${request.student?.full_name || request.student_code}? This request will become terminal and cannot be paid later.${extraNote}`,
            confirmText: 'Cancel request',
            cancelText: 'Back',
        },
        {
            onConfirm: () =>
                new Promise<void>((resolve, reject) => {
                    router.post(
                        route('finance.dng.payment-requests.cancel', request.id),
                        {},
                        {
                            preserveScroll: true,
                            only: ['items', 'stats', 'filters'],
                            onSuccess: () => resolve(),
                            onError: (errors) => reject(new Error(Object.values(errors).join(', '))),
                        },
                    );
                }),
        },
    );
};

const getRequestStatusClass = (status: string) => {
    switch (status) {
        case 'failed':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'cancelled':
            return 'bg-slate-100 text-slate-700 border-slate-300';
        case 'cancel_pushed_to_dng':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'reconciled':
        case 'paid_invoiced':
            return 'bg-green-50 text-green-700 border-green-200';
        case 'paid_uninvoiced':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'pushed_to_dng':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};

const getStudentStatusClass = (color: string) => {
    const map: Record<string, string> = {
        red: 'bg-red-50 text-red-700 border-red-200',
        blue: 'bg-blue-50 text-blue-700 border-blue-200',
        yellow: 'bg-yellow-50 text-yellow-700 border-yellow-200',
        indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        green: 'bg-green-50 text-green-700 border-green-200',
        orange: 'bg-orange-50 text-orange-700 border-orange-200',
        gray: 'bg-slate-50 text-slate-700 border-slate-200',
    };
    return map[color] ?? map.gray;
};

const getWebhookStatusClass = (status: string) => {
    switch (status) {
        case 'processed':
            return 'bg-green-50 text-green-700 border-green-200';
        case 'mismatch':
        case 'failed':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'pending':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};

const columns: ColumnDef<DngPaymentRequestRow>[] = [
    {
        accessorKey: 'created_at',
        header: 'Created',
        enableSorting: true,
        cell: ({ row }) => h('span', formatDate(row.original.created_at)),
    },
    {
        id: 'student',
        header: 'Student',
        enableSorting: false,
    },
    {
        accessorKey: 'fee_type',
        header: 'Fee / Item',
        enableSorting: false,
    },
    {
        accessorKey: 'amount',
        header: 'Amount',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'text-right font-medium' }, formatCurrency(row.original.amount)),
    },
    {
        accessorKey: 'status',
        header: 'Request Status',
        enableSorting: true,
    },
    {
        id: 'dng_ids',
        header: 'DNG IDs',
        enableSorting: false,
    },
    {
        id: 'bridged',
        header: 'Bridged Payment',
        enableSorting: false,
    },
    {
        id: 'latest_webhook',
        header: 'Latest Webhook',
        enableSorting: false,
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        enableHiding: false,
    },
];
</script>

<template>
    <div class="space-y-6">
        <Head title="DNG Payment Requests" />

        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link v-if="permission.can('view_finance_payments')" :href="financeRoutes.collect.payments()">
                    <Button variant="outline" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold">DNG Payment Requests</h1>
                    <p class="text-muted-foreground text-sm">Track DNG request lifecycle, bridge state, and latest webhook outcome.</p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Total</CardTitle></CardHeader>
                <CardContent class="text-2xl font-semibold">{{ stats.total }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Pending</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-2 text-2xl font-semibold"><Clock3 class="h-5 w-5 text-amber-600" />{{ stats.pending_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Pushed to DNG</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-2 text-2xl font-semibold"><Send class="h-5 w-5 text-amber-600" />{{ stats.pushed_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Paid Uninvoiced</CardTitle></CardHeader>
                <CardContent class="text-2xl font-semibold text-blue-700">{{ stats.paid_uninvoiced_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Paid Invoiced</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-2 text-2xl font-semibold"><CheckCircle2 class="h-5 w-5 text-green-600" />{{ stats.paid_invoiced_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Failed / Bridged</CardTitle></CardHeader>
                <CardContent class="space-y-1">
                    <div class="flex items-center gap-2 text-lg font-semibold text-red-700"><XCircle class="h-5 w-5" />{{ stats.failed_count }}</div>
                    <div class="text-muted-foreground text-sm">Cancelled {{ stats.cancelled_count }}</div>
                    <div class="text-muted-foreground text-sm">Bridged {{ stats.bridged_count }}</div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <FilterPanel :has-active-filters="hasActiveFilters" :columns="4" @clear="clearAllFilters">
                    <FilterSearchInput :model-value="filters.search ?? ''" placeholder="Search student, item, DNG ID..." :debounce="400" @update:model-value="(value) => setFilter('search', value)" />
                    <FilterSelect :model-value="filters.status ?? ''" :options="statusOptions" placeholder="Request status" all-label="All statuses" @change="(value) => setFilter('status', value)" />
                    <FilterSelect :model-value="filters.has_payment ?? 'all'" :options="yesNoOptions" placeholder="Bridged payment" all-label="All payment states" @change="(value) => setFilter('has_payment', value || 'all')" />
                    <FilterSelect :model-value="filters.has_webhook ?? 'all'" :options="yesNoOptions" placeholder="Webhook received" all-label="All webhook states" @change="(value) => setFilter('has_webhook', value || 'all')" />
                    <FilterDateRange :from-value="filters.created_from ?? ''" :to-value="filters.created_to ?? ''" @change="onDateChange" />
                </FilterPanel>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Requests</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <DataTable :data="items.data" :columns="columns" :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange">
                    <template #cell-student="{ row }">
                        <div class="space-y-1">
                            <div class="font-medium">{{ row.original.student?.full_name || '-' }}</div>
                            <div class="text-muted-foreground text-xs">{{ row.original.student_code }}</div>
                            <div class="text-muted-foreground text-xs">Campus {{ row.original.campus_code }}</div>
                            <Badge v-if="row.original.student?.status_label" variant="outline" :class="getStudentStatusClass(row.original.student.status_color)">
                                {{ row.original.student.status_label }}
                            </Badge>
                        </div>
                    </template>

                    <template #cell-fee_type="{ row }">
                        <div class="space-y-1">
                            <Badge variant="outline">{{ row.original.fee_type }}</Badge>
                            <div class="text-muted-foreground text-xs">{{ row.original.description || 'No description' }}</div>
                            <div class="text-muted-foreground text-xs">
                                {{ row.original.semester ? `${row.original.semester.name} (${row.original.semester.code})` : 'No semester' }}
                                <span v-if="row.original.due_date"> • Due {{ row.original.due_date }}</span>
                            </div>
                            <div class="font-mono text-xs">{{ row.original.item_id }}</div>
                        </div>
                    </template>

                    <template #cell-status="{ row }">
                        <Badge variant="outline" :class="getRequestStatusClass(row.original.status)">{{ row.original.status }}</Badge>
                    </template>

                    <template #cell-dng_ids="{ row }">
                        <div class="space-y-1 text-xs">
                            <div>
                                <span class="text-muted-foreground">Payment:</span> <span class="font-mono">{{ row.original.dng_payment_id || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">Txn:</span> <span class="font-mono">{{ row.original.dng_transaction_id || '-' }}</span>
                            </div>
                        </div>
                    </template>

                    <template #cell-bridged="{ row }">
                        <div v-if="row.original.payment" class="space-y-1">
                            <Link v-if="permission.can('view_finance_payment_details')" :href="financeRoutes.collect.paymentDetail(row.original.payment.id)" class="text-sm font-medium text-blue-600 hover:underline">
                                Payment #{{ row.original.payment.id }}
                            </Link>
                            <div v-else class="text-sm font-medium">Payment #{{ row.original.payment.id }}</div>
                            <div class="text-muted-foreground text-xs">{{ formatCurrency(row.original.payment.amount) }}</div>
                        </div>
                        <Badge v-else variant="outline">Not bridged</Badge>
                    </template>

                    <template #cell-latest_webhook="{ row }">
                        <div v-if="row.original.latest_webhook" class="space-y-1">
                            <Badge variant="outline" :class="getWebhookStatusClass(row.original.latest_webhook.processing_status)">
                                {{ row.original.latest_webhook.processing_status }}
                            </Badge>
                            <div class="text-muted-foreground text-xs">{{ row.original.latest_webhook.event_type }}</div>
                            <div class="text-muted-foreground text-xs">Checksum {{ row.original.latest_webhook.is_valid_checksum ? 'valid' : 'invalid' }}</div>
                            <div class="text-muted-foreground text-xs">{{ formatDate(row.original.latest_webhook.created_at) }}</div>
                        </div>
                        <div v-else class="text-muted-foreground text-sm">No webhook</div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex justify-end gap-2">
                            <Button v-if="canCancelRequest(row.original)" variant="ghost" size="sm" class="text-red-700 hover:text-red-800" @click="cancelRequest(row.original)">
                                Cancel
                                <Ban class="ml-2 h-4 w-4" />
                            </Button>
                            <Link :href="financeRoutes.collect.dngPaymentRequestDetail(row.original.id)">
                                <Button variant="ghost" size="sm">
                                    View
                                    <ArrowRight class="ml-2 h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </template>
                </DataTable>

                <DataPagination :pagination-data="items" item-name="requests" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
