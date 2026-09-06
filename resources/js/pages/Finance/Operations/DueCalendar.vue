<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResponse } from '@/types';
import { formatCurrency } from '@/types/finance';
import { formatDateTime } from '@/utils/date';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, Bell, Calendar, CalendarClock, CalendarDays, CalendarX2, Clock, Download, ExternalLink, FileText, GraduationCap, Mail, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type ReminderState = 'remindable' | 'needs_charge_or_dng' | 'blocked';

interface RoomRef {
    name: string | null;
    code: string | null;
}

interface InvoiceDueItem {
    id: number;
    invoice_number: string;
    student_id: number;
    student_code: string;
    student_name: string;
    student_email: string;
    student_status_label: string | null;
    student_status_color: string | null;
    total_amount: number;
    paid_amount: number;
    balance: number;
    due_date: string;
    days_until_due: number;
    status: 'upcoming' | 'due_today' | 'overdue' | 'paid';
    last_reminder_at: string | null;
    fee_type?: string | null;
    source_type?: string | null;
    source_id?: number | null;
    reminder_state?: ReminderState;
    blocked_reason?: string | null;
    unit_code?: string | null;
    unit_name?: string | null;
    exam_date?: string | null;
    exam_start_time?: string | null;
    exam_end_time?: string | null;
    room?: RoomRef | null;
    days_overdue?: number | null;
}

interface HandoffItem {
    id: number;
    source_type: string;
    source_id: number;
    fee_type: string;
    student_id: number;
    student_code: string | null;
    student_name: string | null;
    student_status_label: string | null;
    student_status_color: string | null;
    amount: number;
    unit_code: string | null;
    unit_name: string | null;
    exam_date: string | null;
    exam_start_time: string | null;
    days_overdue: number | null;
    last_reminder_at: string | null;
    handoff: { route_name: string; label: string };
}

interface DueSummary {
    upcoming_count: number;
    due_today_count: number;
    overdue_count: number;
    total_overdue_amount: number;
}

interface Props {
    invoices: PaginatedResponse<InvoiceDueItem>;
    handoffItems: PaginatedResponse<HandoffItem> | null;
    summary: DueSummary;
    filters: {
        status: string;
        search: string;
        source: string;
        fee_type: string;
    };
}

const props = defineProps<Props>();
const { showConfirmDialog } = useGlobalConfirmDialog();

// Local filter state. Semester is driven by the global top-bar SemesterSwitcher
// (shared `semester` prop), not a per-page select.
const statusFilter = ref(props.filters.status || 'all');
const search = ref(props.filters.search || '');
const sourceFilter = ref(props.filters.source || 'all');
const feeTypeFilter = ref(props.filters.fee_type || 'all');

// Search debounce
let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

watch([statusFilter, sourceFilter, feeTypeFilter], () => {
    applyFilters();
});

const applyFilters = () => {
    router.get(
        route('finance.operations.due-calendar'),
        {
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            search: search.value || undefined,
            source: sourceFilter.value !== 'all' ? sourceFilter.value : undefined,
            fee_type: feeTypeFilter.value !== 'all' ? feeTypeFilter.value : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true });
};

// Get status badge
const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'upcoming':
            return 'bg-blue-100 text-blue-800';
        case 'due_today':
            return 'bg-yellow-100 text-yellow-800';
        case 'overdue':
            return 'bg-red-100 text-red-800';
        case 'paid':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'upcoming':
            return 'Sắp đến hạn';
        case 'due_today':
            return 'Đến hạn hôm nay';
        case 'overdue':
            return 'Quá hạn';
        case 'paid':
            return 'Đã thanh toán';
        default:
            return status;
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'upcoming':
            return CalendarClock;
        case 'due_today':
            return CalendarDays;
        case 'overdue':
            return CalendarX2;
        case 'paid':
            return Calendar;
        default:
            return Calendar;
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

// Reminder-state presentation for exam-resit (PTL) rows.
const blockedReasonLabel = (reason?: string | null) => {
    switch (reason) {
        case 'paid':
            return 'Đã thanh toán';
        case 'cancelled':
            return 'Đã hủy';
        case 'lifecycle_exception':
            return 'Ngoại lệ lifecycle';
        case 'missing_email':
            return 'Thiếu email';
        default:
            return 'Không thể nhắc';
    }
};

const isRemindable = (row: InvoiceDueItem) => !row.reminder_state || row.reminder_state === 'remindable';

