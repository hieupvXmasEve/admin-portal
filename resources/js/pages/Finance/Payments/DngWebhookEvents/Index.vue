<script setup lang="ts">
import FilterDateRange from '@/components/filters/FilterDateRange.vue';
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/composables/usePermission';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import type { PaginatedResponse } from '@/types';
import { formatDate } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertTriangle, ArrowLeft, ArrowRight, CheckCircle2, CircleDashed, ShieldAlert } from 'lucide-vue-next';
import { h } from 'vue';
import { route } from 'ziggy-js';

interface DngWebhookEventRow {
    id: number;
    created_at: string | null;
    processed_at: string | null;
    dng_payment_id: string | null;
    event_type: string;
    processing_status: string;
    is_valid_checksum: boolean;
    error_message: string | null;
    linked_request: {
        id: number;
        status: string;
        student: {
            student_code: string;
            full_name: string;
        } | null;
        payment: {
            id: number;
            status: string;
        } | null;
    } | null;
}

interface Filters {
    search?: string;
    processing_status?: string;
    event_type?: string;
    checksum_validity?: string;
    linked_request?: string;
    created_from?: string;
    created_to?: string;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    per_page?: number;
    page?: number;
}

interface Props {
    items: PaginatedResponse<DngWebhookEventRow>;
    stats: {
        total: number;
        pending_count: number;
        processed_count: number;
        mismatch_count: number;
        failed_count: number;
        invalid_checksum_count: number;
        orphan_count: number;
    };
    filters: Filters;
}

const props = defineProps<Props>();
const permission = usePermission();

const statusOptions = [
    { value: 'received', label: 'Received' },
    { value: 'processing', label: 'Processing' },
    { value: 'processed', label: 'Processed' },
    { value: 'skipped', label: 'Skipped' },
    { value: 'mismatch', label: 'Mismatch' },
    { value: 'failed_retryable', label: 'Failed (retryable)' },
    { value: 'failed_terminal', label: 'Failed (terminal)' },
];

const eventTypeOptions = [
    { value: 'payment_succeeded_without_invoice', label: 'Without invoice' },
    { value: 'payment_invoiced', label: 'Payment invoiced' },
];

const checksumOptions = [
    { value: 'valid', label: 'Valid' },
    { value: 'invalid', label: 'Invalid' },
];

const linkedOptions = [
    { value: 'linked', label: 'Linked' },
    { value: 'orphan', label: 'Orphan' },
];

const { filters, hasActiveFilters, clearFilters, applySearch, setFilter, apply, handleSortChange, handlePageChange, handlePageSizeChange, currentSort, currentDirection } = useServerTableQuery<Filters>({
    baseUrl: route('finance.dng.webhook-events.index'),
    initialFilters: {
        search: props.filters.search ?? '',
        processing_status: props.filters.processing_status ?? '',
        event_type: props.filters.event_type ?? '',
        checksum_validity: props.filters.checksum_validity ?? 'all',
        linked_request: props.filters.linked_request ?? 'all',
        created_from: props.filters.created_from ?? '',
        created_to: props.filters.created_to ?? '',
        per_page: props.filters.per_page ?? 15,
        page: props.items.current_page ?? 1,
        sort: props.filters.sort ?? 'created_at',
        direction: props.filters.direction ?? 'desc',
    },
    emptyFilters: {
        search: '',
        processing_status: '',
        event_type: '',
        checksum_validity: 'all',
        linked_request: 'all',
        created_from: '',
        created_to: '',
        per_page: 15,
        page: 1,
        sort: 'created_at',
        direction: 'desc',
    },
    defaultValues: {
        checksum_validity: 'all',
        linked_request: 'all',
        per_page: 15,
        page: 1,
        sort: 'created_at',
        direction: 'desc',
    },
    only: ['items', 'stats', 'filters'],
});

const onDateChange = (from: string, to: string) => {
    apply({ created_from: from, created_to: to, page: 1 });
};

