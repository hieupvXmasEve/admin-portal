<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertTriangle, Ban, CalendarClock, CalendarDays, CheckCircle2, Clock, CreditCard, GraduationCap, Plus } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { route } from 'ziggy-js';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning' | 'info' | 'purple' | 'indigo';

interface ExamResitState {
    value: string;
    label: string;
    variant: BadgeVariant;
}

interface CancelContext {
    fee_state: 'paid_no_refund' | 'unpaid_charge' | 'no_charge' | string;
    requires_no_refund_acknowledgement: boolean;
    requires_unpaid_fee_confirmation: boolean;
    confirmation_token: string | null;
    title: string;
    message: string;
}

interface ExamResitRow {
    id: number;
    student: { id: number; full_name: string; student_id: string };
    unit: { id: number; code: string; name: string };
    semester: { id: number; name: string; code: string } | null;
    campus: { id: number; name: string; code: string } | null;
    status: string;
    request_sequence: number;
    attempt_number: number | null;
    fee_amount: string | null;
    payment_deadline: string | null;
    scheduled_at: string | null;
    completed_at: string | null;
    created_at: string;
    hq_fee_status: string;
    finance_charge_id: number | null;
    cancellation_fee_disposition: string | null;
    cancellation_notice_sent_at: string | null;
    cancellation_notice_error: string | null;
    resit_score: string | null;
    final_chosen_score: string | null;
    resit_passed: boolean | null;
    unpaid_allowed_reason: string | null;
    session: {
        id: number;
        status: string;
        exam_date: string | null;
        start_time: string | null;
        end_time: string | null;
        room: { id: number; name: string; code: string } | null;
    } | null;
    payment_state: ExamResitState;
    schedule_state: ExamResitState;
    result_state: ExamResitState;
    operation_state: ExamResitState;
    available_actions: string[];
    cancel_context: CancelContext;
}

