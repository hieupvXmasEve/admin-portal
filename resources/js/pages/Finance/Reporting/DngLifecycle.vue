<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, getChargeTypeLabel } from '@/types/finance';
import { formatDate } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { Link } from '@inertiajs/vue3';
import { Clock, ExternalLink, FileSearch, ScrollText, Webhook } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DngStudent {
    id: number;
    student_code: string;
    full_name: string;
    status: string;
    status_label: string;
}

interface RelatedSemester {
    id: number;
    name: string;
}

interface DngLifecycleRow {
    row_key: string;
    request_id: number;
    created_at: string | null;
    due_date: string | null;
    paid_at: string | null;
    student: DngStudent;
    fee_type: string;
    amount: number;
    status: string;
    status_label: string;
    payment_bridge: string;
    payment_bridge_label: string;
    payment_id: number | null;
    webhook_state: string;
    webhook_state_label: string;
    webhook_event_count: number;
    invoice_state: string;
    invoice_state_label: string;
    invoice_serial_number: string | null;
    allocation_state: string;
    allocation_state_label: string;
    flow_state: string;
    flow_state_label: string;
    related_semester_state: 'single' | 'multi' | 'unknown';
    related_semester_state_label: string;
    related_semesters: RelatedSemester[];
    outside_selected_semester: boolean;
    attention_buckets: string[];
    attention_reasons: string[];
    needs_attention: boolean;
    error_message: string | null;
    drilldowns: { dng_request_id: number; student_id: number; has_webhooks: boolean; webhook_event_id: number | null };
}

interface DngLifecycleSummary {
    total_count: number;
    needs_attention_count: number;
    total_amount: number;
    attention_amount: number;
    failed_request_count: number;
    webhook_problem_count: number;
    paid_uninvoiced_count: number;
    pending_stale_count: number;
    overdue_pushed_count: number;
    bridged_count: number;
    unbridged_count: number;
    outside_selected_semester_count: number;
}

interface BreakdownItem {
    key: string;
    label: string;
    count: number;
    amount: number;
}

interface FilterOption {
    value: string | number;
    label: string;
}

interface DngLifecycleFilters {
    attention_bucket: string;
    dng_status: string;
    payment_bridge: string;
    webhook_state: string;
    invoice_state: string;
    allocation_state: string;
    flow_state: string;
    related_semester: string;
    outside_selected_semester: string;
    fee_type: string;
    amount_min: string;
    amount_max: string;
    created_from: string;
    created_to: string;
    paid_from: string;
    paid_to: string;
    search: string;
    per_page: number;
    page: number;
}

interface DngLifecyclePayload {
    rows: PaginatedResponse<DngLifecycleRow>;
    summary: DngLifecycleSummary;
    breakdowns: {
        by_attention_bucket: BreakdownItem[];
        by_status: BreakdownItem[];
        by_webhook_state: BreakdownItem[];
        by_payment_bridge: BreakdownItem[];
        by_invoice_state: BreakdownItem[];
        by_related_semester: BreakdownItem[];
        by_fee_type: BreakdownItem[];
    };
    filters: Partial<DngLifecycleFilters>;
    filter_options: {
        attention_buckets: FilterOption[];
        dng_statuses: FilterOption[];
        payment_bridges: FilterOption[];
        webhook_states: FilterOption[];
        invoice_states: FilterOption[];
        allocation_states: FilterOption[];
        flow_states: FilterOption[];
        fee_types: FilterOption[];
        related_semesters: FilterOption[];
        selected_semester_id: number | null;
    };
    meta: {
        selected_semester_id: number | null;
        campus_bound: boolean;
        obeys_semester: boolean;
        total_matched: number;
        scan_cap: number;
        truncated: boolean;
    };
    computed_at: string;
}

const props = defineProps<{ dng_lifecycle: DngLifecyclePayload }>();

