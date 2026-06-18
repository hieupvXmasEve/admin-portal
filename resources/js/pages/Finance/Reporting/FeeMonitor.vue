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
import { CHARGE_TYPE_LABELS, formatCurrency } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, Clock } from 'lucide-vue-next';
import { computed } from 'vue';

interface FeeMonitorStudent {
    id: number;
    student_code: string;
    full_name: string;
    status: string;
    status_label: string;
}

interface FeeMonitorRow {
    row_key: string;
    student: FeeMonitorStudent;
    program_code: string | null;
    intake_semester_id: number | null;
    cohort: number | null;
    expected_source: string;
    expected_fee_type: string;
    expected_fee_type_label: string;
    semester_id: number;
    generation_state: 'missing' | 'generated' | 'skipped' | 'voided' | 'blocked';
    generation_reason: string | null;
    payment_state: 'paid' | 'partially_paid' | 'outstanding' | null;
    amount: number | null;
    paid_amount: number;
    outstanding_amount: number | null;
    finance_charge_id: number | null;
    batch_handoff: { label: string; fee_category: string; fee_type?: string } | null;
    drilldowns: {
        student_360_focus: string | null;
        lookup_charge_id: number | null;
    };
}

interface FeeMonitorSummary {
    missing_count: number;
    generated_count: number;
    skipped_count: number;
    voided_count: number;
    blocked_count: number;
    paid_count: number;
    partially_paid_count: number;
    outstanding_count: number;
    total_count: number;
}

interface FilterOption {
    value: string | number;
    label: string;
}

interface FeeMonitorFilters {
    program_id: string | number;
    intake_semester_id: string | number;
    cohort: string | number;
    expected_fee_type: string;
    generation_state: string;
    payment_state: string;
    student_status: string;
    search: string;
    per_page: number;
    page: number;
}

interface FeeMonitorPayload {
    rows: PaginatedResponse<FeeMonitorRow>;
    summary: FeeMonitorSummary;
    filters: Partial<FeeMonitorFilters>;
    filter_options: {
        programs: FilterOption[];
        intakes: FilterOption[];
        cohorts: FilterOption[];
        expected_fee_types: FilterOption[];
        generation_states: FilterOption[];
        student_statuses: FilterOption[];
        semester_id: number | null;
    };
    meta: {
        semester_id: number | null;
        acad_ret_gate: {
            missing_inference_enabled: boolean;
            excluded_missing_sources: string[];
        };
    };
    computed_at: string;
    permissions: {
        can_batch_handoff: boolean;
    };
}

const props = defineProps<{
    fee_monitor: FeeMonitorPayload;
}>();

const filterProps = props.fee_monitor.filters ?? {};
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
} = useDataTable<FeeMonitorFilters & { view: string }>({
    baseUrl: financeRoutes.reporting.index({ view: 'fee-monitor' }),
    initialFilters: {
        view: 'fee-monitor',
        program_id: filterProps.program_id ?? 'all',
        intake_semester_id: filterProps.intake_semester_id ?? 'all',
        cohort: filterProps.cohort ?? 'all',
        expected_fee_type: stringFilter(filterProps.expected_fee_type),
        generation_state: stringFilter(filterProps.generation_state),
        payment_state: stringFilter(filterProps.payment_state),
        student_status: stringFilter(filterProps.student_status),
        search: stringFilter(filterProps.search, ''),
        per_page: numberFilter(filterProps.per_page),
        page: numberFilter(filterProps.page, 1),
    },
    defaultValues: {
        view: 'fee-monitor',
        program_id: 'all',
        intake_semester_id: 'all',
        cohort: 'all',
        expected_fee_type: 'all',
        generation_state: 'all',
        payment_state: 'all',
        student_status: 'all',
        search: '',
        per_page: 20,
        page: 1,
    },
    only: ['fee_monitor', 'computed_at', 'active_view'],
    immediateFields: ['program_id', 'intake_semester_id', 'cohort', 'expected_fee_type', 'generation_state', 'payment_state', 'student_status'],
});