interface Filters {
    search: string;
    status: string | null;
    operation_state: string | null;
    semester_id: number | null;
    unit_id: number | null;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

interface Props {
    attempts: PaginatedResponse<ExamResitRow>;
    summary: {
        total: number;
        awaiting_payment: number;
        ready_to_schedule: number;
        scheduled: number;
        completed: number;
        cancelled: number;
    };
    filters?: Partial<Filters>;
    semesters: { id: number; name: string; code: string }[];
}

const props = defineProps<Props>();

const filterProps = props.filters && !Array.isArray(props.filters) ? props.filters : {};
const stringFilter = (value: unknown): string | null => (typeof value === 'string' && value !== '' ? value : null);
const numberFilter = (value: unknown): number | null => {
    if (typeof value === 'number') return value;
    if (typeof value === 'string' && value !== '' && !Number.isNaN(Number(value))) return Number(value);

    return null;
};
const directionFilter = (value: unknown): 'asc' | 'desc' | null => (value === 'asc' || value === 'desc' ? value : null);

const {
    filters: tableFilters,
    hasActiveFilters,
    isLoading,
    currentSort,
    currentDirection,
    handleSearch,
    handleSortChange,
    handlePaginationNavigate,
    handlePageSizeChange,
    setFilter,
    clearAllFilters,
} = useDataTable<Filters>({
    baseUrl: route('academic.exam-resit.index'),
    initialFilters: {
        search: stringFilter(filterProps.search) ?? '',
        status: stringFilter(filterProps.status),
        operation_state: stringFilter(filterProps.operation_state),
        semester_id: numberFilter(filterProps.semester_id),
        unit_id: numberFilter(filterProps.unit_id),
        sort: stringFilter(filterProps.sort),
        direction: directionFilter(filterProps.direction),
        per_page: numberFilter(filterProps.per_page) ?? 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'desc',
        search: '',
        status: null,
        operation_state: null,
        semester_id: null,
        unit_id: null,
        sort: null,
    },
    only: ['attempts', 'filters', 'summary'],
    debounce: 400,
    immediateFields: ['operation_state', 'semester_id'],
});

const data = computed(() => props.attempts.data);

const summaryItems = computed(() => [
    { key: null, label: 'Tất cả', count: props.summary.total, icon: CreditCard },
    { key: 'awaiting_payment', label: 'Chờ thanh toán', count: props.summary.awaiting_payment, icon: Clock },
    { key: 'ready_to_schedule', label: 'Đã thu - chờ xếp lịch', count: props.summary.ready_to_schedule, icon: CalendarClock },
    { key: 'scheduled', label: 'Đã xếp lịch', count: props.summary.scheduled, icon: CalendarDays },
    { key: 'completed', label: 'Hoàn tất', count: props.summary.completed, icon: CheckCircle2 },
]);

const applyOperationFilter = (state: string | null) => {
    setFilter('operation_state', state);
};

const formatScore = (value: string | null): string => (value === null || value === '' ? '—' : Number(value).toFixed(1));
const formatDate = (value: string | null): string => (value ? new Date(value).toLocaleDateString('vi-VN') : '—');

// Cancel dialog state
const cancelDialogOpen = ref(false);
const cancelTarget = ref<ExamResitRow | null>(null);
const cancelAcknowledged = ref(false);
const cancelForm = useForm({
    reason: '',
    acknowledge_no_refund: false,
    confirmation: null as string | null,
});

const cancelRequiresAcknowledgement = computed(() => Boolean(cancelTarget.value?.cancel_context.requires_no_refund_acknowledgement || cancelTarget.value?.cancel_context.requires_unpaid_fee_confirmation));

const cancelAcknowledgementLabel = computed(() => {
    if (cancelTarget.value?.cancel_context.requires_no_refund_acknowledgement) {
        return 'Tôi xác nhận hủy lượt thi lại, giữ nguyên khoản phí đã thu và không tạo hoàn phí.';
    }

    if (cancelTarget.value?.cancel_context.requires_unpaid_fee_confirmation) {
        return 'Tôi xác nhận hủy khoản phí/DNG đang chờ thu và gửi email thông báo cho sinh viên.';
    }

    return '';
});

const cancelWarningClass = computed(() => {
    if (cancelTarget.value?.cancel_context.fee_state === 'paid_no_refund') {
        return 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-100';
    }

    if (cancelTarget.value?.cancel_context.fee_state === 'unpaid_charge') {
        return 'border-destructive/30 bg-destructive/5 text-foreground';
    }

    return 'border-border bg-muted/40 text-foreground';
});

const cancelSubmitDisabled = computed(() => cancelForm.processing || (cancelRequiresAcknowledgement.value && !cancelAcknowledged.value));

const openCancelDialog = (attempt: ExamResitRow) => {
    cancelTarget.value = attempt;
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelAcknowledged.value = false;
    cancelDialogOpen.value = true;
};

const submitCancel = () => {
    if (!cancelTarget.value) return;

    const context = cancelTarget.value.cancel_context;
    cancelForm.acknowledge_no_refund = context.requires_no_refund_acknowledgement && cancelAcknowledged.value;
    cancelForm.confirmation = context.requires_unpaid_fee_confirmation && cancelAcknowledged.value ? context.confirmation_token : null;

    cancelForm.post(route('academic.exam-resit.cancel', cancelTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelDialogOpen.value = false;
            cancelTarget.value = null;
            cancelForm.reset();
            cancelAcknowledged.value = false;
        },
    });
};