const isExamResit = (row: InvoiceDueItem) => row.source_type === 'exam_resit' || row.fee_type === 'PTL';

// Format days remaining
const formatDaysRemaining = (days: number) => {
    if (days === 0) return 'Hôm nay';
    if (days < 0) return `Quá hạn ${Math.abs(days)} ngày`;
    return `Còn ${days} ngày`;
};

// Selected invoices for bulk actions — only remindable rows can be selected.
const selectedInvoices = ref<number[]>([]);
const remindableRows = () => props.invoices.data.filter((i) => isRemindable(i));

const toggleSelectAll = () => {
    const ids = remindableRows().map((i) => i.id);
    if (selectedInvoices.value.length === ids.length && ids.length > 0) {
        selectedInvoices.value = [];
    } else {
        selectedInvoices.value = ids;
    }
};

const toggleInvoice = (id: number) => {
    const index = selectedInvoices.value.indexOf(id);
    if (index > -1) {
        selectedInvoices.value.splice(index, 1);
    } else {
        selectedInvoices.value.push(id);
    }
};

// Bulk actions
const isSendingStudentReminders = ref(false);
const isSendingParentReminders = ref(false);
const isSendingTuitionNotices = ref(false);
const isExporting = ref(false);

const sendStudentReminders = () => {
    if (selectedInvoices.value.length === 0) {
        toast.error('Vui lòng chọn ít nhất một mục');
        return;
    }

    showConfirmDialog(
        {
            title: 'Gửi nhắc nợ sinh viên',
            message: `Gửi email nhắc nợ cho ${selectedInvoices.value.length} mục đã chọn? Email đã gửi không thể thu hồi.`,
            confirmText: 'Gửi nhắc nợ',
            cancelText: 'Huỷ bỏ',
        },
        {
            onConfirm: () => runSendStudentReminders(),
        },
    );
};

const runSendStudentReminders = () => {
    // Convert selected IDs to item_ids format (dng_request:id)
    const itemIds = selectedInvoices.value.map((id) => `dng_request:${id}`);

    router.post(
        route('api.finance.operations.send-due-item-reminders'),
        {
            item_ids: itemIds,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isSendingStudentReminders.value = true;
            },
            onFinish: () => {
                isSendingStudentReminders.value = false;
            },
            onSuccess: () => {
                // Flash messages are handled automatically by useFlashToast in AppLayout
                selectedInvoices.value = [];
            },
            onError: () => {
                toast.error('Lỗi khi gửi nhắc nợ cho sinh viên');
            },
        },
    );
};

const sendParentReminders = () => {
    if (selectedInvoices.value.length === 0) {
        toast.error('Vui lòng chọn ít nhất một mục');
        return;
    }

    showConfirmDialog(
        {
            title: 'Gửi thông báo phụ huynh',
            message: `Gửi email thông báo cho phụ huynh của ${selectedInvoices.value.length} mục đã chọn? Email đã gửi không thể thu hồi.`,
            confirmText: 'Gửi thông báo',
            cancelText: 'Huỷ bỏ',
        },
        {
            onConfirm: () => runSendParentReminders(),
        },
    );
};

const runSendParentReminders = () => {
    // Convert selected IDs to item_ids format (dng_request:id)
    const itemIds = selectedInvoices.value.map((id) => `dng_request:${id}`);

    router.post(
        route('api.finance.operations.send-due-item-parent-reminders'),
        {
            item_ids: itemIds,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isSendingParentReminders.value = true;
            },
            onFinish: () => {
                isSendingParentReminders.value = false;
            },
            onSuccess: () => {
                // Flash messages are handled automatically by useFlashToast in AppLayout
                selectedInvoices.value = [];
            },
            onError: () => {
                toast.error('Lỗi khi gửi thông báo cho phụ huynh');
            },
        },
    );
};

const selectedStudentIds = (): number[] => {
    const ids = new Set<number>();
    for (const row of props.invoices.data) {
        if (selectedInvoices.value.includes(row.id)) {
            ids.add(row.student_id);
        }
    }
    return [...ids];
};

