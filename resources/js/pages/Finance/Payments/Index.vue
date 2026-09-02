<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import LookupRowActions from '@/components/finance/lookup/LookupRowActions.vue';
import SendToBatchBar from '@/components/finance/lookup/SendToBatchBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import { useLookupSelection } from '@/composables/useLookupSelection';
import { usePermission } from '@/composables/usePermission';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, formatDate } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ChevronsUpDown, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

interface PaymentRow {
    id: number;
    amount: number;
    paid_at: string;
    source: string;
    external_ref: string;
    status: string;
    allocated_amount: number;
    unapplied_amount: number;
    student: { id: number; full_name: string; student_id: string } | null;
}

interface PaymentFilters {
    search: string;
    source: string;
    status: string;
    date_range: string[] | null;
    per_page: number;
    sort: 'amount' | 'paid_at' | 'status' | 'student_id' | 'student_name' | 'unapplied_amount' | null;
    direction: 'asc' | 'desc' | null;
}

interface PaymentStats {
    payment_count: number;
    total_paid: number;
    total_applied: number;
    total_unapplied: number;
}

const props = defineProps<{
    items: PaginatedResponse<PaymentRow>;
    stats: PaymentStats;
    filters?: Partial<PaymentFilters>;
}>();

const permission = usePermission();
const sel = useLookupSelection();

const { filters, handleSearch, setFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useDataTable<PaymentFilters>({
    baseUrl: financeRoutes.collect.payments(),
    initialFilters: {
        search: props.filters?.search ?? '',
        source: props.filters?.source ?? 'all',
        status: props.filters?.status ?? 'all',
        date_range: Array.isArray(props.filters?.date_range) ? props.filters.date_range : null,
        per_page: props.filters?.per_page ?? 15,
        sort: (props.filters?.sort as PaymentFilters['sort']) ?? 'paid_at',
        direction: (props.filters?.direction as 'asc' | 'desc') ?? 'desc',
    },
    defaultValues: {
        source: 'all',
        status: 'all',
        per_page: 15,
        sort: 'paid_at',
        direction: 'desc',
    },
    only: ['items', 'stats', 'filters'],
});

const canDng = computed(() => permission.can('create_finance_payments'));

const allRows = computed(() => props.items.data.filter((payment) => payment.student?.id).map((payment) => ({ key: payment.id, studentId: payment.student!.id })));

function toggleSort(column: NonNullable<PaymentFilters['sort']>): void {
    const nextDirection = currentSort.value === column && currentDirection.value === 'asc' ? 'desc' : 'asc';
    handleSortChange(column, nextDirection);
}

function getSortIcon(column: NonNullable<PaymentFilters['sort']>) {
    if (currentSort.value !== column) {
        return ChevronsUpDown;
    }

    return currentDirection.value === 'asc' ? ArrowUp : ArrowDown;
}

function openStudent(row: PaymentRow): void {
    if (!row.student?.id) {
        return;
    }

    router.visit(financeRoutes.students.overview(row.student.id, `payment:${row.id}`));
}

function updateDateRange(index: 0 | 1, value: string | null): void {
    const current = [...(filters.date_range ?? ['', ''])];
    current[index] = value ?? '';
    setFilter('date_range', current[0] || current[1] ? current : null);
}

function getStatusColor(status: string): 'default' | 'destructive' | 'outline' | 'secondary' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        default:
            return 'outline';
    }
}

function getSourceLabel(source: string): string {
    switch (source) {
        case 'import':
            return 'Excel Import';
        case 'manual':
            return 'Manual';
        case 'gateway':
            return 'Gateway';
        case 'bank_transfer':
            return 'Bank Transfer';
        default:
            return source ?? '-';
    }
}

const sortableHeaders: Array<{ key: NonNullable<PaymentFilters['sort']>; label: string; align?: 'left' | 'right' }> = [
    { key: 'paid_at', label: 'Date' },
    { key: 'student_id', label: 'Student ID' },
    { key: 'student_name', label: 'Student' },
    { key: 'amount', label: 'Amount', align: 'right' },
    { key: 'unapplied_amount', label: 'Unallocated', align: 'right' },
];
</script>

