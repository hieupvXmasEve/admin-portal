<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertTriangle, Ban, CheckCircle2, Clock, CreditCard, Plus, RefreshCw, UserCheck } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { route } from 'ziggy-js';

interface RetakeState {
    value: string;
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning' | 'info' | 'purple' | 'indigo';
}

interface RetakeRegistration {
    id: number;
    student: { id: number; full_name: string; student_id: string };
    unit: { id: number; code: string; name: string };
    course_offering: { id: number; section_code: string | null; semester: { name: string }; campus: { name: string } | null } | null;
    semester: { id: number; name: string; code: string };
    campus: { id: number; name: string; code: string };
    status: string;
    attempt_number: number;
    retake_fee: string;
    payment_deadline: string | null;
    approved_at: string | null;
    created_at: string;
    approved_by: { name: string } | null;
    paid_at: string | null;
    enrolled_at: string | null;
    course_registration: { id: number; registration_status: string; is_retake: boolean; is_retake_paid: boolean } | null;
    payment_state: RetakeState;
    class_state: RetakeState;
    operation_state: RetakeState;
    available_actions: string[];
    exception_summary: string | null;
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
    registrations: PaginatedResponse<RetakeRegistration>;
    summary: {
        total: number;
        awaiting_payment: number;
        paid_waiting_class: number;
        enrolled: number;
        needs_review: number;
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
    baseUrl: route('academic.retake-course.index'),
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
    only: ['registrations', 'filters', 'summary'],
    debounce: 400,
    immediateFields: ['operation_state', 'semester_id'],
});

const data = computed(() => props.registrations.data);
const syncingId = ref<number | null>(null);

const summaryItems = computed(() => [
    { key: null, label: 'Tất cả', count: props.summary.total, icon: CreditCard },
    { key: 'awaiting_payment', label: 'Chờ thanh toán', count: props.summary.awaiting_payment, icon: Clock },
    { key: 'paid_waiting_class', label: 'Đã thu - chờ lớp', count: props.summary.paid_waiting_class, icon: UserCheck },
    { key: 'enrolled', label: 'Đã ghi danh', count: props.summary.enrolled, icon: CheckCircle2 },
    { key: 'needs_review', label: 'Cần xử lý', count: props.summary.needs_review, icon: AlertTriangle },
]);

const applyOperationFilter = (state: string | null) => {
    setFilter('operation_state', state);
};

// Cancel dialog state
const cancelDialogOpen = ref(false);
const cancelTarget = ref<RetakeRegistration | null>(null);
const cancelForm = useForm({ reason: '' });

const openCancelDialog = (registration: RetakeRegistration) => {
    cancelTarget.value = registration;
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelDialogOpen.value = true;
};

const submitCancel = () => {
    if (!cancelTarget.value) return;
    cancelForm.post(route('academic.retake-course.cancel', cancelTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelDialogOpen.value = false;
            cancelTarget.value = null;
            cancelForm.reset();
        },
    });
};

const syncRegistration = (registration: RetakeRegistration) => {
    syncingId.value = registration.id;
    router.post(
        route('academic.retake-course.sync', registration.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncingId.value = null;
            },
        },
    );
};

const columns: ColumnDef<RetakeRegistration>[] = [
    {
        header: '#',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.registrations.current_page;
            const perPage = props.registrations.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
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
        cell: ({ row }) => row.original.semester.name,
    },
    {
        header: 'Vận hành',
        id: 'operation_state',
        enableSorting: false,
        cell: 'operation_state',
    },
    {
        header: 'Thanh toán',
        id: 'payment_state',
        enableSorting: false,
        cell: 'payment_state',
    },
    {
        header: 'Lớp',
        id: 'class_state',
        enableSorting: false,
        cell: 'class_state',
    },
    {
        header: 'Phí học lại',
        accessorKey: 'retake_fee',
        enableSorting: true,
        cell: ({ row }) => {
            const fee = parseFloat(row.original.retake_fee);
            return h('div', { class: 'font-mono text-sm' }, fee.toLocaleString('vi-VN') + ' đ');
        },
    },
    {
        header: 'Hạn TT',
        accessorKey: 'payment_deadline',
        enableSorting: true,
        cell: ({ row }) => {
            if (!row.original.payment_deadline) return '—';
            return new Date(row.original.payment_deadline).toLocaleDateString('vi-VN');
        },
    },
    {
        header: 'Ngày tạo',
        accessorKey: 'created_at',
        enableSorting: true,
        cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString('vi-VN'),
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: 'actions',
    },
];
</script>

