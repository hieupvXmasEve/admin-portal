<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import LookupRowActions from '@/components/finance/lookup/LookupRowActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, getChargeTypeLabel } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Clock, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type BalanceState = 'unpaid' | 'partially_paid' | 'paid' | 'overdue' | 'overpaid' | 'unapplied' | 'invalid';

interface CollectionProgressStudent {
    id: number;
    student_code: string;
    full_name: string;
    status: string;
    status_label: string;
}

interface CollectionProgressRow {
    row_key: string;
    student: CollectionProgressStudent;
    program_code: string | null;
    intake_semester_id: number | null;
    cohort: number | null;
    semester_id: number;
    invoice_count: number;
    fee_types: string[];
    valid: boolean;
    gross: number | null;
    discount: number | null;
    credit: number | null;
    billed: number | null;
    paid: number | null;
    outstanding: number | null;
    overdue: number | null;
    overpaid: number | null;
    unapplied: number;
    has_unapplied: boolean;
    collection_rate: number | null;
    balance_state: BalanceState;
    balance_state_label: string;
    aging_bucket: string;
    aging_bucket_label: string;
    max_days_overdue: number;
    is_lifecycle_exception: boolean;
    settlement_issue_codes: string[];
    drilldowns: { student_360_focus: string | null; lookup_invoice_id: number | null };
}

interface CollectionProgressSummary {
    student_count: number;
    invalid_count: number;
    billed_total: number;
    paid_total: number;
    outstanding_total: number;
    overdue_total: number;
    overpaid_total: number;
    unapplied_total: number;
    collection_rate: number | null;
    unpaid_count: number;
    partially_paid_count: number;
    paid_count: number;
    overdue_count: number;
    overpaid_count: number;
    unapplied_count: number;
    lifecycle_exception_count: number;
}

interface BreakdownItem {
    key: string;
    label: string;
    student_count: number;
    outstanding: number;
    billed?: number;
    paid?: number;
}

interface FilterOption {
    value: string | number;
    label: string;
}

interface CollectionProgressFilters {
    program_id: string | number;
    intake_semester_id: string | number;
    cohort: string | number;
    fee_type: string;
    balance_state: string;
    aging_bucket: string;
    student_status: string;
    search: string;
    per_page: number;
    page: number;
}

interface CollectionProgressPayload {
    rows: PaginatedResponse<CollectionProgressRow>;
    summary: CollectionProgressSummary;
    breakdowns: {
        by_fee_type: BreakdownItem[];
        by_program: BreakdownItem[];
        by_intake: BreakdownItem[];
        by_cohort: BreakdownItem[];
        by_balance_state: BreakdownItem[];
        by_aging_bucket: BreakdownItem[];
        by_lifecycle_exception: BreakdownItem[];
    };
    filters: Partial<CollectionProgressFilters>;
    filter_options: {
        programs: FilterOption[];
        intakes: FilterOption[];
        cohorts: FilterOption[];
        fee_types: FilterOption[];
        balance_states: FilterOption[];
        aging_buckets: FilterOption[];
        student_statuses: FilterOption[];
        semester_id: number | null;
    };
    meta: { semester_id: number | null };
    computed_at: string;
}

const props = defineProps<{ collection_progress: CollectionProgressPayload }>();

const filterProps = props.collection_progress.filters ?? {};
const stringFilter = (value: unknown, fallback = 'all'): string => (typeof value === 'string' && value !== '' ? value : fallback);
const numberFilter = (value: unknown, fallback = 20): number => {
    if (typeof value === 'number') return value;
    if (typeof value === 'string' && value !== '' && !Number.isNaN(Number(value))) return Number(value);

    return fallback;
};