const generationStateVariant = (state: FeeMonitorRow['generation_state']) => {
    switch (state) {
        case 'missing':
            return 'destructive';
        case 'blocked':
            return 'secondary';
        case 'generated':
            return 'default';
        case 'voided':
            return 'outline';
        default:
            return 'outline';
    }
};

const generationStateLabel: Record<FeeMonitorRow['generation_state'], string> = {
    missing: 'Thiếu phí',
    generated: 'Đã sinh phí',
    skipped: 'Bỏ qua',
    voided: 'Đã void',
    blocked: 'Bị chặn',
};

const paymentStateLabel: Record<NonNullable<FeeMonitorRow['payment_state']>, string> = {
    paid: 'Đã thanh toán',
    partially_paid: 'Thanh toán một phần',
    outstanding: 'Còn nợ',
};

const summaryCards = computed(() => [
    { key: 'missing', label: 'Thiếu phí', value: props.fee_monitor.summary.missing_count },
    { key: 'generated', label: 'Đã sinh phí', value: props.fee_monitor.summary.generated_count },
    { key: 'blocked', label: 'Bị chặn', value: props.fee_monitor.summary.blocked_count },
    { key: 'skipped', label: 'Bỏ qua', value: props.fee_monitor.summary.skipped_count },
    { key: 'voided', label: 'Đã void', value: props.fee_monitor.summary.voided_count },
    { key: 'outstanding', label: 'Còn nợ', value: props.fee_monitor.summary.outstanding_count },
    { key: 'partially_paid', label: 'TT một phần', value: props.fee_monitor.summary.partially_paid_count },
    { key: 'paid', label: 'Đã thanh toán', value: props.fee_monitor.summary.paid_count },
]);

const batchHandoffUrl = (row: FeeMonitorRow): string | null => {
    if (!row.batch_handoff || !props.fee_monitor.permissions.can_batch_handoff) {
        return null;
    }

    const params: Record<string, unknown> = {
        fee_category: row.batch_handoff.fee_category,
        semester_id: row.semester_id,
        search: row.student.student_code,
    };

    if (row.batch_handoff.fee_type) {
        params.fee_type = row.batch_handoff.fee_type;
    }

    return financeRoutes.batchStudio.charges(params);
};

const lookupDetailUrl = (row: FeeMonitorRow): string | undefined => {
    if (!row.drilldowns.lookup_charge_id) {
        return financeRoutes.audit();
    }

    return financeRoutes.lookup.chargeDetail(row.drilldowns.lookup_charge_id);
};

const acadRetGateActive = computed(() => !props.fee_monitor.meta.acad_ret_gate.missing_inference_enabled);
</script>