const columns: ColumnDef<ExamResitRow>[] = [
    {
        header: '#',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => (props.attempts.current_page - 1) * props.attempts.per_page + row.index + 1,
    },
    {
        header: 'Sinh viên',
        id: 'student',
        enableSorting: false,
        cell: ({ row }) => h('div', [h('div', { class: 'font-medium' }, row.original.student.full_name), h('div', { class: 'text-xs text-muted-foreground font-mono' }, row.original.student.student_id)]),
    },
    {
        header: 'Môn học',
        id: 'unit',
        enableSorting: false,
        cell: ({ row }) => h('div', [h('div', { class: 'font-mono text-sm font-medium' }, row.original.unit.code), h('div', { class: 'text-xs text-muted-foreground max-w-[200px] truncate' }, row.original.unit.name)]),
    },
    {
        header: 'Học kỳ',
        id: 'semester',
        enableSorting: false,
        cell: ({ row }) => row.original.semester?.name ?? '—',
    },
    { header: 'Vận hành', id: 'operation_state', enableSorting: false, cell: 'operation_state' },
    { header: 'Thanh toán', id: 'payment_state', enableSorting: false, cell: 'payment_state' },
    { header: 'Lịch thi', id: 'schedule_state', enableSorting: false, cell: 'schedule_state' },
    { header: 'Kết quả', id: 'result_state', enableSorting: false, cell: 'result_state' },
    {
        header: 'Phí thi lại',
        accessorKey: 'fee_amount',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-mono text-sm' }, row.original.fee_amount ? Number(row.original.fee_amount).toLocaleString('vi-VN') + ' đ' : '—'),
    },
    { id: 'actions', header: 'Actions', enableSorting: false, cell: 'actions' },
];
</script>