<template>
    <Head title="Payments" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Payments</h2>
            <Link :href="financeRoutes.collect.settlement()">
                <Button variant="outline">
                    <Sparkles class="mr-2 h-4 w-4" />
                    Settlement Worklist
                </Button>
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">Tổng đã đóng</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold tabular-nums">{{ formatCurrency(stats.total_paid) }}</div>
                    <p class="text-muted-foreground mt-1 text-xs">{{ stats.payment_count }} payment(s) trong scope hiện tại</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">Đã thanh toán</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold text-emerald-600 tabular-nums">{{ formatCurrency(stats.total_applied) }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">Còn dư</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-semibold text-amber-600 tabular-nums">{{ formatCurrency(stats.total_unapplied) }}</div>
                    <p class="text-muted-foreground mt-1 text-xs">Số tiền chưa được apply</p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 md:grid-cols-5">
            <Input :model-value="filters.search" placeholder="Search student, ref…" @update:model-value="handleSearch" />
            <Select :model-value="filters.source" @update:model-value="(value) => setFilter('source', String(value))">
                <SelectTrigger>
                    <SelectValue placeholder="Source" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Sources</SelectItem>
                    <SelectItem value="import">Excel Import</SelectItem>
                    <SelectItem value="manual">Manual</SelectItem>
                    <SelectItem value="gateway">Gateway</SelectItem>
                    <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                </SelectContent>
            </Select>
            <Select :model-value="filters.status" @update:model-value="(value) => setFilter('status', String(value))">
                <SelectTrigger>
                    <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Statuses</SelectItem>
                    <SelectItem value="completed">Completed</SelectItem>
                    <SelectItem value="pending">Pending</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
            </Select>
            <DatePicker :model-value="(filters.date_range ?? [])[0] ?? ''" placeholder="Từ ngày" @update:model-value="(value) => updateDateRange(0, value)" />
            <DatePicker :model-value="(filters.date_range ?? [])[1] ?? ''" placeholder="Đến ngày" @update:model-value="(value) => updateDateRange(1, value)" />
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="bg-background sticky top-0 z-10 w-8">
                            <Checkbox :model-value="allRows.length > 0 && sel.count.value === allRows.length" @update:model-value="(value) => sel.toggleAll(allRows, !!value)" />
                        </TableHead>
                        <TableHead v-for="header in sortableHeaders" :key="header.key" class="bg-background sticky top-0 z-10 cursor-pointer" :class="header.align === 'right' ? 'text-right' : ''" @click="toggleSort(header.key)">
                            <span class="inline-flex items-center gap-1" :class="header.align === 'right' ? 'ml-auto' : ''">
                                {{ header.label }}
                                <component :is="getSortIcon(header.key)" class="h-4 w-4" />
                            </span>
                        </TableHead>
                        <TableHead class="bg-background sticky top-0 z-10">Ref</TableHead>
                        <TableHead class="bg-background sticky top-0 z-10">Source</TableHead>
                        <TableHead class="bg-background sticky top-0 z-10 cursor-pointer" @click="toggleSort('status')">
                            <span class="inline-flex items-center gap-1">
                                Status
                                <component :is="getSortIcon('status')" class="h-4 w-4" />
                            </span>
                        </TableHead>
                        <TableHead class="bg-background sticky top-0 z-10 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="payment in items.data" :key="payment.id" class="hover:bg-muted/50 cursor-pointer" @click="openStudent(payment)">
                        <TableCell @click.stop>
                            <Checkbox v-if="payment.student?.id" :model-value="sel.isSelected(payment.id)" @update:model-value="() => sel.toggle({ key: payment.id, studentId: payment.student!.id })" />
                        </TableCell>
                        <TableCell>{{ formatDate(payment.paid_at) }}</TableCell>
                        <TableCell class="font-mono text-xs tabular-nums">{{ payment.student?.student_id ?? '-' }}</TableCell>
                        <TableCell>
                            <div v-if="payment.student" class="font-medium">{{ payment.student.full_name }}</div>
                            <span v-else class="text-muted-foreground">-</span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatCurrency(payment.amount) }}</TableCell>
                        <TableCell class="text-right tabular-nums" :class="{ 'font-bold text-emerald-600': payment.unapplied_amount > 0 }">
                            {{ formatCurrency(payment.unapplied_amount) }}
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ payment.external_ref }}</TableCell>
                        <TableCell>
                            <Badge variant="outline">{{ getSourceLabel(payment.source) }}</Badge>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="getStatusColor(payment.status)">{{ payment.status }}</Badge>
                        </TableCell>
                        <TableCell @click.stop>
                            <LookupRowActions v-if="payment.student?.id" :student-id="payment.student.id" :focus="`payment:${payment.id}`" :detail-url="financeRoutes.collect.paymentDetail(payment.id)" />
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="items.data.length === 0" :colspan="10"> No payments found. </TableEmpty>
                </TableBody>
            </Table>
        </div>

        <DataPagination :pagination-data="items" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
        <SendToBatchBar :count="sel.count.value" :can-dng="canDng" @dng="sel.sendToBatch()" @clear="sel.clear" />
    </div>
</template>
