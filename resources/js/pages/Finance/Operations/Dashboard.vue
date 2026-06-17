<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, type Semester } from '@/types/finance';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertTriangle, ArrowRight, CheckCircle2, DollarSign, FileText, Play, RotateCcw, Wallet, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface KpiStats {
    eligible_count: number;
    charged_count: number;
    uncharged_count: number;
    paid_count: number;
    unpaid_count: number;
    partial_paid_count: number;
    total_charges: number;
    total_credits: number;
    total_paid: number;
    total_balance: number;
    defer_preserve_count: number;
    defer_forfeit_count: number;
    retake_unpaid_count: number;
}

interface StudentBillingSummary {
    id: number;
    student_id: string;
    full_name: string;
    program_code: string | null;
    intake_semester: string | null;
    status: string;
    stage: 'EGC' | 'Major' | 'Unknown';
    gc_current_level: string | null;
    gross_billed: number;
    total_discounts: number;
    net_due: number;
    cash_applied: number;
    outstanding_amount: number;
    unapplied_cash: number;
    breakdown: {
        major: number;
        egc: number;
        retake: number;
        discounts: number;
    };
    flags: {
        has_retake: boolean;
        is_defer_preserve: boolean;
        is_defer_forfeit: boolean;
        missing_docs: boolean;
        uncharged: boolean;
    };
    invoices: {
        id: number;
        invoice_number: string;
        status: string;
        due_date: string | null;
    }[];
    invoice_statuses: string[];
    invoice_status: string | null;
    invoice_number: string | null;
    invoice_id: number | null;
    due_date: string | null;
}