<template>
    <Head title="Thi lại" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Thi lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Quản lý các lượt thi lại: duyệt phí, xếp lịch và cập nhật kết quả.</p>
        </div>
        <Button @click="router.visit(route('academic.exam-resit.create'))" class="gap-2">
            <Plus class="h-4 w-4" />
            Đăng ký thi lại
        </Button>
    </div>

    <div class="mt-6 flex flex-col gap-4">
        <!-- Summary cards -->
        <div class="grid gap-2 md:grid-cols-5">
            <button
                v-for="item in summaryItems"
                :key="item.key ?? 'all'"
                type="button"
                class="bg-background hover:bg-accent flex items-center justify-between rounded-md border px-3 py-2 text-left text-sm transition"
                :class="{ 'border-primary ring-primary ring-1': (tableFilters.operation_state ?? null) === item.key }"
                @click="applyOperationFilter(item.key)"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <component :is="item.icon" class="text-muted-foreground h-4 w-4 shrink-0" />
                    <span class="truncate">{{ item.label }}</span>
                </span>
                <span class="font-mono text-sm font-semibold">{{ item.count }}</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="w-full max-w-xs">
                <DebouncedInput :model-value="tableFilters.search" @update:model-value="handleSearch" placeholder="Tìm SV, MSSV, mã môn..." />
            </div>
            <Select :model-value="tableFilters.operation_state ?? undefined" @update:model-value="(v) => setFilter('operation_state', typeof v === 'string' ? v : null)">
                <SelectTrigger class="w-[220px]">
                    <SelectValue placeholder="Trạng thái vận hành" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="awaiting_payment">Chờ thanh toán</SelectItem>
                    <SelectItem value="ready_to_schedule">Đã thu - chờ xếp lịch</SelectItem>
                    <SelectItem value="scheduled">Đã xếp lịch</SelectItem>
                    <SelectItem value="completed">Hoàn tất</SelectItem>
                    <SelectItem value="no_show">Vắng thi</SelectItem>
                    <SelectItem value="cancelled">Đã hủy</SelectItem>
                </SelectContent>
            </Select>
            <Select :model-value="tableFilters.semester_id?.toString() ?? undefined" @update:model-value="(v) => setFilter('semester_id', v ? Number(v) : null)">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Học kỳ" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="sem in semesters" :key="sem.id" :value="sem.id.toString()">{{ sem.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Button variant="outline" size="sm" @click="clearAllFilters" :disabled="!hasActiveFilters" v-if="hasActiveFilters"> Xóa bộ lọc </Button>
        </div>

        <DataTable :data="data" :columns="columns" :loading="isLoading" :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange">
            <template #cell-operation_state="{ row }">
                <Badge :variant="row.original.operation_state.variant">{{ row.original.operation_state.label }}</Badge>
                <p v-if="row.original.unpaid_allowed_reason" class="text-muted-foreground mt-1 flex max-w-[220px] items-center gap-1 text-xs"><AlertTriangle class="h-3 w-3 shrink-0" /> Thi trước khi thu phí</p>
            </template>
            <template #cell-payment_state="{ row }">
                <Badge :variant="row.original.payment_state.variant">{{ row.original.payment_state.label }}</Badge>
            </template>
            <template #cell-schedule_state="{ row }">
                <Badge :variant="row.original.schedule_state.variant">{{ row.original.schedule_state.label }}</Badge>
                <p v-if="row.original.session" class="text-muted-foreground mt-1 max-w-[200px] text-xs">
                    {{ formatDate(row.original.session.exam_date) }} · {{ row.original.session.start_time }}–{{ row.original.session.end_time }}
                    <template v-if="row.original.session.room"><br />{{ row.original.session.room.name }}</template>
                </p>
            </template>
            <template #cell-result_state="{ row }">
                <Badge :variant="row.original.result_state.variant">{{ row.original.result_state.label }}</Badge>
                <p v-if="row.original.final_chosen_score !== null" class="text-muted-foreground mt-1 font-mono text-xs">Điểm chốt: {{ formatScore(row.original.final_chosen_score) }}</p>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-1">
                    <TooltipProvider v-if="row.original.available_actions.includes('schedule')" :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="h-8 w-8" @click="router.visit(route('academic.exam-resit.schedule.create', row.original.id))">
                                    <CalendarClock class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent><p>Xếp lịch thi</p></TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <TooltipProvider v-if="row.original.available_actions.includes('complete')" :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="h-8 w-8" @click="router.visit(route('academic.exam-resit.complete.create', row.original.id))">
                                    <GraduationCap class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent><p>Nhập kết quả</p></TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <TooltipProvider v-if="row.original.available_actions.includes('cancel')" :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive h-8 w-8" @click="openCancelDialog(row.original)">
                                    <Ban class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent><p>Hủy thi lại</p></TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="attempts" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Cancel Dialog -->
    <Dialog v-model:open="cancelDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ cancelTarget?.cancel_context.title ?? 'Hủy thi lại' }}</DialogTitle>
                <DialogDescription v-if="cancelTarget"> {{ cancelTarget.student.full_name }} ({{ cancelTarget.student.student_id }}) — {{ cancelTarget.unit.code }} </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitCancel" class="space-y-4">
                <div v-if="cancelTarget" class="rounded-md border p-3 text-sm leading-relaxed" :class="cancelWarningClass">
                    <div class="flex items-start gap-2">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                        <div class="min-w-0">
                            <p class="font-medium">{{ cancelTarget.cancel_context.title }}</p>
                            <p class="mt-1">{{ cancelTarget.cancel_context.message }}</p>
                            <p v-if="cancelTarget.cancel_context.fee_state === 'paid_no_refund'" class="mt-1 text-xs">Student portal vẫn giữ lịch sử khoản phí đã thanh toán; thao tác này không hoàn tiền và không void charge.</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label>Lý do hủy <span class="text-destructive">*</span></Label>
                    <Textarea v-model="cancelForm.reason" placeholder="Nhập lý do hủy (tối thiểu 5 ký tự)..." rows="3" />
                    <p v-if="cancelForm.errors.reason" class="text-destructive text-xs">{{ cancelForm.errors.reason }}</p>
                </div>
                <label v-if="cancelRequiresAcknowledgement" class="flex items-start gap-3 rounded-md border p-3 text-sm leading-relaxed">
                    <Checkbox v-model="cancelAcknowledged" class="mt-0.5" />
                    <span>{{ cancelAcknowledgementLabel }}</span>
                </label>
                <p v-if="cancelForm.errors.acknowledge_no_refund" class="text-destructive text-xs">{{ cancelForm.errors.acknowledge_no_refund }}</p>
                <p v-if="cancelForm.errors.confirmation" class="text-destructive text-xs">{{ cancelForm.errors.confirmation }}</p>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="cancelDialogOpen = false">Đóng</Button>
                    <Button type="submit" variant="destructive" :disabled="cancelSubmitDisabled">
                        {{ cancelForm.processing ? 'Đang xử lý...' : 'Xác nhận hủy' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