<template>
    <div class="space-y-4">
        <div v-if="acadRetGateActive" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <p>
                Phí học lại / thi lại chỉ hiển thị các khoản đã có charge. Suy luận "thiếu phí" cho retake/resit đang chờ
                <code>ACAD-RET-001</code>.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="card in summaryCards" :key="card.key">
                <CardHeader class="pb-2">
                    <CardDescription>{{ card.label }}</CardDescription>
                    <CardTitle class="text-2xl">{{ card.value }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card>
            <CardHeader class="gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Fee completeness worklist</CardTitle>
                    <CardDescription>
                        Grain: student × expected fee type × semester. Computed at
                        {{ new Date(fee_monitor.computed_at).toLocaleString() }}.
                    </CardDescription>
                </div>
                <p class="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                    <Clock class="size-3.5" />
                    {{ fee_monitor.summary.total_count }} rows in current filter scope
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <DebouncedInput :model-value="tableFilters.search" placeholder="Tìm mã SV / tên..." @update:model-value="handleSearch" />
                    <Select :model-value="String(tableFilters.program_id)" @update:model-value="(value) => setFilter('program_id', value)">
                        <SelectTrigger><SelectValue placeholder="Chương trình" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả chương trình</SelectItem>
                            <SelectItem v-for="option in fee_monitor.filter_options.programs" :key="option.value" :value="String(option.value)">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="String(tableFilters.intake_semester_id)" @update:model-value="(value) => setFilter('intake_semester_id', value)">
                        <SelectTrigger><SelectValue placeholder="Intake" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả intake</SelectItem>
                            <SelectItem v-for="option in fee_monitor.filter_options.intakes" :key="option.value" :value="String(option.value)">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="String(tableFilters.cohort)" @update:model-value="(value) => setFilter('cohort', value)">
                        <SelectTrigger><SelectValue placeholder="Cohort" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả cohort</SelectItem>
                            <SelectItem v-for="option in fee_monitor.filter_options.cohorts" :key="option.value" :value="String(option.value)">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.expected_fee_type" @update:model-value="(value) => setFilter('expected_fee_type', value)">
                        <SelectTrigger><SelectValue placeholder="Loại phí dự kiến" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả loại phí</SelectItem>
                            <SelectItem v-for="option in fee_monitor.filter_options.expected_fee_types" :key="option.value" :value="String(option.value)">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.generation_state" @update:model-value="(value) => setFilter('generation_state', value)">
                        <SelectTrigger><SelectValue placeholder="Trạng thái sinh phí" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả trạng thái</SelectItem>
                            <SelectItem v-for="option in fee_monitor.filter_options.generation_states" :key="option.value" :value="String(option.value)">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.student_status" @update:model-value="(value) => setFilter('student_status', value)">
                        <SelectTrigger><SelectValue placeholder="Trạng thái SV" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả trạng thái SV</SelectItem>
                            <SelectItem v-for="option in fee_monitor.filter_options.student_statuses" :key="option.value" :value="String(option.value)">
                                {{ option.label }}
                            </SelectItem>
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
                                <TableHead>Loại phí</TableHead>
                                <TableHead>Sinh phí</TableHead>
                                <TableHead>Thanh toán</TableHead>
                                <TableHead class="text-right">Số tiền</TableHead>
                                <TableHead class="text-right">Hành động</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="7" class="text-muted-foreground py-8 text-center text-sm">Đang tải...</TableCell>
                            </TableRow>
                            <TableRow v-else-if="fee_monitor.rows.data.length === 0">
                                <TableCell colspan="7" class="text-muted-foreground py-8 text-center text-sm">Không có dòng nào trong phạm vi lọc hiện tại.</TableCell>
                            </TableRow>
                            <TableRow v-for="row in fee_monitor.rows.data" :key="row.row_key">
                                <TableCell>
                                    <div class="font-medium">{{ row.student.full_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ row.student.student_code }} · {{ row.student.status_label }}</div>
                                </TableCell>
                                <TableCell>
                                    <div>{{ row.program_code ?? '—' }}</div>
                                    <div v-if="row.cohort" class="text-muted-foreground text-xs">Cohort {{ row.cohort }}</div>
                                </TableCell>
                                <TableCell>
                                    <div>{{ row.expected_fee_type_label }}</div>
                                    <div class="text-muted-foreground text-xs">{{ CHARGE_TYPE_LABELS[row.expected_fee_type as keyof typeof CHARGE_TYPE_LABELS] ?? row.expected_fee_type }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="generationStateVariant(row.generation_state)">{{ generationStateLabel[row.generation_state] }}</Badge>
                                    <div v-if="row.generation_reason" class="text-muted-foreground mt-1 text-xs">{{ row.generation_reason }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="row.payment_state" variant="outline">{{ paymentStateLabel[row.payment_state] }}</Badge>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div>{{ row.amount !== null ? formatCurrency(row.amount) : '—' }}</div>
                                    <div v-if="row.outstanding_amount !== null && row.outstanding_amount > 0" class="text-muted-foreground text-xs">Còn {{ formatCurrency(row.outstanding_amount) }}</div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link v-if="batchHandoffUrl(row)" :href="batchHandoffUrl(row)!" class="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline">
                                            Batch Studio
                                            <ArrowRight class="size-3" />
                                        </Link>
                                        <LookupRowActions :student-id="row.student.id" :focus="row.drilldowns.student_360_focus ?? undefined" :detail-url="lookupDetailUrl(row)" />
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <DataPagination :pagination-data="fee_monitor.rows" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
