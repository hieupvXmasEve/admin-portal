<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables';
import { useDataTable } from '@/composables/useDataTable';
import { usePermission } from '@/composables/usePermission';
import { createColumns } from '@/lib/table-utils';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, CalendarIcon, CheckCircle2, ExternalLink, Loader2, Send, Wallet, X, Zap } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface SettlementInvoice {
    id: number;
    invoice_number: string;
    semester_id: number;
    semester_name: string | null;
    status: string;
    due_date: string | null;
    total_amount: number;
    paid_amount: number;
    remaining_amount: number;
}

interface FeeTypeBreakdownEntry {
    fee_type: string;
    label: string;
    gross: number;
    discount: number;
    net_remaining: number;
    semester_id: number | null;
    active_dng: { id: number; amount: number; status: string } | null;
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
    latest_dng_request: {
        id: number;
        status: string;
        item_id: string;
        description: string | null;
        created_at: string | null;
    } | null;
    invoices: SettlementInvoice[];
    fee_type_breakdown: FeeTypeBreakdownEntry[];
}

interface SettlementFilters {
    search: string;
    readiness: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface Props {
    students: PaginatedResponse<SettlementStudent>;
    summary: {
        students_with_unpaid_invoices: number;
        ready_students: number;
        total_active_due: number;
        total_unapplied_balance: number;
    };
    filters?: {
        search?: string;
        readiness?: string;
        per_page?: number;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

const props = defineProps<Props>();
const permission = usePermission();

const formatCurrency = (value: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);

const readinessOptions = [
    { value: 'all', label: 'All unpaid students' },
    { value: 'ready', label: 'Ready to settle' },
    { value: 'no_cash', label: 'No unapplied cash' },
];

const { filters, setFilter, clearAllFilters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, hasActiveFilters, isLoading, currentSort, currentDirection } = useDataTable<SettlementFilters>({
    baseUrl: route('finance.operations.settlement.index'),
    initialFilters: {
        search: props.filters?.search ?? '',
        readiness: props.filters?.readiness ?? 'all',
        per_page: props.filters?.per_page ?? 50,
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : 'active_due',
        direction: (props.filters?.direction as 'asc' | 'desc') || 'desc',
    },
    defaultValues: {
        search: '',
        readiness: 'all',
        per_page: 50,
        sort: 'active_due',
        direction: 'desc',
    },
    only: ['students', 'summary', 'filters'],
    debounce: 300,
    fieldDebounce: { search: 400 },
    immediateFields: ['readiness'],
});

const api = useApi();

const now = new Date();
const selectedStudentIds = ref<number[]>(props.students.data.filter((student) => student.actionable).map((student) => student.student_id));
const isApplying = ref(false);

// Batch DNG dialog state
const dngBatchDialogOpen = ref(false);
const dngBatchStudent = ref<SettlementStudent | null>(null);
const dngBatchDescription = ref('');
const dngBatchDueDate = ref('');
const dngBatchSubmitting = ref(false);
const estimateTimePickerOpen = ref(false);
const estimateMonth = ref(String(now.getMonth() + 1).padStart(2, '0'));
const estimateYear = ref(String(now.getFullYear()));
const estimateTime = ref(`${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getFullYear()).slice(-2)}`);

const estimateMonthOptions = [
    { value: '01', label: 'Tháng 01' },
    { value: '02', label: 'Tháng 02' },
    { value: '03', label: 'Tháng 03' },
    { value: '04', label: 'Tháng 04' },
    { value: '05', label: 'Tháng 05' },
    { value: '06', label: 'Tháng 06' },
    { value: '07', label: 'Tháng 07' },
    { value: '08', label: 'Tháng 08' },
    { value: '09', label: 'Tháng 09' },
    { value: '10', label: 'Tháng 10' },
    { value: '11', label: 'Tháng 11' },
    { value: '12', label: 'Tháng 12' },
];

const estimateYearOptions = Array.from({ length: 6 }, (_, i) => {
    const year = now.getFullYear() - 1 + i;
    return { value: String(year), label: `Năm ${year}` };
});

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

const getRecordsToCreateForStudent = (student: SettlementStudent) => student.fee_type_breakdown.filter((entry) => entry.semester_id !== null && (!entry.active_dng || entry.active_dng.amount !== entry.net_remaining));

const recordsToCreate = computed(() => (dngBatchStudent.value ? getRecordsToCreateForStudent(dngBatchStudent.value) : []));

const openDngBatchDialog = (student: SettlementStudent) => {
    dngBatchStudent.value = student;
    dngBatchDescription.value = '';
    dngBatchDueDate.value = '';
    dngBatchDialogOpen.value = true;
};

const applyEstimateTimeSelection = () => {
    estimateTime.value = `${estimateMonth.value}/${estimateYear.value.slice(-2)}`;
    estimateTimePickerOpen.value = false;
};

const submitDngBatch = async () => {
    if (!dngBatchStudent.value) return;

    if (!dngBatchDescription.value.trim()) {
        toast.error('Vui lòng nhập mô tả khoản phí.');
        return;
    }

    if (!dngBatchDueDate.value) {
        toast.error('Vui lòng chọn hạn thanh toán.');
        return;
    }

    if (recordsToCreate.value.length === 0) {
        toast.error('Không có khoản phí nào cần tạo.');
        return;
    }

    dngBatchSubmitting.value = true;

    try {
        const records = recordsToCreate.value.map((entry) => ({
            student_id: dngBatchStudent.value!.student_id,
            amount: entry.net_remaining,
            type: entry.fee_type,
            description: dngBatchDescription.value.trim(),
            semester_id: entry.semester_id!,
            due_date: dngBatchDueDate.value,
            estimate_time: estimateTime.value,
        }));

        const res = await api.post('/api/v1/finance/dng/batch', { records });

        if (res.error.value) throw new Error(String(res.error.value));

        const data = (res.data.value as { data?: { created?: number } })?.data;
        toast.success(`Đã tạo ${data?.created ?? records.length} DNG request thành công`);
        dngBatchDialogOpen.value = false;
        router.reload({ only: ['students', 'summary', 'filters'] });
    } catch {
        toast.error('Không thể tạo DNG. Vui lòng thử lại.');
    } finally {
        dngBatchSubmitting.value = false;
    }
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
            <div class="flex gap-2">
                <Link :href="route('finance.operations.batch-dng')">
                    <Button variant="outline">
                        <Send class="mr-2 h-4 w-4" />
                        Tạo DNG hàng loạt
                    </Button>
                </Link>
                <Button @click="applySettlement(selectedStudentIds)" :disabled="isApplying || selectedStudentIds.length === 0">
                    <Zap class="mr-2 h-4 w-4" />
                    Apply selected
                </Button>
            </div>
        </div>

        <Dialog v-model:open="dngBatchDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tạo DNG request</DialogTitle>
                    <DialogDescription v-if="dngBatchStudent"> {{ dngBatchStudent.student_name }} · {{ dngBatchStudent.student_code }} </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Records to create list -->
                    <div v-if="recordsToCreate.length > 0" class="space-y-1.5">
                        <p class="text-sm font-medium">Khoản phí sẽ tạo ({{ recordsToCreate.length }})</p>
                        <div v-for="entry in recordsToCreate" :key="entry.fee_type" class="bg-muted/30 flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                            <span>{{ entry.label }}</span>
                            <div class="flex items-center gap-2">
                                <Badge v-if="entry.active_dng && entry.active_dng.amount !== entry.net_remaining" variant="outline" class="border-orange-200 bg-orange-50 text-xs text-orange-700"> Tạo lại ↻ </Badge>
                                <span class="font-semibold text-red-600">{{ formatCurrency(entry.net_remaining) }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">Tất cả khoản phí đã có DNG đúng số tiền.</p>

                    <!-- Shared fields -->
                    <div class="space-y-2">
                        <Label for="batch-dng-description">Mô tả khoản phí *</Label>
                        <Input id="batch-dng-description" v-model="dngBatchDescription" placeholder="Ví dụ: Thu học phí còn thiếu của invoice chưa thanh toán" />
                    </div>

                    <div class="space-y-2">
                        <Label>Hạn thanh toán nội bộ *</Label>
                        <DatePicker v-model="dngBatchDueDate" placeholder="Chọn hạn thanh toán" />
                    </div>

                    <div class="space-y-2">
                        <Label>Thời hạn thanh toán (MM/YY)</Label>
                        <Popover v-model:open="estimateTimePickerOpen">
                            <PopoverTrigger as-child>
                                <Button variant="outline" class="w-full justify-start font-normal">
                                    <CalendarIcon class="mr-2 h-4 w-4" />
                                    {{ estimateTime }}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64 space-y-3 p-4" align="start">
                                <div class="space-y-1">
                                    <Label>Tháng</Label>
                                    <Select v-model="estimateMonth">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="m in estimateMonthOptions" :key="m.value" :value="m.value">{{ m.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-1">
                                    <Label>Năm</Label>
                                    <Select v-model="estimateYear">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="y in estimateYearOptions" :key="y.value" :value="y.value">{{ y.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button class="w-full" @click="applyEstimateTimeSelection">Áp dụng</Button>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="dngBatchDialogOpen = false">Hủy</Button>
                    <Button :disabled="dngBatchSubmitting || recordsToCreate.length === 0" @click="submitDngBatch">
                        <Loader2 v-if="dngBatchSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Gửi {{ recordsToCreate.length }} khoản phí → DNG
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <Label class="text-muted-foreground text-xs">Search</Label>
                        <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" placeholder="Search student or invoice..." class="w-64" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="text-muted-foreground text-xs">Readiness</Label>
                        <Select :model-value="filters.readiness" @update:model-value="(v) => setFilter('readiness', v)">
                            <SelectTrigger class="w-48">
                                <SelectValue placeholder="All unpaid students" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in readinessOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="text-xs text-transparent">Clear</Label>
                        <Button variant="outline" size="default" @click="clearAllFilters" :disabled="!hasActiveFilters">
                            <X class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </div>

                <DataTable
                    :data="props.students.data"
                    :columns="columns"
                    :loading="isLoading"
                    :show-column-toggle="false"
                    :initial-sort="currentSort ?? undefined"
                    :initial-direction="currentDirection ?? undefined"
                    empty-message="No unpaid invoice candidates matched the current filter."
                    @sort-change="handleSortChange"
                >
                    <template #cell-invoices="{ row }">
                        <div class="min-w-[340px] space-y-3">
                            <!-- Fee-type breakdown (shown when available) -->
                            <div v-if="row.original.fee_type_breakdown.length > 0">
                                <div class="mb-1 flex items-center gap-1">
                                    <span class="text-muted-foreground text-xs font-medium">Phân loại phí</span>
                                    <span class="text-muted-foreground/60 text-xs" title="latest_dng_request là thông tin cấp student, không phản ánh trạng thái DNG từng loại phí">ⓘ</span>
                                </div>
                                <div class="space-y-1.5">
                                    <div v-for="entry in row.original.fee_type_breakdown" :key="entry.fee_type" class="bg-muted/30 flex items-center justify-between gap-3 rounded-md border px-3 py-2 text-xs">
                                        <div class="space-y-0.5">
                                            <div class="font-medium">{{ entry.label }}</div>
                                            <div class="text-muted-foreground">
                                                Gốc: {{ formatCurrency(entry.gross) }}
                                                <template v-if="entry.discount > 0"> · Giảm: {{ formatCurrency(entry.discount) }} </template>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-red-600">{{ formatCurrency(entry.net_remaining) }}</span>
                                            <Badge v-if="entry.active_dng !== null && entry.active_dng.amount === entry.net_remaining" variant="outline" class="border-amber-200 bg-amber-50 text-xs text-amber-700"> DNG đang chờ </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice list -->
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
                        <div class="flex flex-col items-end gap-2">
                            <div v-if="row.original.latest_dng_request" class="flex items-center gap-2">
                                <Badge variant="outline" class="border-blue-200 bg-blue-50 text-blue-700">Has DNG request</Badge>
                                <Link v-if="permission.can('view_finance_dng_payment_requests')" :href="route('finance.dng.payment-requests.show', row.original.latest_dng_request.id)">
                                    <Button variant="ghost" size="sm">
                                        <ExternalLink class="mr-2 h-4 w-4" />
                                        View
                                    </Button>
                                </Link>
                            </div>

                            <Button v-if="row.original.actionable" size="sm" :disabled="isApplying" @click="applySettlement([row.original.student_id])">
                                <CheckCircle2 class="mr-2 h-4 w-4" />
                                Apply
                            </Button>

                            <Button v-else-if="permission.can('create_finance_payments')" size="sm" variant="outline" :disabled="getRecordsToCreateForStudent(row.original).length === 0" @click="openDngBatchDialog(row.original)">
                                <Wallet class="mr-2 h-4 w-4" />
                                Create DNG request
                            </Button>
                        </div>
                    </template>
                </DataTable>

                <DataPagination :pagination-data="props.students" item-name="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