const sendTuitionNotices = () => {
    if (selectedInvoices.value.length === 0) {
        toast.error('Vui lòng chọn ít nhất một mục');
        return;
    }

    const studentIds = selectedStudentIds();
    if (studentIds.length === 0) {
        toast.error('Vui lòng chọn ít nhất một sinh viên');
        return;
    }

    showConfirmDialog(
        {
            title: 'Phát thông báo học phí',
            message: `Phát thông báo học phí cho ${studentIds.length} sinh viên đã chọn (sinh viên và phụ huynh)? Email đã gửi không thể thu hồi.`,
            confirmText: 'Phát thông báo',
            cancelText: 'Huỷ bỏ',
        },
        {
            onConfirm: () => runSendTuitionNotices(studentIds),
        },
    );
};

const runSendTuitionNotices = (studentIds: number[]) => {
    router.post(
        route('api.finance.operations.send-tuition-notices'),
        {
            student_ids: studentIds,
        },
        {
            preserveScroll: true,
            onStart: () => {
                isSendingTuitionNotices.value = true;
            },
            onFinish: () => {
                isSendingTuitionNotices.value = false;
            },
            onSuccess: () => {
                selectedInvoices.value = [];
            },
            onError: () => {
                toast.error('Lỗi khi phát thông báo học phí');
            },
        },
    );
};

const exportList = async () => {
    isExporting.value = true;

    try {
        window.open(
            route('finance.operations.export-due-list', {
                status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            }),
            '_blank',
        );
    } catch {
        toast.error('Lỗi khi xuất danh sách');
    } finally {
        isExporting.value = false;
    }
};

const statusOptions = [
    { value: 'all', label: 'Tất cả trạng thái' },
    { value: 'upcoming', label: 'Sắp đến hạn' },
    { value: 'due_today', label: 'Đến hạn hôm nay' },
    { value: 'overdue', label: 'Quá hạn' },
];

const sourceOptions = [
    { value: 'all', label: 'Tất cả nguồn' },
    { value: 'dng_request', label: 'DNG thường' },
    { value: 'exam_resit', label: 'Thi lại (PTL)' },
];