const filterProps = props.dng_lifecycle.filters ?? {};
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
} = useDataTable<DngLifecycleFilters & { view: string }>({
    baseUrl: financeRoutes.reporting.index({ view: 'dng-lifecycle' }),
    initialFilters: {
        view: 'dng-lifecycle',
        attention_bucket: stringFilter(filterProps.attention_bucket),
        dng_status: stringFilter(filterProps.dng_status),
        payment_bridge: stringFilter(filterProps.payment_bridge),
        webhook_state: stringFilter(filterProps.webhook_state),
        invoice_state: stringFilter(filterProps.invoice_state),
        allocation_state: stringFilter(filterProps.allocation_state),
        flow_state: stringFilter(filterProps.flow_state),
        related_semester: stringFilter(filterProps.related_semester),
        outside_selected_semester: stringFilter(filterProps.outside_selected_semester),
        fee_type: stringFilter(filterProps.fee_type),
        amount_min: stringFilter(filterProps.amount_min, ''),
        amount_max: stringFilter(filterProps.amount_max, ''),
        created_from: stringFilter(filterProps.created_from, ''),
        created_to: stringFilter(filterProps.created_to, ''),
        paid_from: stringFilter(filterProps.paid_from, ''),
        paid_to: stringFilter(filterProps.paid_to, ''),
        search: stringFilter(filterProps.search, ''),
        per_page: numberFilter(filterProps.per_page),
        page: numberFilter(filterProps.page, 1),
    },
    defaultValues: {
        view: 'dng-lifecycle',
        attention_bucket: 'all',
        dng_status: 'all',
        payment_bridge: 'all',
        webhook_state: 'all',
        invoice_state: 'all',
        allocation_state: 'all',
        flow_state: 'all',
        related_semester: 'all',
        outside_selected_semester: 'all',
        fee_type: 'all',
        amount_min: '',
        amount_max: '',
        created_from: '',
        created_to: '',
        paid_from: '',
        paid_to: '',
        search: '',
        per_page: 20,
        page: 1,
    },
    only: ['dng_lifecycle', 'computed_at', 'active_view'],
    immediateFields: ['attention_bucket', 'dng_status', 'payment_bridge', 'webhook_state', 'invoice_state', 'allocation_state', 'flow_state', 'related_semester', 'outside_selected_semester', 'fee_type'],
});

const summary = computed(() => props.dng_lifecycle.summary);
const options = computed(() => props.dng_lifecycle.filter_options);

const summaryCards = computed(() => [
    { key: 'total', label: 'Tổng yêu cầu', value: summary.value.total_count, sub: formatCurrency(summary.value.total_amount) },
    { key: 'attention', label: 'Cần xử lý', value: summary.value.needs_attention_count, sub: formatCurrency(summary.value.attention_amount), accent: 'text-amber-600' },
    { key: 'failed', label: 'Yêu cầu thất bại', value: summary.value.failed_request_count, accent: 'text-red-600' },
    { key: 'webhook', label: 'Webhook lỗi/sai lệch', value: summary.value.webhook_problem_count, accent: 'text-red-600' },
    { key: 'paid_uninvoiced', label: 'Đã thu, chưa HĐ', value: summary.value.paid_uninvoiced_count },
    { key: 'overdue', label: 'Quá hạn / chờ lâu', value: summary.value.overdue_pushed_count, sub: `${summary.value.pending_stale_count} chờ > 60'` },
]);

const breakdownDimensions = [
    { key: 'by_attention_bucket', label: 'Nhóm cần xử lý' },
    { key: 'by_status', label: 'Trạng thái DNG' },
    { key: 'by_webhook_state', label: 'Trạng thái webhook' },
    { key: 'by_payment_bridge', label: 'Cầu nối Payment' },
    { key: 'by_invoice_state', label: 'Trạng thái hóa đơn' },
    { key: 'by_related_semester', label: 'Học kỳ liên quan' },
    { key: 'by_fee_type', label: 'Loại phí' },
] as const;

type BreakdownKey = (typeof breakdownDimensions)[number]['key'];

const activeBreakdown = ref<BreakdownKey>('by_attention_bucket');
const breakdownRows = computed<BreakdownItem[]>(() => props.dng_lifecycle.breakdowns[activeBreakdown.value] ?? []);

const breakdownLabel = (item: BreakdownItem): string => (activeBreakdown.value === 'by_fee_type' ? getChargeTypeLabel(item.label) : item.label);

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'failed') return 'destructive';
    if (status === 'reconciled' || status === 'paid_invoiced') return 'default';
    if (status === 'cancelled' || status === 'cancel_pushed_to_dng') return 'outline';

    return 'secondary';
};

const webhookIsProblem = (state: string): boolean => ['invalid_checksum', 'mismatch', 'failed'].includes(state);

