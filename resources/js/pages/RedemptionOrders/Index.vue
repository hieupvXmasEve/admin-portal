<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Eye } from 'lucide-vue-next';
import { reactive } from 'vue';
import { route } from 'ziggy-js';

type RedemptionOrderStatus =
    | 'pending_review'
    | 'approved'
    | 'ready_for_collection'
    | 'pickup_overdue'
    | 'cancellation_requested'
    | 'collected'
    | 'shipped'
    | 'rejected'
    | 'cancelled';

interface RedemptionOrderListItem {
    id: number;
    code: string;
    status: RedemptionOrderStatus;
    method: 'pickup' | 'shipping';
    total_gold: number;
    items_count: number;
    student: { student_id: string | null; full_name: string | null };
    created_at: string | null;
}

interface Props {
    orders: PaginatedResponse<RedemptionOrderListItem>;
    filters: { status?: string; per_page?: number };
    statuses: RedemptionOrderStatus[];
}

const props = defineProps<Props>();

const searchForm = reactive({
    status: props.filters.status || 'all',
    per_page: props.filters.per_page || 15,
});

const applyFilters = () => {
    router.get(route('redemption-orders.index'), searchForm, {
        preserveState: true,
        replace: true,
    });
};

const statusVariant = (status: RedemptionOrderStatus): 'default' | 'destructive' | 'outline' | 'secondary' | 'success' => {
    const variants: Record<RedemptionOrderStatus, 'default' | 'destructive' | 'outline' | 'secondary' | 'success'> = {
        pending_review: 'secondary',
        approved: 'default',
        ready_for_collection: 'default',
        pickup_overdue: 'destructive',
        cancellation_requested: 'destructive',
        collected: 'success',
        shipped: 'success',
        rejected: 'destructive',
        cancelled: 'outline',
    };
    return variants[status] ?? 'outline';
};

const statusLabel = (status: string) => status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const columns: ColumnDef<RedemptionOrderListItem>[] = [
    { accessorKey: 'code', header: 'Order' },
    { accessorKey: 'student', header: 'Student' },
    { accessorKey: 'status', header: 'Status' },
    { accessorKey: 'method', header: 'Method' },
    { accessorKey: 'total_gold', header: 'Gold' },
    { accessorKey: 'created_at', header: 'Placed' },
    { id: 'actions', header: 'Actions' },
];

const handlePaginationNavigate = (url: string) => {
    router.get(url, {}, { preserveState: true, replace: true });
};
const handlePageSizeChange = (pageSize: number) => {
    searchForm.per_page = pageSize;
    applyFilters();
};
</script>

<template>
    <Head title="Redemption Orders" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Redemption Orders</h2>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="searchForm.status" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem v-for="status in statuses" :key="status" :value="status">
                                    {{ statusLabel(status) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <DataTable :data="orders.data" :columns="columns" empty-message="No redemption orders found.">
            <template #cell-code="{ row }">
                <span class="text-sm font-medium text-gray-900">{{ row.original.code }}</span>
                <span class="block text-xs text-muted-foreground">{{ row.original.items_count }} item(s)</span>
            </template>

            <template #cell-student="{ row }">
                <span class="text-sm">{{ row.original.student.full_name ?? '—' }}</span>
                <span class="block text-xs text-muted-foreground">{{ row.original.student.student_id ?? '—' }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge :variant="statusVariant(row.original.status)">
                    {{ statusLabel(row.original.status) }}
                </Badge>
            </template>

            <template #cell-method="{ row }">
                <span class="text-sm capitalize">{{ row.original.method }}</span>
            </template>

            <template #cell-total_gold="{ row }"> {{ row.original.total_gold }} gold </template>

            <template #cell-created_at="{ row }">
                {{ row.original.created_at ? new Date(row.original.created_at).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' }) : '—' }}
            </template>

            <template #cell-actions="{ row }">
                <Link :href="route('redemption-orders.show', row.original.id)" title="Review order">
                    <Eye class="h-4 w-4" />
                </Link>
            </template>
        </DataTable>

        <DataPagination :pagination-data="orders" item-name="orders" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