const feeTypeOptions = [
    { value: 'all', label: 'Tất cả loại phí' },
    { value: 'PTL', label: 'PTL · Thi lại' },
    { value: 'HP', label: 'HP · Học phí' },
    { value: 'HL', label: 'HL · Học lại' },
];

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="DNG Due Reminders" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">DNG Due Reminders</h1>
                <p class="text-muted-foreground mt-1">Hàng đợi nhắc nợ DNG — bao gồm phí thi lại (PTL) quá hạn</p>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('finance.operations.lifecycle-exceptions')">
                    <Button variant="outline">Lifecycle Exceptions</Button>
                </Link>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <Card :class="{ 'ring-2 ring-blue-500': statusFilter === 'upcoming' }" class="cursor-pointer" @click="statusFilter = 'upcoming'">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Sắp đến hạn</CardTitle>
                    <CalendarClock class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-blue-600">{{ summary.upcoming_count }}</div>
                    <p class="text-muted-foreground text-xs">DNG Request trong 7 ngày tới</p>
                </CardContent>
            </Card>

            <Card :class="{ 'ring-2 ring-yellow-500': statusFilter === 'due_today' }" class="cursor-pointer" @click="statusFilter = 'due_today'">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Đến hạn hôm nay</CardTitle>
                    <CalendarDays class="h-4 w-4 text-yellow-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-yellow-600">{{ summary.due_today_count }}</div>
                    <p class="text-muted-foreground text-xs">Cần nhắc nhở ngay</p>
                </CardContent>
            </Card>

            <Card :class="{ 'ring-2 ring-red-500': statusFilter === 'overdue' }" class="cursor-pointer" @click="statusFilter = 'overdue'">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Quá hạn</CardTitle>
                    <CalendarX2 class="h-4 w-4 text-red-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-red-600">{{ summary.overdue_count }}</div>
                    <p class="text-muted-foreground text-xs">Cần xử lý gấp</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Tổng nợ quá hạn</CardTitle>
                    <AlertTriangle class="h-4 w-4 text-orange-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-orange-600">{{ formatCurrency(summary.total_overdue_amount) }}</div>
                    <p class="text-muted-foreground text-xs">Số tiền cần thu hồi</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filters & Actions -->
        <Card>
            <CardContent class="pt-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input v-model="search" placeholder="Tìm sinh viên, mã DNG request..." class="w-[250px] pl-10" />
                        </div>
                        <Select v-model="statusFilter">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="sourceFilter">
                            <SelectTrigger class="w-[170px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="feeTypeFilter">
                            <SelectTrigger class="w-[170px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in feeTypeOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="outline" :disabled="isExporting" @click="exportList">
                            <Download class="mr-2 h-4 w-4" />
                            Export Excel
                        </Button>
                        <Button :disabled="selectedInvoices.length === 0 || isSendingStudentReminders || isSendingParentReminders || isSendingTuitionNotices" @click="sendStudentReminders">
                            <Mail class="mr-2 h-4 w-4" />
                            {{ isSendingStudentReminders ? 'Đang gửi...' : `Gửi nhắc sinh viên (${selectedInvoices.length})` }}
                        </Button>
                        <Button :disabled="selectedInvoices.length === 0 || isSendingStudentReminders || isSendingParentReminders || isSendingTuitionNotices" @click="sendParentReminders">
                            <Mail class="mr-2 h-4 w-4" />
                            {{ isSendingParentReminders ? 'Đang gửi...' : `Gửi thông báo phụ huynh (${selectedInvoices.length})` }}
                        </Button>
                        <Button :disabled="selectedInvoices.length === 0 || isSendingStudentReminders || isSendingParentReminders || isSendingTuitionNotices" @click="sendTuitionNotices">
                            <FileText class="mr-2 h-4 w-4" />
                            {{ isSendingTuitionNotices ? 'Đang phát...' : 'Phát thông báo học phí' }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Invoices Table -->
        <Card>
            <CardHeader>
                <CardTitle>
                    Danh sách DNG Request đến hạn
                    <Badge variant="outline" class="ml-2">{{ invoices.total || 0 }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">
                                <input
                                    type="checkbox"
                                    :checked="selectedInvoices.length === remindableRows().length && remindableRows().length > 0"
                                    :indeterminate="selectedInvoices.length > 0 && selectedInvoices.length < remindableRows().length"
                                    class="h-4 w-4 rounded border-gray-300"
                                    @change="toggleSelectAll"
                                />
                            </TableHead>
                            <TableHead>DNG Request</TableHead>
                            <TableHead>Sinh viên</TableHead>
                            <TableHead>Trạng thái SV</TableHead>
                            <TableHead class="text-right">Số tiền</TableHead>
                            <TableHead class="text-right">Đã trả</TableHead>
                            <TableHead class="text-right">Còn nợ</TableHead>
                            <TableHead>Hạn thanh toán</TableHead>
                            <TableHead>Trạng thái</TableHead>
                            <TableHead>Nhắc nhở</TableHead>
                            <TableHead class="text-right">Thao tác</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="invoices.data.length === 0">
                            <TableCell colspan="11" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <Calendar class="text-muted-foreground h-12 w-12" />
                                    <p class="text-muted-foreground">Không có DNG request nào phù hợp</p>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="invoice in invoices.data" :key="invoice.id" :class="{ 'bg-red-50': invoice.status === 'overdue', 'bg-yellow-50': invoice.status === 'due_today' }">
                            <TableCell>
                                <input
                                    type="checkbox"
                                    :checked="selectedInvoices.includes(invoice.id)"
                                    :disabled="!isRemindable(invoice)"
                                    class="h-4 w-4 rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-40"
                                    @change="toggleInvoice(invoice.id)"
                                />
                            </TableCell>
                            <TableCell>
                                <div class="font-medium">{{ invoice.invoice_number }}</div>
                                <div v-if="isExamResit(invoice)" class="mt-1 flex flex-wrap items-center gap-1">
                                    <Badge class="bg-purple-100 text-purple-800">
                                        <GraduationCap class="mr-1 h-3 w-3" />
                                        PTL
                                    </Badge>
                                    <span v-if="invoice.unit_code" class="text-muted-foreground text-xs">{{ invoice.unit_code }}</span>
                                </div>
                                <div v-if="isExamResit(invoice) && invoice.exam_date" class="text-muted-foreground mt-0.5 text-xs">
                                    Thi: {{ new Date(invoice.exam_date).toLocaleDateString('vi-VN') }}
                                    <span v-if="invoice.exam_start_time">· {{ invoice.exam_start_time }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div>
                                    <div class="font-medium">{{ invoice.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ invoice.student_code }}</div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge v-if="invoice.student_status_label" variant="outline" :class="getStudentStatusClass(invoice.student_status_color ?? 'gray')">
                                    {{ invoice.student_status_label }}
                                </Badge>
                                <span v-else class="text-muted-foreground text-xs">—</span>
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ formatCurrency(invoice.total_amount) }}
                            </TableCell>
                            <TableCell class="text-right text-green-600">
                                {{ formatCurrency(invoice.paid_amount) }}
                            </TableCell>
                            <TableCell class="text-right font-medium" :class="invoice.balance > 0 ? 'text-red-600' : ''">
                                {{ formatCurrency(invoice.balance) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Clock
                                        class="h-4 w-4"
                                        :class="{
                                            'text-blue-500': invoice.status === 'upcoming',
                                            'text-yellow-500': invoice.status === 'due_today',
                                            'text-red-500': invoice.status === 'overdue',
                                        }"
                                    />
                                    <div>
                                        <div class="text-sm">{{ new Date(invoice.due_date).toLocaleDateString('vi-VN') }}</div>
                                        <div
                                            class="text-xs"
                                            :class="{
                                                'text-blue-600': invoice.days_until_due > 0,
                                                'text-yellow-600': invoice.days_until_due === 0,
                                                'text-red-600': invoice.days_until_due < 0,
                                            }"
                                        >
                                            {{ formatDaysRemaining(invoice.days_until_due) }}
                                        </div>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :class="getStatusBadgeClass(invoice.status)">
                                    <component :is="getStatusIcon(invoice.status)" class="mr-1 h-3 w-3" />
                                    {{ getStatusLabel(invoice.status) }}
                                </Badge>
                                <Badge v-if="!isRemindable(invoice)" class="mt-1 block w-fit bg-slate-200 text-slate-700">
                                    {{ blockedReasonLabel(invoice.blocked_reason) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div v-if="invoice.last_reminder_at" class="flex items-center gap-1 text-green-600">
                                    <Bell class="h-3 w-3" />
                                    <span class="text-xs">{{ formatDateTime(invoice.last_reminder_at) }}</span>
                                </div>
                                <div v-else class="text-muted-foreground text-xs">Chưa gửi</div>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('finance.dng.payment-requests.show', invoice.id)">
                                        <Button variant="ghost" size="sm">
                                            <ArrowRight class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Pagination -->
        <DataPagination :pagination-data="invoices" @navigate="handlePaginationNavigate" />

        <!-- Exam-resit handoff: overdue PTL sources still needing charge/DNG creation -->
        <Card v-if="handoffItems && handoffItems.total > 0" class="border-amber-200">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 text-amber-500" />
                    Thi lại quá hạn cần tạo phí / DNG
                    <Badge variant="outline" class="ml-1">{{ handoffItems.total }}</Badge>
                </CardTitle>
                <p class="text-muted-foreground text-sm">Các lần thi lại đã quá hạn nhưng chưa có DNG để nhắc — chuyển sang luồng tạo DNG.</p>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Môn / Lịch thi</TableHead>
                            <TableHead>Sinh viên</TableHead>
                            <TableHead class="text-right">Lệ phí</TableHead>
                            <TableHead>Quá hạn</TableHead>
                            <TableHead class="text-right">Thao tác</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in handoffItems.data" :key="`handoff-${item.id}`">
                            <TableCell>
                                <div class="flex items-center gap-1 font-medium">
                                    <GraduationCap class="h-4 w-4 text-purple-500" />
                                    {{ item.unit_code ?? '—' }}
                                </div>
                                <div class="text-muted-foreground text-xs">{{ item.unit_name }}</div>
                                <div v-if="item.exam_date" class="text-muted-foreground text-xs">
                                    Thi: {{ new Date(item.exam_date).toLocaleDateString('vi-VN') }}
                                    <span v-if="item.exam_start_time">· {{ item.exam_start_time }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="font-medium">{{ item.student_name }}</div>
                                <div class="text-muted-foreground text-xs">{{ item.student_code }}</div>
                            </TableCell>
                            <TableCell class="text-right font-medium">{{ formatCurrency(item.amount) }}</TableCell>
                            <TableCell>
                                <Badge class="bg-red-100 text-red-800">
                                    <CalendarX2 class="mr-1 h-3 w-3" />
                                    {{ item.days_overdue ?? 0 }} ngày
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Link :href="route(item.handoff.route_name)">
                                    <Button variant="outline" size="sm">
                                        <ExternalLink class="mr-2 h-4 w-4" />
                                        {{ item.handoff.label }}
                                    </Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <DataPagination v-if="handoffItems && handoffItems.total > 0" :pagination-data="handoffItems" @navigate="handlePaginationNavigate" />
    </div>
</template>