<template>
    <Head title="Đăng ký học lại" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Đăng ký học lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Quản lý danh sách đăng ký học lại cho sinh viên.</p>
        </div>
        <Button @click="router.visit(route('academic.retake-course.create'))" class="gap-2">
            <Plus class="h-4 w-4" />
            Đăng ký mới
        </Button>
    </div>

    <div class="mt-6 flex flex-col gap-4">
        <!-- Filters -->
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
                    <SelectItem value="paid_waiting_class">Đã thu - chờ thêm lớp</SelectItem>
                    <SelectItem value="enrolled">Đã ghi danh</SelectItem>
                    <SelectItem value="needs_review">Cần xử lý</SelectItem>
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
                <Badge :variant="row.original.operation_state.variant">
                    {{ row.original.operation_state.label }}
                </Badge>
                <p v-if="row.original.exception_summary" class="text-destructive mt-1 max-w-[220px] text-xs">
                    {{ row.original.exception_summary }}
                </p>
            </template>
            <template #cell-payment_state="{ row }">
                <Badge :variant="row.original.payment_state.variant">
                    {{ row.original.payment_state.label }}
                </Badge>
            </template>
            <template #cell-class_state="{ row }">
                <Badge :variant="row.original.class_state.variant">
                    {{ row.original.class_state.label }}
                </Badge>
                <p v-if="row.original.course_registration" class="text-muted-foreground mt-1 font-mono text-xs">CR#{{ row.original.course_registration.id }} · {{ row.original.course_registration.registration_status }}</p>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-1">
                    <TooltipProvider v-if="row.original.available_actions.includes('sync')" :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="syncingId === row.original.id" @click="syncRegistration(row.original)">
                                    <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': syncingId === row.original.id }" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Quét lại lớp đã có</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <TooltipProvider v-if="row.original.available_actions.includes('cancel')" :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive h-8 w-8" @click="openCancelDialog(row.original)">
                                    <Ban class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Hủy đăng ký</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="registrations" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Cancel Dialog -->
    <Dialog v-model:open="cancelDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Hủy đăng ký học lại</DialogTitle>
                <DialogDescription v-if="cancelTarget">
                    {{ cancelTarget.student.full_name }} ({{ cancelTarget.student.student_id }}) — {{ cancelTarget.unit.code }}
                    <template v-if="cancelTarget.status === 'payment_pending'">
                        <br />
                        <span class="text-destructive font-medium">Charge và invoice liên quan sẽ bị hủy.</span>
                    </template>
                    <template v-if="cancelTarget.status === 'paid'">
                        <br />
                        <span class="font-medium text-amber-600 dark:text-amber-400">Đơn đã thanh toán: tiền đã thu sẽ được chuyển thành dư nợ dùng cho các phí phát sinh sau của sinh viên (không hoàn về tài khoản).</span>
                    </template>
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitCancel" class="space-y-4">
                <div class="space-y-1.5">
                    <Label>Lý do hủy <span class="text-destructive">*</span></Label>
                    <Textarea v-model="cancelForm.reason" placeholder="Nhập lý do hủy (tối thiểu 5 ký tự)..." rows="3" />
                    <p v-if="cancelForm.errors.reason" class="text-destructive text-xs">{{ cancelForm.errors.reason }}</p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="cancelDialogOpen = false">Đóng</Button>
                    <Button type="submit" variant="destructive" :disabled="cancelForm.processing">
                        {{ cancelForm.processing ? 'Đang xử lý...' : 'Xác nhận hủy' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
