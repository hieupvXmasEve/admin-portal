<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, type Semester } from '@/types/finance';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Bell,
    Calendar,
    CalendarClock,
    CalendarDays,
    CalendarX2,
    Clock,
    Download,
    Mail,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface InvoiceDueItem {
    id: number;
    invoice_number: string;
    student_id: number;
    student_code: string;
    student_name: string;
    student_email: string;
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
        selectedInvoices.value = props.invoices.data.map(i => i.id);
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

const handleReminderResponse = async (response: Response, defaultErrorMessage: string) => {
    if (response.ok) {
        const data = await response.json();
        if (data.success) {
            if ((data.data.sent_count || 0) > 0) {
                toast.success(data.data.message || `Đã gửi ${data.data.sent_count} email`);
            } else {
                toast.warning(data.data.message || 'Không có email nào được gửi');
            }
            selectedInvoices.value = [];
            router.reload({ only: ['invoices'] });
        } else {
            toast.error(data.message || defaultErrorMessage);
        }
    } else {
        toast.error('Lỗi kết nối server');
    }
};

const sendStudentReminders = async () => {
    if (selectedInvoices.value.length === 0) {
        toast.error('Vui lòng chọn ít nhất một hóa đơn');
        return;
    }

    isSendingStudentReminders.value = true;

    try {
        const response = await fetch(route('api.finance.operations.send-reminders'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                invoice_ids: selectedInvoices.value,
            }),
        });

        await handleReminderResponse(response, 'Không thể gửi nhắc nợ cho sinh viên');
    } catch (error) {
        console.error('Send student reminders error:', error);
        toast.error('Lỗi khi gửi nhắc nợ cho sinh viên');
    } finally {
        isSendingStudentReminders.value = false;
    }
};

const sendParentReminders = async () => {
    if (selectedInvoices.value.length === 0) {
        toast.error('Vui lòng chọn ít nhất một hóa đơn');
        return;
    }

    isSendingParentReminders.value = true;

    try {
        const response = await fetch(route('api.finance.operations.send-parent-reminders'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                invoice_ids: selectedInvoices.value,
            }),
        });

        await handleReminderResponse(response, 'Không thể gửi thông báo cho phụ huynh');
    } catch (error) {
        console.error('Send parent reminders error:', error);
        toast.error('Lỗi khi gửi thông báo cho phụ huynh');
    } finally {
        isSendingParentReminders.value = false;
    }
};

const exportList = async () => {
    isExporting.value = true;

    try {
        window.open(route('finance.operations.export-due-list', {
            semester_id: semesterId.value !== 'all' ? semesterId.value : undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        }), '_blank');
    } catch (error) {
        console.error('Export error:', error);
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

    <Head title="Due Calendar" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Billing Calendar</h1>
                <p class="text-muted-foreground mt-1">Quản lý hạn thanh toán và gửi nhắc nợ cho sinh viên hoặc phụ huynh</p>
            </div>
            <div class="flex items-center gap-3">
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
            <Card :class="{ 'ring-2 ring-blue-500': statusFilter === 'upcoming' }" class="cursor-pointer"
                @click="statusFilter = 'upcoming'">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Sắp đến hạn</CardTitle>
                    <CalendarClock class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-blue-600">{{ summary.upcoming_count }}</div>
                    <p class="text-muted-foreground text-xs">Invoice trong 7 ngày tới</p>
                </CardContent>
            </Card>

            <Card :class="{ 'ring-2 ring-yellow-500': statusFilter === 'due_today' }" class="cursor-pointer"
                @click="statusFilter = 'due_today'">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Đến hạn hôm nay</CardTitle>
                    <CalendarDays class="h-4 w-4 text-yellow-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-yellow-600">{{ summary.due_today_count }}</div>
                    <p class="text-muted-foreground text-xs">Cần nhắc nhở ngay</p>
                </CardContent>
            </Card>

            <Card :class="{ 'ring-2 ring-red-500': statusFilter === 'overdue' }" class="cursor-pointer"
                @click="statusFilter = 'overdue'">
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
                    <div class="text-2xl font-bold text-orange-600">{{ formatCurrency(summary.total_overdue_amount) }}
                    </div>
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
                            <Search class="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input v-model="search" placeholder="Tìm sinh viên, mã hóa đơn..."
                                class="w-[250px] pl-10" />
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
                    Danh sách Invoice
                    <Badge variant="outline" class="ml-2">{{ invoices.total || 0 }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">
                                <input type="checkbox"
                                    :checked="selectedInvoices.length === invoices.data.length && invoices.data.length > 0"
                                    :indeterminate="selectedInvoices.length > 0 && selectedInvoices.length < invoices.data.length"
                                    class="h-4 w-4 rounded border-gray-300" @change="toggleSelectAll" />
                            </TableHead>
                            <TableHead>Invoice</TableHead>
                            <TableHead>Sinh viên</TableHead>
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
                            <TableCell colspan="10" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <Calendar class="text-muted-foreground h-12 w-12" />
                                    <p class="text-muted-foreground">Không có invoice nào phù hợp</p>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="invoice in invoices.data" :key="invoice.id"
                            :class="{ 'bg-red-50': invoice.status === 'overdue', 'bg-yellow-50': invoice.status === 'due_today' }">
                            <TableCell>
                                <input type="checkbox" :checked="selectedInvoices.includes(invoice.id)"
                                    class="h-4 w-4 rounded border-gray-300" @change="toggleInvoice(invoice.id)" />
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
                            <TableCell class="text-right font-medium">
                                {{ formatCurrency(invoice.total_amount) }}
                            </TableCell>
                            <TableCell class="text-right text-green-600">
                                {{ formatCurrency(invoice.paid_amount) }}
                            </TableCell>
                            <TableCell class="text-right font-medium"
                                :class="invoice.balance > 0 ? 'text-red-600' : ''">
                                {{ formatCurrency(invoice.balance) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Clock class="h-4 w-4" :class="{
                                        'text-blue-500': invoice.status === 'upcoming',
                                        'text-yellow-500': invoice.status === 'due_today',
                                        'text-red-500': invoice.status === 'overdue',
                                    }" />
                                    <div>
                                        <div class="text-sm">{{ new Date(invoice.due_date).toLocaleDateString('vi-VN')
                                        }}</div>
                                        <div class="text-xs" :class="{
                                            'text-blue-600': invoice.days_until_due > 0,
                                            'text-yellow-600': invoice.days_until_due === 0,
                                            'text-red-600': invoice.days_until_due < 0,
                                        }">
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
                                    <span class="text-xs">{{ new
                                        Date(invoice.last_reminder_at).toLocaleDateString('vi-VN') }}</span>
                                </div>
                                <div v-else class="text-muted-foreground text-xs">Chưa gửi</div>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('finance.invoices.show', invoice.id)">
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