const {
    filters: tableFilters,
    hasActiveFilters,
    isLoading,
    handleSearch,
    handlePaginationNavigate,
    handlePageSizeChange,
    setFilter,
    clearAllFilters,
} = useDataTable<CollectionProgressFilters & { view: string }>({
    baseUrl: financeRoutes.reporting.index({ view: 'collection-progress' }),
    initialFilters: {
        view: 'collection-progress',
        program_id: filterProps.program_id ?? 'all',
        intake_semester_id: filterProps.intake_semester_id ?? 'all',
        cohort: filterProps.cohort ?? 'all',
        fee_type: stringFilter(filterProps.fee_type),
        balance_state: stringFilter(filterProps.balance_state),
        aging_bucket: stringFilter(filterProps.aging_bucket),
        student_status: stringFilter(filterProps.student_status),
        search: stringFilter(filterProps.search, ''),
        per_page: numberFilter(filterProps.per_page),
        page: numberFilter(filterProps.page, 1),
    },
    defaultValues: {
        view: 'collection-progress',
        program_id: 'all',
        intake_semester_id: 'all',
        cohort: 'all',
        fee_type: 'all',
        balance_state: 'all',
        aging_bucket: 'all',
        student_status: 'all',
        search: '',
        per_page: 20,
        page: 1,
    },
    only: ['collection_progress', 'computed_at', 'active_view'],
    immediateFields: ['program_id', 'intake_semester_id', 'cohort', 'fee_type', 'balance_state', 'aging_bucket', 'student_status'],
});

const summary = computed(() => props.collection_progress.summary);

const collectionRatePct = computed(() => (summary.value.collection_rate !== null ? Math.round(summary.value.collection_rate * 100) : null));

const summaryCards = computed(() => [
    { key: 'billed', label: 'Tổng phát sinh', value: summary.value.billed_total, sub: `${summary.value.student_count} sinh viên` },
    { key: 'paid', label: 'Đã thu (cash)', value: summary.value.paid_total, sub: collectionRatePct.value !== null ? `Tỷ lệ thu ${collectionRatePct.value}%` : null },
    { key: 'outstanding', label: 'Còn phải thu', value: summary.value.outstanding_total, sub: `${summary.value.unpaid_count + summary.value.partially_paid_count} SV còn nợ` },
    { key: 'overdue', label: 'Quá hạn', value: summary.value.overdue_total, sub: `${summary.value.overdue_count} SV quá hạn`, accent: 'text-red-600' },
    { key: 'overpaid', label: 'Thanh toán dư', value: summary.value.overpaid_total, sub: `${summary.value.overpaid_count} SV` },
    { key: 'unapplied', label: 'Tiền chưa áp dụng', value: summary.value.unapplied_total, sub: `${summary.value.unapplied_count} SV` },
]);

const balanceStateVariant = (state: BalanceState): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (state) {
        case 'paid':
            return 'default';
        case 'overdue':
        case 'invalid':
            return 'destructive';
        case 'partially_paid':
        case 'overpaid':
            return 'secondary';
        default:
            return 'outline';
    }
};

const breakdownDimensions = [
    { key: 'by_balance_state', label: 'Trạng thái thanh toán' },
    { key: 'by_aging_bucket', label: 'Tuổi nợ / quá hạn' },
    { key: 'by_fee_type', label: 'Loại phí' },
    { key: 'by_program', label: 'Chương trình' },
    { key: 'by_intake', label: 'Intake' },
    { key: 'by_cohort', label: 'Cohort' },
    { key: 'by_lifecycle_exception', label: 'Ngoại lệ vòng đời' },
] as const;

type BreakdownKey = (typeof breakdownDimensions)[number]['key'];

const activeBreakdown = ref<BreakdownKey>('by_balance_state');

const breakdownRows = computed<BreakdownItem[]>(() => props.collection_progress.breakdowns[activeBreakdown.value] ?? []);

const breakdownHasMoney = computed(() => breakdownRows.value.some((item) => item.billed !== undefined));

const feeTypeLabel = (type: string): string => (activeBreakdown.value === 'by_fee_type' ? getChargeTypeLabel(type) : type);

