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
import { formatCurrency, type Semester } from '@/types/finance';
import { formatDateTime } from '@/utils/date';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, Bell, Calendar, CalendarClock, CalendarDays, CalendarX2, Clock, Download, Mail, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

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
}

interface DueSummary {
    upcoming_count: number;
    due_today_count: number;
    overdue_count: number;
    total_overdue_amount: number;
}

interface Props {
    invoices: PaginatedResponse<InvoiceDueItem>;
    summary: DueSummary;
    semesters: Semester[];
    filters: {
        semester_id: string | null;
        status: string;
        search: string;
    };
    currentSemester: Semester | null;
}

const props = defineProps<Props>();
const { showConfirmDialog } = useGlobalConfirmDialog();

// Local filter state
const semesterId = ref(props.filters.semester_id || (props.currentSemester?.id ? String(props.currentSemester.id) : 'all'));
const statusFilter = ref(props.filters.status || 'all');
const search = ref(props.filters.search || '');

// Search debounce
let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

watch([semesterId, statusFilter], () => {
    applyFilters();
});

const applyFilters = () => {
    router.get(
        route('finance.operations.due-calendar'),
        {
            semester_id: semesterId.value !== 'all' ? semesterId.value : undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            search: search.value || undefined,
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

// Format days remaining
const formatDaysRemaining = (days: number) => {
    if (days === 0) return 'Hôm nay';
    if (days < 0) return `Quá hạn ${Math.abs(days)} ngày`;
    return `Còn ${days} ngày`;
};

// Selected invoices for bulk actions
const selectedInvoices = ref<number[]>([]);
const toggleSelectAll = () => {
    if (selectedInvoices.value.length === props.invoices.data.length) {
        selectedInvoices.value = [];
    } else {
        selectedInvoices.value = props.invoices.data.map((i) => i.id);
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
const isExporting = ref(false);

// Flash messages are handled by router.post callbacks

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

const exportList = async () => {
    isExporting.value = true;

    try {
        window.open(
            route('finance.operations.export-due-list', {
                semester_id: semesterId.value !== 'all' ? semesterId.value : undefined,
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
                <p class="text-muted-foreground mt-1">Hàng đợi nhắc nợ DNG — không phải lịch tháng</p>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('finance.operations.lifecycle-exceptions')">
                    <Button variant="outline">Lifecycle Exceptions</Button>
                </Link>
                <Select v-model="semesterId">
                    <SelectTrigger class="w-[200px]">
                        <SelectValue placeholder="Chọn học kỳ" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Tất cả học kỳ</SelectItem>
                        <SelectItem v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                            {{ sem.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
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
                    <div class="flex items-center gap-3">
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
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="outline" :disabled="isExporting" @click="exportList">
                            <Download class="mr-2 h-4 w-4" />
                            Export Excel
                        </Button>
                        <Button :disabled="selectedInvoices.length === 0 || isSendingStudentReminders || isSendingParentReminders" @click="sendStudentReminders">
                            <Mail class="mr-2 h-4 w-4" />
                            {{ isSendingStudentReminders ? 'Đang gửi...' : `Gửi nhắc sinh viên (${selectedInvoices.length})` }}
                        </Button>
                        <Button :disabled="selectedInvoices.length === 0 || isSendingStudentReminders || isSendingParentReminders" @click="sendParentReminders">
                            <Mail class="mr-2 h-4 w-4" />
                            {{ isSendingParentReminders ? 'Đang gửi...' : `Gửi thông báo phụ huynh (${selectedInvoices.length})` }}
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
                                    :checked="selectedInvoices.length === invoices.data.length && invoices.data.length > 0"
                                    :indeterminate="selectedInvoices.length > 0 && selectedInvoices.length < invoices.data.length"
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
                                <input type="checkbox" :checked="selectedInvoices.includes(invoice.id)" class="h-4 w-4 rounded border-gray-300" @change="toggleInvoice(invoice.id)" />
                            </TableCell>
                            <TableCell>
                                <div class="font-medium">{{ invoice.invoice_number }}</div>
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
    </div>
</template>