interface DashboardFilters {
    semester_id: string | null;
    status: string;
    stage: string;
    defer: string;
    retake: string;
    search: string;
    per_page: number;
    page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface Props {
    kpiStats: KpiStats;
    students: PaginatedResponse<StudentBillingSummary>;
    semesters: Semester[];
    filters: Partial<DashboardFilters>;
    currentSemester: Semester | null;
}

const props = defineProps<Props>();

// Use Inertia filters composable
const { filters, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useInertiaFilters<DashboardFilters>({
    baseUrl: route('finance.operations.dashboard'),
    initialFilters: {
        semester_id: typeof props.filters?.semester_id === 'string' ? props.filters.semester_id : null,
        status: (typeof props.filters?.status === 'string' ? props.filters.status : 'all') || 'all',
        stage: (typeof props.filters?.stage === 'string' ? props.filters.stage : 'all') || 'all',
        defer: (typeof props.filters?.defer === 'string' ? props.filters.defer : 'all') || 'all',
        retake: (typeof props.filters?.retake === 'string' ? props.filters.retake : 'all') || 'all',
        search: (typeof props.filters?.search === 'string' ? props.filters.search : '') || '',
        per_page: props.filters?.per_page || 20,
        sort: (typeof props.filters?.sort === 'string' ? props.filters.sort : 'full_name') || 'full_name',
        direction: (props.filters?.direction as 'asc' | 'desc') || 'asc',
        page: props.students.current_page || 1,
    },
    defaultValues: {
        semester_id: null,
        status: 'all',
        stage: 'all',
        defer: 'all',
        retake: 'all',
        per_page: 20,
        sort: null,
        direction: 'asc',
    },
    only: ['students', 'filters', 'kpiStats'],
    debounce: 400,
});

// Define columns for DataTable
const columns: ColumnDef<StudentBillingSummary>[] = [
    {
        accessorKey: 'full_name',
        header: 'Sinh viên',
        enableSorting: true,
    },
    {
        accessorKey: 'program_code',
        header: 'Chương trình',
    },
    // header align right
    {
        accessorKey: 'gross_billed',
        header: () => 'Gross',
        enableSorting: true,
    },
    {
        accessorKey: 'total_discounts',
        header: () => 'Discounts',
        enableSorting: false,
    },
    {
        accessorKey: 'cash_applied',
        header: () => 'Cash Applied',
        enableSorting: true,
    },
    {
        accessorKey: 'outstanding_amount',
        header: () => 'Còn phải thu',
        enableSorting: true,
    },
    {
        id: 'flags',
        header: 'Flags',
    },
    {
        accessorKey: 'invoice_status',
        header: 'Trạng thái',
    },
    {
        id: 'actions',
        header: () => '',
    },
];

// Computed percentage values
const chargedPercentage = computed(() => {
    if (props.kpiStats.eligible_count === 0) return 0;
    return Math.round((props.kpiStats.charged_count / props.kpiStats.eligible_count) * 100);
});

const paidPercentage = computed(() => {
    if (props.kpiStats.charged_count === 0) return 0;
    return Math.round((props.kpiStats.paid_count / props.kpiStats.charged_count) * 100);
});

// Get invoice status badge
const getStatusBadgeClass = (status: string | null) => {
    switch (status) {
        case 'draft':
            return 'bg-slate-100 text-slate-800';
        case 'overdue':
            return 'bg-orange-100 text-orange-800';
        case 'cancelled':
            return 'bg-zinc-100 text-zinc-600';
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'partial':
            return 'bg-yellow-100 text-yellow-800';
        case 'unpaid':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-500';
    }
};

const getStatusLabel = (status: string | null) => {
    switch (status) {
        case 'draft':
            return 'Nháp';
        case 'overdue':
            return 'Quá hạn';
        case 'cancelled':
            return 'Đã hủy';
        case 'paid':
            return 'Đã thanh toán';
        case 'partial':
            return 'Thanh toán một phần';
        case 'unpaid':
            return 'Chưa thanh toán';
        case 'pending':
            return 'Chờ xử lý';
        default:
            return 'Chưa có hóa đơn';
    }
};

const statusOptions = [
    { value: 'all', label: 'Tất cả trạng thái' },
    { value: 'paid', label: 'Đã thanh toán' },
    { value: 'partial', label: 'Thanh toán một phần' },
    { value: 'unpaid', label: 'Chưa thanh toán' },
    { value: 'pending', label: 'Chờ xử lý' },
    { value: 'overdue', label: 'Quá hạn' },
    { value: 'draft', label: 'Nháp' },
    { value: 'cancelled', label: 'Đã hủy' },
    { value: 'no_invoice', label: 'Chưa có hóa đơn' },
];

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Billing Dashboard" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Billing Dashboard</h1>
                <p class="text-muted-foreground mt-1">Tổng quan billing theo kỳ học</p>
            </div>
            <div class="flex items-center gap-3">
                <Select :model-value="filters.semester_id || 'all'" @update:model-value="(val) => handleSelectFilter('semester_id', val)">
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

        <!-- KPI Cards -->
        <!-- ... (cards remain the same, just keeping the structure) ... -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Eligible / Charged -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Eligible → Charged</CardTitle>
                    <FileText class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ kpiStats.charged_count }} / {{ kpiStats.eligible_count }}</div>
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-blue-500" :style="{ width: chargedPercentage + '%' }"></div>
                        </div>
                        <span class="text-muted-foreground text-xs">{{ chargedPercentage }}%</span>
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">
                        <span class="text-orange-600">{{ kpiStats.uncharged_count }}</span> chưa có invoice trong kỳ
                    </p>
                </CardContent>
            </Card>

            <!-- Paid / Unpaid -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Fully Settled</CardTitle>
                    <Wallet class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ kpiStats.paid_count }} / {{ kpiStats.charged_count }}</div>
                    <div class="flex items-center gap-2">
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-green-500" :style="{ width: paidPercentage + '%' }"></div>
                        </div>
                        <span class="text-muted-foreground text-xs">{{ paidPercentage }}%</span>
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">
                        <span class="text-yellow-600">{{ kpiStats.partial_paid_count }}</span> thanh toán một phần, <span class="text-red-600">{{ kpiStats.unpaid_count }}</span> chưa thanh toán
                    </p>
                </CardContent>
            </Card>

            <!-- Financial Summary -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Tổng tiền</CardTitle>
                    <DollarSign class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="space-y-1">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Gross billed:</span>
                            <span class="font-medium text-red-600">{{ formatCurrency(kpiStats.total_charges) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Discounts:</span>
                            <span class="font-medium text-green-600">{{ formatCurrency(kpiStats.total_credits) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Cash applied:</span>
                            <span class="font-medium text-blue-600">{{ formatCurrency(kpiStats.total_paid) }}</span>
                        </div>
                        <div class="my-1 border-t"></div>
                        <div class="flex justify-between text-sm font-semibold">
                            <span>Outstanding debt:</span>
                            <span class="text-orange-600">{{ formatCurrency(kpiStats.total_balance) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Special Cases -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Trường hợp đặc biệt</CardTitle>
                    <AlertTriangle class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <RotateCcw class="h-4 w-4 text-orange-500" />
                                <span class="text-sm">Retake chưa thanh toán</span>
                            </div>
                            <Badge variant="outline" class="bg-orange-50">{{ kpiStats.retake_unpaid_count }}</Badge>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 text-green-500" />
                                <span class="text-sm">Defer (Preserve)</span>
                            </div>
                            <Badge variant="outline" class="bg-green-50">{{ kpiStats.defer_preserve_count }}</Badge>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <XCircle class="h-4 w-4 text-red-500" />
                                <span class="text-sm">Defer (Forfeit)</span>
                            </div>
                            <Badge variant="outline" class="bg-red-50">{{ kpiStats.defer_forfeit_count }}</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Quick Actions -->
        <Card>
            <CardHeader>
                <CardTitle>Thao tác nhanh</CardTitle>
                <CardDescription>Các tác vụ billing thường dùng</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 md:grid-cols-4">
                    <Link :href="route('finance.batch-studio.charges')">
                        <Button variant="outline" class="w-full justify-start">
                            <Play class="mr-2 h-4 w-4" />
                            Sinh phí hàng loạt
                        </Button>
                    </Link>
                    <Link :href="route('finance.charges.create')">
                        <Button variant="outline" class="w-full justify-start">
                            <DollarSign class="mr-2 h-4 w-4" />
                            Tạo phí thủ công
                        </Button>
                    </Link>
                    <!-- <Link :href="route('finance.payments.create')">
                        <Button variant="outline" class="w-full justify-start">
                            <CreditCard class="mr-2 h-4 w-4" />
                            Ghi nhận thanh toán
                        </Button>
                    </Link> -->
                    <Link :href="route('finance.operations.exceptions')">
                        <Button variant="outline" class="w-full justify-start">
                            <AlertTriangle class="mr-2 h-4 w-4" />
                            Xem Exceptions ({{ kpiStats.uncharged_count }})
                        </Button>
                    </Link>
                    <Link :href="route('finance.operations.settlement.index')">
                        <Button variant="outline" class="w-full justify-start">
                            <Wallet class="mr-2 h-4 w-4" />
                            Settlement Worklist
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>

        <!-- Filters Row -->
        <div class="bg-muted/50 flex flex-wrap gap-4 rounded-lg border p-4">
            <div class="min-w-[200px] flex-1">
                <Input v-model="filters.search" placeholder="Tìm theo tên hoặc mã SV" />
            </div>

            <div class="min-w-[150px] flex-1">
                <Select :model-value="filters.status || 'all'" @update:model-value="(val) => handleSelectFilter('status', val)">
                    <SelectTrigger>
                        <SelectValue placeholder="Finance Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="min-w-[150px] flex-1">
                <Select :model-value="filters.stage || 'all'" @update:model-value="(val) => handleSelectFilter('stage', val)">
                    <SelectTrigger>
                        <SelectValue placeholder="Stage" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Stages</SelectItem>
                        <SelectItem value="egc">EGC</SelectItem>
                        <SelectItem value="major">Major</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="min-w-[150px] flex-1">
                <Select :model-value="filters.defer || 'all'" @update:model-value="(val) => handleSelectFilter('defer', val)">
                    <SelectTrigger>
                        <SelectValue placeholder="Defer Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Defer</SelectItem>
                        <SelectItem value="preserve">Preserve</SelectItem>
                        <SelectItem value="forfeit">Forfeit</SelectItem>
                        <SelectItem value="missing_docs">Missing Docs</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="min-w-[150px] flex-1">
                <Select :model-value="filters.retake || 'all'" @update:model-value="(val) => handleSelectFilter('retake', val)">
                    <SelectTrigger>
                        <SelectValue placeholder="Retake Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Retake</SelectItem>
                        <SelectItem value="retake_unpaid">Retake Unpaid</SelectItem>
                        <SelectItem value="retake_charged">Retake Charged</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Student Billing Summary Table -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Danh sách sinh viên</CardTitle>
                        <CardDescription>1 dòng / 1 sinh viên, hiển thị đầy đủ invoice trong kỳ</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <DataTable :data="students.data" :columns="columns" :show-column-toggle="false" :initial-sort="currentSort" :initial-direction="currentDirection" enable-server-sorting @sort-change="handleSortChange">
                    <!-- Student Column -->
                    <template #cell-full_name="{ row }">
                        <div>
                            <div class="font-medium">{{ row.original.full_name }}</div>
                            <div class="text-muted-foreground text-xs">{{ row.original.student_id }}</div>
                            <Badge v-if="row.original.status === 'intake_pre_uni_gc'" variant="secondary" class="mt-1 text-[10px]"> EGC {{ row.original.gc_current_level || '' }} </Badge>
                            <Badge v-else-if="row.original.stage === 'Major'" variant="outline" class="mt-1 border-blue-200 bg-blue-50 text-[10px] text-blue-700"> Major </Badge>
                        </div>
                    </template>

                    <!-- Program Column -->
                    <template #cell-program_code="{ row }">
                        <div class="text-muted-foreground text-sm">
                            <div>{{ row.original.program_code || '-' }}</div>
                            <div class="text-xs">{{ row.original.intake_semester }}</div>
                        </div>
                    </template>

                    <!-- Gross Column -->
                    <template #cell-gross_billed="{ row }">
                        <div class="text-left font-medium text-red-600" :title="`Major: ${formatCurrency(row.original.breakdown.major)}\nEGC: ${formatCurrency(row.original.breakdown.egc)}\nRetake: ${formatCurrency(row.original.breakdown.retake)}`">
                            {{ formatCurrency(row.original.gross_billed) }}
                        </div>
                    </template>

                    <!-- Discounts Column -->
                    <template #cell-total_discounts="{ row }">
                        <div class="text-left font-medium text-sky-600">
                            {{ formatCurrency(row.original.total_discounts) }}
                        </div>
                    </template>

                    <!-- Cash Applied Column -->
                    <template #cell-cash_applied="{ row }">
                        <div class="text-left font-medium text-green-600">
                            {{ formatCurrency(row.original.cash_applied) }}
                        </div>
                    </template>

                    <!-- Outstanding Column -->
                    <template #cell-outstanding_amount="{ row }">
                        <div class="text-left font-medium">
                            <span :class="row.original.outstanding_amount > 0 ? 'text-orange-600' : 'text-green-600'">
                                {{ formatCurrency(row.original.outstanding_amount) }}
                            </span>
                            <div v-if="row.original.unapplied_cash > 0" class="text-xs text-green-600" title="Unapplied Cash">+{{ formatCurrency(row.original.unapplied_cash) }} unapplied</div>
                            <div v-if="row.original.net_due > 0" class="text-muted-foreground text-xs">Net due {{ formatCurrency(row.original.net_due) }}</div>
                        </div>
                    </template>

                    <!-- Flags Column -->
                    <template #cell-flags="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <!-- Defer Flags -->
                            <Badge v-if="row.original.flags.is_defer_preserve" variant="outline" class="border-purple-200 bg-purple-50 text-purple-700"> Defer Preserve </Badge>
                            <Badge v-if="row.original.flags.is_defer_forfeit" variant="outline" class="border-red-200 bg-red-50 text-red-700"> Defer Forfeit </Badge>
                            <Badge v-if="row.original.flags.missing_docs" variant="destructive" class="text-[10px]"> Missing Docs</Badge>

                            <!-- Retake Flag -->
                            <Badge v-if="row.original.flags.has_retake" variant="outline" class="border-orange-200 bg-orange-50 text-orange-700"> Retake </Badge>
                            <Badge v-if="row.original.flags.uncharged" variant="secondary" class="text-gray-500"> Uncharged </Badge>
                        </div>
                    </template>

                    <!-- Status Column -->
                    <template #cell-invoice_status="{ row }">
                        <div v-if="row.original.invoices.length > 0" class="flex flex-col items-start gap-2">
                            <div v-for="invoice in row.original.invoices" :key="invoice.id" class="flex flex-col items-start gap-1">
                                <Badge :class="getStatusBadgeClass(invoice.status)">
                                    {{ getStatusLabel(invoice.status) }}
                                </Badge>
                                <span class="text-muted-foreground text-xs"> #{{ invoice.invoice_number }} </span>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-start gap-1">
                            <Badge :class="getStatusBadgeClass(null)">
                                {{ getStatusLabel(null) }}
                            </Badge>
                        </div>
                    </template>

                    <!-- Actions Column -->
                    <template #cell-actions="{ row }">
                        <div class="text-right">
                            <Link :href="route('students.academic-summary.fees', { student: row.original.id })">
                                <Button variant="outline" size="sm">
                                    <ArrowRight class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <!-- Pagination -->
        <DataPagination :pagination-data="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