const lookupInvoiceUrl = (row: CollectionProgressRow): string | undefined => {
    if (!row.drilldowns.lookup_invoice_id) {
        return financeRoutes.lookup.invoices();
    }

    return financeRoutes.lookup.invoiceDetail(row.drilldowns.lookup_invoice_id);
};
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
            <Card v-for="card in summaryCards" :key="card.key">
                <CardHeader class="pb-2">
                    <CardDescription>{{ card.label }}</CardDescription>
                    <CardTitle class="text-xl" :class="card.accent">{{ formatCurrency(card.value) }}</CardTitle>
                    <p v-if="card.sub" class="text-muted-foreground text-xs">{{ card.sub }}</p>
                </CardHeader>
            </Card>
        </div>

        <Card>
            <CardHeader class="gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Phân tích theo nhóm</CardTitle>
                    <CardDescription>Số dư còn phải thu theo từng chiều phân tích, trong phạm vi bộ lọc hiện tại.</CardDescription>
                </div>
                <Select :model-value="activeBreakdown" @update:model-value="(value) => (activeBreakdown = value as BreakdownKey)">
                    <SelectTrigger class="w-full sm:w-64"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="dimension in breakdownDimensions" :key="dimension.key" :value="dimension.key">
                            {{ dimension.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </CardHeader>
            <CardContent>
                <div class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nhóm</TableHead>
                                <TableHead class="text-right">Số SV</TableHead>
                                <TableHead v-if="breakdownHasMoney" class="text-right">Phát sinh</TableHead>
                                <TableHead v-if="breakdownHasMoney" class="text-right">Đã thu</TableHead>
                                <TableHead class="text-right">Còn phải thu</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="breakdownRows.length === 0">
                                <TableCell :colspan="breakdownHasMoney ? 5 : 3" class="text-muted-foreground py-6 text-center text-sm">Không có dữ liệu.</TableCell>
                            </TableRow>
                            <TableRow v-for="item in breakdownRows" :key="item.key">
                                <TableCell class="font-medium">{{ feeTypeLabel(item.label) }}</TableCell>
                                <TableCell class="text-right">{{ item.student_count }}</TableCell>
                                <TableCell v-if="breakdownHasMoney" class="text-right">{{ formatCurrency(item.billed ?? 0) }}</TableCell>
                                <TableCell v-if="breakdownHasMoney" class="text-right">{{ formatCurrency(item.paid ?? 0) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(item.outstanding) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Danh sách thu phí</CardTitle>
                    <CardDescription>
                        Grain: sinh viên × số dư học kỳ. Computed at
                        {{ new Date(collection_progress.computed_at).toLocaleString() }}.
                    </CardDescription>
                </div>
                <p class="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                    <Clock class="size-3.5" />
                    {{ summary.student_count }} sinh viên trong phạm vi lọc
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <DebouncedInput :model-value="tableFilters.search" placeholder="Tìm mã SV / tên..." @update:model-value="handleSearch" />
                    <Select :model-value="String(tableFilters.program_id)" @update:model-value="(value) => setFilter('program_id', value)">
                        <SelectTrigger><SelectValue placeholder="Chương trình" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả chương trình</SelectItem>
                            <SelectItem v-for="option in collection_progress.filter_options.programs" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="String(tableFilters.intake_semester_id)" @update:model-value="(value) => setFilter('intake_semester_id', value)">
                        <SelectTrigger><SelectValue placeholder="Intake" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả intake</SelectItem>
                            <SelectItem v-for="option in collection_progress.filter_options.intakes" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="String(tableFilters.cohort)" @update:model-value="(value) => setFilter('cohort', value)">
                        <SelectTrigger><SelectValue placeholder="Cohort" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả cohort</SelectItem>
                            <SelectItem v-for="option in collection_progress.filter_options.cohorts" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.fee_type" @update:model-value="(value) => setFilter('fee_type', value)">
                        <SelectTrigger><SelectValue placeholder="Loại phí" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả loại phí</SelectItem>
                            <SelectItem v-for="option in collection_progress.filter_options.fee_types" :key="option.value" :value="String(option.value)">{{ getChargeTypeLabel(String(option.value)) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.balance_state" @update:model-value="(value) => setFilter('balance_state', value)">
                        <SelectTrigger><SelectValue placeholder="Trạng thái thanh toán" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả trạng thái</SelectItem>
                            <SelectItem v-for="option in collection_progress.filter_options.balance_states" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.aging_bucket" @update:model-value="(value) => setFilter('aging_bucket', value)">
                        <SelectTrigger><SelectValue placeholder="Tuổi nợ" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả tuổi nợ</SelectItem>
                            <SelectItem v-for="option in collection_progress.filter_options.aging_buckets" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.student_status" @update:model-value="(value) => setFilter('student_status', value)">
                        <SelectTrigger><SelectValue placeholder="Trạng thái SV" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả trạng thái SV</SelectItem>
                            <SelectItem v-for="option in collection_progress.filter_options.student_statuses" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="flex items-center gap-2">
                        <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearAllFilters">Xóa bộ lọc</Button>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Sinh viên</TableHead>
                                <TableHead>Chương trình</TableHead>
                                <TableHead>Tình trạng</TableHead>
                                <TableHead class="text-right">Cash / Phát sinh</TableHead>
                                <TableHead class="text-right">Còn phải thu</TableHead>
                                <TableHead class="text-right">Hành động</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="6" class="text-muted-foreground py-8 text-center text-sm">Đang tải...</TableCell>
                            </TableRow>
                            <TableRow v-else-if="collection_progress.rows.data.length === 0">
                                <TableCell colspan="6" class="text-muted-foreground py-8 text-center text-sm">Không có sinh viên nào trong phạm vi lọc hiện tại.</TableCell>
                            </TableRow>
                            <TableRow v-for="row in collection_progress.rows.data" :key="row.row_key">
                                <TableCell>
                                    <div class="font-medium">{{ row.student.full_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ row.student.student_code }} · {{ row.student.status_label }}</div>
                                    <Badge v-if="row.is_lifecycle_exception" variant="outline" class="mt-1 border-amber-300 text-amber-700">Ngoại lệ vòng đời</Badge>
                                </TableCell>
                                <TableCell>
                                    <div>{{ row.program_code ?? '—' }}</div>
                                    <div v-if="row.cohort" class="text-muted-foreground text-xs">Cohort {{ row.cohort }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="balanceStateVariant(row.balance_state)">{{ row.balance_state_label }}</Badge>
                                    <div v-if="!row.valid" class="text-destructive mt-1 text-xs">{{ row.settlement_issue_codes.join(', ') }}</div>
                                    <div v-if="row.aging_bucket !== 'not_due'" class="text-muted-foreground mt-1 text-xs">{{ row.aging_bucket_label }}</div>
                                    <div v-if="row.has_unapplied" class="mt-1 inline-flex items-center gap-1 text-xs text-emerald-700">
                                        <Wallet class="size-3" />
                                        Dư {{ formatCurrency(row.unapplied) }}
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <template v-if="row.valid">
                                        <div class="font-medium">{{ formatCurrency(row.paid ?? 0) }}</div>
                                        <div class="text-muted-foreground text-xs">/ {{ formatCurrency(row.billed ?? 0) }}</div>
                                        <div class="text-muted-foreground text-xs">Giảm {{ formatCurrency(row.discount ?? 0) }} · Credit {{ formatCurrency(row.credit ?? 0) }}</div>
                                    </template>
                                    <span v-else class="text-destructive text-xs">Không hiển thị số tiền tin cậy</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div v-if="row.valid" :class="(row.outstanding ?? 0) > 0 ? 'font-medium' : 'text-muted-foreground'">{{ formatCurrency(row.outstanding ?? 0) }}</div>
                                    <div v-if="row.valid && (row.overdue ?? 0) > 0" class="text-xs text-red-600">Quá hạn {{ formatCurrency(row.overdue ?? 0) }}</div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <LookupRowActions :student-id="row.student.id" :focus="row.drilldowns.student_360_focus ?? undefined" :detail-url="lookupInvoiceUrl(row)" />
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <DataPagination :pagination-data="collection_progress.rows" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