const updateDate = (field: keyof DngLifecycleFilters, value: string | null): void => {
    setFilter(field, (value ?? '') as never);
};
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
            <Card v-for="card in summaryCards" :key="card.key">
                <CardHeader class="pb-2">
                    <CardDescription>{{ card.label }}</CardDescription>
                    <CardTitle class="text-2xl" :class="card.accent">{{ card.value }}</CardTitle>
                    <p v-if="card.sub" class="text-muted-foreground text-xs">{{ card.sub }}</p>
                </CardHeader>
            </Card>
        </div>

        <Card>
            <CardHeader class="gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Phân tích theo nhóm</CardTitle>
                    <CardDescription>Số lượng yêu cầu DNG theo từng chiều, trong phạm vi bộ lọc hiện tại.</CardDescription>
                </div>
                <Select :model-value="activeBreakdown" @update:model-value="(value) => (activeBreakdown = value as BreakdownKey)">
                    <SelectTrigger class="w-full sm:w-64"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="dimension in breakdownDimensions" :key="dimension.key" :value="dimension.key">{{ dimension.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </CardHeader>
            <CardContent>
                <div class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nhóm</TableHead>
                                <TableHead class="text-right">Số yêu cầu</TableHead>
                                <TableHead class="text-right">Tổng tiền</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="breakdownRows.length === 0">
                                <TableCell colspan="3" class="text-muted-foreground py-6 text-center text-sm">Không có dữ liệu.</TableCell>
                            </TableRow>
                            <TableRow v-for="item in breakdownRows" :key="item.key">
                                <TableCell class="font-medium">{{ breakdownLabel(item) }}</TableCell>
                                <TableCell class="text-right">{{ item.count }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(item.amount) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Hàng đợi DNG / Payment</CardTitle>
                    <CardDescription>
                        Grain: yêu cầu DNG. Không lọc theo học kỳ toàn cục — học kỳ liên quan chỉ là dữ kiện. Computed at
                        {{ new Date(dng_lifecycle.computed_at).toLocaleString() }}.
                    </CardDescription>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <p class="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                        <Clock class="size-3.5" />
                        {{ summary.total_count }} yêu cầu · {{ summary.needs_attention_count }} cần xử lý
                    </p>
                    <Badge v-if="dng_lifecycle.meta.truncated" variant="outline" class="border-amber-300 text-amber-700">
                        Đang xem {{ dng_lifecycle.meta.scan_cap.toLocaleString() }} / {{ dng_lifecycle.meta.total_matched.toLocaleString() }} yêu cầu mới nhất — thu hẹp bộ lọc để xem đầy đủ
                    </Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <DebouncedInput :model-value="tableFilters.search" placeholder="Tìm mã SV / tên / item / mã DNG..." @update:model-value="handleSearch" />
                    <Select :model-value="tableFilters.attention_bucket" @update:model-value="(value) => setFilter('attention_bucket', value)">
                        <SelectTrigger><SelectValue placeholder="Nhóm cần xử lý" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả nhóm</SelectItem>
                            <SelectItem v-for="option in options.attention_buckets" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.dng_status" @update:model-value="(value) => setFilter('dng_status', value)">
                        <SelectTrigger><SelectValue placeholder="Trạng thái DNG" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả trạng thái</SelectItem>
                            <SelectItem v-for="option in options.dng_statuses" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.webhook_state" @update:model-value="(value) => setFilter('webhook_state', value)">
                        <SelectTrigger><SelectValue placeholder="Webhook" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả webhook</SelectItem>
                            <SelectItem v-for="option in options.webhook_states" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.payment_bridge" @update:model-value="(value) => setFilter('payment_bridge', value)">
                        <SelectTrigger><SelectValue placeholder="Cầu nối Payment" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem v-for="option in options.payment_bridges" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.invoice_state" @update:model-value="(value) => setFilter('invoice_state', value)">
                        <SelectTrigger><SelectValue placeholder="Hóa đơn" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả hóa đơn</SelectItem>
                            <SelectItem v-for="option in options.invoice_states" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.allocation_state" @update:model-value="(value) => setFilter('allocation_state', value)">
                        <SelectTrigger><SelectValue placeholder="Đối soát" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả đối soát</SelectItem>
                            <SelectItem v-for="option in options.allocation_states" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.flow_state" @update:model-value="(value) => setFilter('flow_state', value)">
                        <SelectTrigger><SelectValue placeholder="Hủy / lỗi / thử lại" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem v-for="option in options.flow_states" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.fee_type" @update:model-value="(value) => setFilter('fee_type', value)">
                        <SelectTrigger><SelectValue placeholder="Loại phí" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả loại phí</SelectItem>
                            <SelectItem v-for="option in options.fee_types" :key="option.value" :value="String(option.value)">{{ getChargeTypeLabel(String(option.value)) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.related_semester" @update:model-value="(value) => setFilter('related_semester', value)">
                        <SelectTrigger><SelectValue placeholder="Học kỳ liên quan" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả học kỳ</SelectItem>
                            <SelectItem v-for="option in options.related_semesters" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.outside_selected_semester" @update:model-value="(value) => setFilter('outside_selected_semester', value)">
                        <SelectTrigger><SelectValue placeholder="Ngoài học kỳ đang chọn" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem value="outside">Ngoài học kỳ đang chọn</SelectItem>
                            <SelectItem value="inside">Trong học kỳ đang chọn</SelectItem>
                        </SelectContent>
                    </Select>
                    <DebouncedInput :model-value="tableFilters.amount_min" type="number" placeholder="Số tiền từ" @update:model-value="(value) => setFilter('amount_min', String(value))" />
                    <DebouncedInput :model-value="tableFilters.amount_max" type="number" placeholder="Số tiền đến" @update:model-value="(value) => setFilter('amount_max', String(value))" />
                    <DatePicker :model-value="tableFilters.created_from" placeholder="Tạo từ ngày" @update:model-value="(value) => updateDate('created_from', value)" />
                    <DatePicker :model-value="tableFilters.created_to" placeholder="Tạo đến ngày" @update:model-value="(value) => updateDate('created_to', value)" />
                    <DatePicker :model-value="tableFilters.paid_from" placeholder="Thu từ ngày" @update:model-value="(value) => updateDate('paid_from', value)" />
                    <DatePicker :model-value="tableFilters.paid_to" placeholder="Thu đến ngày" @update:model-value="(value) => updateDate('paid_to', value)" />
                    <div class="flex items-center gap-2">
                        <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearAllFilters">Xóa bộ lọc</Button>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Yêu cầu / SV</TableHead>
                                <TableHead>Học kỳ liên quan</TableHead>
                                <TableHead>Trạng thái</TableHead>
                                <TableHead class="text-right">Số tiền</TableHead>
                                <TableHead>Cần xử lý</TableHead>
                                <TableHead class="text-right">Hành động</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="6" class="text-muted-foreground py-8 text-center text-sm">Đang tải...</TableCell>
                            </TableRow>
                            <TableRow v-else-if="dng_lifecycle.rows.data.length === 0">
                                <TableCell colspan="6" class="text-muted-foreground py-8 text-center text-sm">Không có yêu cầu nào trong phạm vi lọc hiện tại.</TableCell>
                            </TableRow>
                            <TableRow v-for="row in dng_lifecycle.rows.data" :key="row.row_key" :class="row.needs_attention ? 'bg-amber-50/40' : ''">
                                <TableCell>
                                    <div class="font-medium">#{{ row.request_id }} · {{ row.student.full_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ row.student.student_code }} · {{ getChargeTypeLabel(row.fee_type) }}</div>
                                    <div class="text-muted-foreground text-xs">
                                        Tạo {{ row.created_at ? formatDate(row.created_at) : '—' }}<span v-if="row.due_date"> · Hạn {{ formatDate(row.due_date) }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div v-if="row.related_semesters.length" class="space-y-0.5">
                                        <div v-for="sem in row.related_semesters" :key="sem.id" class="text-xs">{{ sem.name }}</div>
                                    </div>
                                    <span v-else class="text-muted-foreground text-xs">{{ row.related_semester_state_label }}</span>
                                    <Badge v-if="row.outside_selected_semester" variant="outline" class="mt-1 border-sky-300 text-sky-700">Ngoài học kỳ đang chọn</Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="statusVariant(row.status)">{{ row.status_label }}</Badge>
                                    <div class="text-muted-foreground mt-1 space-y-0.5 text-xs">
                                        <div :class="webhookIsProblem(row.webhook_state) ? 'text-red-600' : ''">
                                            Webhook: {{ row.webhook_state_label }}<span v-if="row.webhook_event_count"> ({{ row.webhook_event_count }})</span>
                                        </div>
                                        <div>{{ row.payment_bridge_label }} · {{ row.invoice_state_label }}</div>
                                        <div v-if="row.allocation_state !== 'not_applicable'">Đối soát: {{ row.allocation_state_label }}</div>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right font-medium">{{ formatCurrency(row.amount) }}</TableCell>
                                <TableCell>
                                    <div v-if="row.attention_reasons.length" class="flex flex-wrap gap-1">
                                        <Badge v-for="reason in row.attention_reasons" :key="reason" variant="outline" class="border-amber-300 text-amber-700">{{ reason }}</Badge>
                                    </div>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                    <div v-if="row.error_message" class="mt-1 max-w-56 truncate text-xs text-red-600" :title="row.error_message">{{ row.error_message }}</div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="financeRoutes.students.overview(row.student.id)" class="hover:bg-muted rounded p-1" title="Hồ sơ SV (360)">
                                            <ExternalLink class="size-4" />
                                        </Link>
                                        <Link :href="financeRoutes.collect.dngPaymentRequestDetail(row.request_id)" class="hover:bg-muted rounded p-1" title="Chi tiết yêu cầu DNG">
                                            <FileSearch class="size-4" />
                                        </Link>
                                        <Link
                                            v-if="row.drilldowns.has_webhooks"
                                            :href="row.drilldowns.webhook_event_id ? financeRoutes.collect.dngWebhookEventDetail(row.drilldowns.webhook_event_id) : financeRoutes.collect.dngWebhookEvents()"
                                            class="hover:bg-muted rounded p-1"
                                            title="Webhook DNG"
                                        >
                                            <Webhook class="size-4" />
                                        </Link>
                                        <Link :href="financeRoutes.audit()" class="hover:bg-muted rounded p-1" title="Lookup &amp; Audit">
                                            <ScrollText class="size-4" />
                                        </Link>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <DataPagination :pagination-data="dng_lifecycle.rows" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