const getStatusClass = (status: string) => {
    switch (status) {
        case 'processed':
            return 'bg-green-50 text-green-700 border-green-200';
        case 'failed_terminal':
        case 'failed_retryable':
        case 'mismatch':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'skipped':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'received':
        case 'processing':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};

const columns: ColumnDef<DngWebhookEventRow>[] = [
    {
        accessorKey: 'created_at',
        header: 'Created',
        enableSorting: true,
        cell: ({ row }) => h('span', formatDate(row.original.created_at)),
    },
    {
        accessorKey: 'dng_payment_id',
        header: 'Payment ID',
        enableSorting: false,
        cell: ({ row }) => h('span', { class: 'font-mono text-xs' }, row.original.dng_payment_id || '-'),
    },
    {
        accessorKey: 'event_type',
        header: 'Event Type',
        enableSorting: true,
    },
    {
        accessorKey: 'processing_status',
        header: 'Processing',
        enableSorting: true,
    },
    {
        id: 'checksum',
        header: 'Checksum',
        enableSorting: false,
    },
    {
        id: 'linked_request',
        header: 'Linked Request',
        enableSorting: false,
    },
    {
        accessorKey: 'processed_at',
        header: 'Processed',
        enableSorting: true,
        cell: ({ row }) => h('span', formatDate(row.original.processed_at)),
    },
    {
        accessorKey: 'error_message',
        header: 'Error',
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
        <Head title="DNG Webhook Events" />

        <div class="flex items-center gap-3">
            <Link v-if="permission.can('view_finance_dng_payment_requests')" :href="financeRoutes.collect.dngPaymentRequests()">
                <Button variant="outline" size="icon">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </Link>
            <div>
                <h1 class="text-2xl font-semibold">DNG Webhook Events</h1>
                <p class="text-muted-foreground text-sm">Inspect checksum validity, matching state, and async processing results.</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Total</CardTitle></CardHeader>
                <CardContent class="text-2xl font-semibold">{{ stats.total }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Pending</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-2 text-2xl font-semibold"><CircleDashed class="h-5 w-5 text-amber-600" />{{ stats.pending_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Processed</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-2 text-2xl font-semibold"><CheckCircle2 class="h-5 w-5 text-green-600" />{{ stats.processed_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Mismatch / Orphan</CardTitle></CardHeader>
                <CardContent class="text-2xl font-semibold text-red-700">{{ stats.mismatch_count + stats.orphan_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Invalid Checksum</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-2 text-2xl font-semibold"><ShieldAlert class="h-5 w-5 text-red-600" />{{ stats.invalid_checksum_count }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="text-sm">Failed</CardTitle></CardHeader>
                <CardContent class="flex items-center gap-2 text-2xl font-semibold"><AlertTriangle class="h-5 w-5 text-red-600" />{{ stats.failed_count }}</CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader><CardTitle>Filters</CardTitle></CardHeader>
            <CardContent>
                <FilterPanel :has-active-filters="hasActiveFilters" :columns="4" @clear="clearFilters">
                    <FilterSearchInput :model-value="filters.search ?? ''" placeholder="Search payment ID, hash, student, item..." @update:model-value="(value) => setFilter('search', value)" @search="applySearch" />
                    <FilterSelect :model-value="filters.processing_status ?? ''" :options="statusOptions" placeholder="Processing status" all-label="All processing states" @change="(value) => apply({ processing_status: value, page: 1 })" />
                    <FilterSelect :model-value="filters.event_type ?? ''" :options="eventTypeOptions" placeholder="Event type" all-label="All event types" @change="(value) => apply({ event_type: value, page: 1 })" />
                    <FilterSelect :model-value="filters.checksum_validity ?? 'all'" :options="checksumOptions" placeholder="Checksum" all-label="All checksum states" @change="(value) => apply({ checksum_validity: value || 'all', page: 1 })" />
                    <FilterSelect :model-value="filters.linked_request ?? 'all'" :options="linkedOptions" placeholder="Linked request" all-label="All linkage states" @change="(value) => apply({ linked_request: value || 'all', page: 1 })" />
                    <FilterDateRange :from-value="filters.created_from ?? ''" :to-value="filters.created_to ?? ''" @change="onDateChange" />
                </FilterPanel>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Webhook Events</CardTitle></CardHeader>
            <CardContent>
                <ServerPaginatedDataTable
                    :data="items.data"
                    :columns="columns"
                    :pagination-data="items"
                    :initial-sort="currentSort"
                    :initial-direction="currentDirection"
                    item-name="events"
                    @sort-change="handleSortChange"
                    @page-change="handlePageChange"
                    @page-size-change="handlePageSizeChange"
                >
                    <template #cell-processing_status="{ row }">
                        <Badge variant="outline" :class="getStatusClass(row.original.processing_status)">{{ row.original.processing_status }}</Badge>
                    </template>

                    <template #cell-checksum="{ row }">
                        <Badge variant="outline" :class="row.original.is_valid_checksum ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'">
                            {{ row.original.is_valid_checksum ? 'valid' : 'invalid' }}
                        </Badge>
                    </template>

                    <template #cell-linked_request="{ row }">
                        <div v-if="row.original.linked_request" class="space-y-1">
                            <Link v-if="permission.can('view_finance_dng_payment_requests')" :href="financeRoutes.collect.dngPaymentRequestDetail(row.original.linked_request.id)" class="text-sm font-medium text-blue-600 hover:underline">
                                Request #{{ row.original.linked_request.id }}
                            </Link>
                            <div v-else class="text-sm font-medium">Request #{{ row.original.linked_request.id }}</div>
                            <div class="text-muted-foreground text-xs">{{ row.original.linked_request.student?.student_code || '-' }}</div>
                        </div>
                        <Badge v-else variant="outline" class="border-red-200 bg-red-50 text-red-700">Orphan</Badge>
                    </template>

                    <template #cell-error_message="{ row }">
                        <div class="max-w-[16rem] truncate text-sm">{{ row.original.error_message || '-' }}</div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex justify-end">
                            <Link :href="route('finance.dng.webhook-events.show', row.original.id)">
                                <Button variant="ghost" size="sm">
                                    View
                                    <ArrowRight class="ml-2 h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>
    </div>
</template>
