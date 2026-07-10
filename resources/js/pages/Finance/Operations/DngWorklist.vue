<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import type { PaginatedResponse } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CalendarIcon, ChevronDown, ChevronRight, Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface DngCharge {
    id: number;
    charge_type: string;
    amount: number;
    balance: number;
    paid: number;
    discount: number;
    description: string | null;
    semester: string | null;
    semester_id: number | null;
}

interface PendingRegistration {
    id: number;
    student_id: number;
    unit_code: string | null;
    unit_name: string | null;
    semester_name: string | null;
    retake_fee: number;
}

interface DngWorklistStudent {
    student_id: number;
    student_code: string;
    student_name: string;
    campus_id: number;
    campus_name: string;
    charge_count: number;
    total_amount: number;
    total_paid: number;
    total_discount: number;
    balance: number;
    next_push_amount: number;
    pending_installment_count: number;
    has_split_plan: boolean;
    active_dng: {
        id: number;
        status: string;
        amount: number;
        created_at: string;
    } | null;
    charges: DngCharge[];
    needs_charge_creation: boolean;
    pending_registrations: PendingRegistration[];
}

interface DngWorklistFilters {
    dng_fee_type: string;
    search: string;
    campus_id: number | null;
    semester_id: number | null;
    dng_status: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface Props {
    students: PaginatedResponse<DngWorklistStudent>;
    filters: DngWorklistFilters;
    summary: {
        total_students: number;
        total_balance: number;
        students_with_active_dng: number;
        students_without_dng: number;
    };
    feeTypeOptions: { value: string; label: string }[];
    semesters: { id: number; name: string; code: string }[];
    campuses: { id: number; name: string; code: string }[];
}

// ---------------------------------------------------------------------------
// Props & state
// ---------------------------------------------------------------------------

const props = defineProps<Props>();
const { selectedId, selectedLabel } = useFinanceSemester();

const expandedRows = ref<Set<number>>(new Set());
const amountOverrides = ref<Record<number, number>>({});
const selectedIds = ref<number[]>([]);
const pushDialogOpen = ref(false);

// Estimate time picker state
const now = new Date();
const estimateTimePickerOpen = ref(false);
const estimateMonth = ref(String(now.getMonth() + 1).padStart(2, '0'));
const estimateYear = ref(String(now.getFullYear()));
const estimateTime = ref(`${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getFullYear()).slice(-2)}`);

// ---------------------------------------------------------------------------
// useDataTable for filtering/pagination
// ---------------------------------------------------------------------------

const { filters, hasActiveFilters, clearAllFilters, handleSearch, handlePaginationNavigate, handlePageSizeChange, setFilter } =
    useDataTable<DngWorklistFilters>({
        baseUrl: route('finance.operations.dng-worklist'),
        initialFilters: {
            dng_fee_type: props.filters.dng_fee_type ?? 'HP',
            search: props.filters.search ?? '',
            campus_id: props.filters.campus_id ?? null,
            dng_status: props.filters.dng_status ?? 'all',
            per_page: props.filters.per_page ?? 50,
            sort: props.filters.sort ?? null,
            direction: props.filters.direction ?? null,
        },
        defaultValues: {
            dng_fee_type: 'HP',
            search: '',
            campus_id: null,
            dng_status: 'all',
            per_page: 50,
            sort: null,
            direction: null,
        },
        only: ['students', 'summary', 'filters'],
        debounce: 300,
        immediateFields: ['dng_fee_type', 'campus_id', 'dng_status'],
    });

// ---------------------------------------------------------------------------
// Push DNG form (Inertia useForm)
// ---------------------------------------------------------------------------

const pushForm = useForm({
    student_ids: [] as number[],
    dng_fee_type: props.filters.dng_fee_type ?? 'HP',
    due_date: '',
    semester_id: selectedId.value,
    description: '',
    estimate_time: estimateTime.value,
    amount_overrides: {} as Record<number, number>,
});

// ---------------------------------------------------------------------------
// Computed helpers
// ---------------------------------------------------------------------------

// Eligible = anything with a positive next-push amount (covers split + non-split).
const eligibleStudents = computed(() => props.students.data.filter((s) => s.next_push_amount > 0));

const isAllSelected = computed(
    () => eligibleStudents.value.length > 0 && selectedIds.value.length === eligibleStudents.value.length,
);

const selectedStudents = computed(() => eligibleStudents.value.filter((s) => selectedIds.value.includes(s.student_id)));

const hasStudentsWithActiveDng = computed(() => selectedStudents.value.some((s) => s.active_dng !== null));

// Total to push = sum of next-push amounts (or admin overrides). Matches backend
// CreateBatchDngFromChargesAction behavior so the UI total matches what will actually be sent.
const totalSelectedAmount = computed(() =>
    selectedStudents.value.reduce((sum, s) => sum + (amountOverrides.value[s.student_id] ?? s.next_push_amount), 0),
);

// ---------------------------------------------------------------------------
// Selection helpers
// ---------------------------------------------------------------------------

const toggleAll = (checked: boolean | 'indeterminate') => {
    selectedIds.value = checked === true ? eligibleStudents.value.map((s) => s.student_id) : [];
};

const toggle = (id: number, checked: boolean | 'indeterminate') => {
    if (checked === true) {
        selectedIds.value = [...new Set([...selectedIds.value, id])];
    } else {
        selectedIds.value = selectedIds.value.filter((sid) => sid !== id);
    }
};

// ---------------------------------------------------------------------------
// Row expand
// ---------------------------------------------------------------------------

const toggleRow = (studentId: number) => {
    if (expandedRows.value.has(studentId)) {
        expandedRows.value.delete(studentId);
    } else {
        expandedRows.value.add(studentId);
    }
};

// ---------------------------------------------------------------------------
// Amount overrides
// ---------------------------------------------------------------------------

const getAmount = (student: DngWorklistStudent): number =>
    amountOverrides.value[student.student_id] ?? student.next_push_amount;

const setAmount = (student: DngWorklistStudent, val: string) => {
    const num = parseFloat(val);
    if (!isNaN(num) && num > 0) {
        amountOverrides.value[student.student_id] = num;
    } else {
        delete amountOverrides.value[student.student_id];
    }
};

// ---------------------------------------------------------------------------
// Estimate time picker
// ---------------------------------------------------------------------------

const estimateMonthOptions = Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1).padStart(2, '0'),
    label: `Tháng ${String(i + 1).padStart(2, '0')}`,
}));

const estimateYearOptions = Array.from({ length: 6 }, (_, i) => {
    const year = now.getFullYear() - 1 + i;
    return { value: String(year), label: `Năm ${year}` };
});

const applyEstimateTimeSelection = () => {
    estimateTime.value = `${estimateMonth.value}/${estimateYear.value.slice(-2)}`;
    pushForm.estimate_time = estimateTime.value;
    estimateTimePickerOpen.value = false;
};

// ---------------------------------------------------------------------------
// Open push dialog
// ---------------------------------------------------------------------------

const openPushDialog = () => {
    pushForm.student_ids = [...selectedIds.value];
    pushForm.dng_fee_type = filters.dng_fee_type;
    pushForm.amount_overrides = { ...amountOverrides.value };
    pushForm.semester_id = selectedId.value;
    pushForm.estimate_time = estimateTime.value;
    pushForm.due_date = '';
    pushForm.description = '';
    pushDialogOpen.value = true;
};

// ---------------------------------------------------------------------------
// Submit push
// ---------------------------------------------------------------------------

const submitPush = () => {
    pushForm.student_ids = [...selectedIds.value];
    pushForm.amount_overrides = { ...amountOverrides.value };

    pushForm.post(route('finance.operations.dng-worklist.store'), {
        preserveScroll: true,
        onSuccess: () => {
            pushDialogOpen.value = false;
            selectedIds.value = [];
            amountOverrides.value = {};
        },
    });
};

// ---------------------------------------------------------------------------
// Formatters
// ---------------------------------------------------------------------------

const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(val);

const getDngStatusBadge = (student: DngWorklistStudent) => {
    if (!student.active_dng) {
        return { label: 'Chưa tạo', class: 'border-gray-200 bg-gray-50 text-gray-600' };
    }
    const s = student.active_dng.status;
    if (s === 'pushed_to_dng') {
        // Compare against installment-aware next-push amount: a 2-installment plan
        // pushed at 10M is "Đã gửi" not "Cần cập nhật" even though balance=20M.
        const amountMatch = Math.abs(student.active_dng.amount - student.next_push_amount) < 1;
        return amountMatch
            ? { label: 'Đã gửi', class: 'border-green-200 bg-green-50 text-green-700' }
            : { label: 'Cần cập nhật', class: 'border-orange-200 bg-orange-50 text-orange-700' };
    }
    return { label: 'Pending', class: 'border-yellow-200 bg-yellow-50 text-yellow-700' };
};

const chargeTypeLabel = (ct: string): string => {
    const map: Record<string, string> = {
        tuition_term: 'Học phí',
        egc_level_fee: 'EGC Level Fee',
        course_fee: 'Course Fee (đã ngừng)',
        retake_fee: 'Phí học lại',
        exam_resit_fee: 'Phí thi lại',
        manual_fee: 'Phí thủ công',
        adjustment: 'Điều chỉnh',
    };
    return map[ct] ?? ct;
};

// ---------------------------------------------------------------------------
// Filter options
// ---------------------------------------------------------------------------

const dngStatusOptions = [
    { value: 'no_dng', label: 'Chưa tạo DNG' },
    { value: 'has_active_dng', label: 'Đã có DNG' },
];
</script>

<template>
    <Head title="DNG Worklist" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Link :href="route('finance.operations.settlement.index')">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">DNG Worklist</h1>
                    <p class="text-muted-foreground text-sm">{{ selectedLabel }}</p>
                    <p class="text-muted-foreground text-sm">
                        Tạo yêu cầu thanh toán DNG từ khoản phí thực tế — tất cả DNG được liên kết đến charge nguồn.
                    </p>
                </div>
            </div>
            <Button
                :disabled="selectedIds.length === 0"
                @click="openPushDialog"
            >
                <Send class="mr-2 h-4 w-4" />
                Push DNG ({{ selectedIds.length }})
                <span v-if="selectedIds.length > 0" class="ml-2 opacity-75">· {{ formatCurrency(totalSelectedAmount) }}</span>
            </Button>
        </div>

        <!-- Fee Type Selector (primary filter, drives the page) -->
        <Card>
            <CardContent class="pt-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[200px] space-y-1">
                        <Label class="text-muted-foreground text-xs font-medium uppercase">Loại phí DNG</Label>
                        <Select
                            :model-value="filters.dng_fee_type"
                            @update:model-value="(v) => setFilter('dng_fee_type', v)"
                        >
                            <SelectTrigger class="h-9">
                                <SelectValue placeholder="Chọn loại phí" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="ft in props.feeTypeOptions" :key="ft.value" :value="ft.value">
                                    {{ ft.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Tổng sinh viên</CardDescription>
                    <CardTitle class="text-2xl">{{ props.summary.total_students }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Tổng số dư cần thu</CardDescription>
                    <CardTitle class="text-2xl text-red-600">{{ formatCurrency(props.summary.total_balance) }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Đã có DNG</CardDescription>
                    <CardTitle class="text-2xl text-green-600">{{ props.summary.students_with_active_dng }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Chưa tạo DNG</CardDescription>
                    <CardTitle class="text-2xl text-amber-600">{{ props.summary.students_without_dng }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <!-- Student Table -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="text-base">
                        Danh sách sinh viên
                        <span class="text-muted-foreground ml-2 font-normal text-sm">
                            ({{ students.total }} sinh viên · trang {{ students.current_page }}/{{ students.last_page }})
                        </span>
                    </CardTitle>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Filters -->
                <FilterPanel :has-active-filters="hasActiveFilters" :columns="3" @clear="clearAllFilters">
                    <FilterSearchInput
                        :model-value="filters.search ?? ''"
                        placeholder="Tìm sinh viên..."
                        @update:model-value="(v) => setFilter('search', v)"
                        @search="handleSearch"
                    />
                    <FilterSelect
                        :model-value="String(filters.campus_id ?? '')"
                        :options="props.campuses.map((c) => ({ value: String(c.id), label: c.name }))"
                        placeholder="Campus"
                        all-label="Tất cả campus"
                        @update:model-value="(v) => setFilter('campus_id', v ? Number(v) : null)"
                        @change="() => setFilter('campus_id', filters.campus_id)"
                    />
                    <FilterSelect
                        :model-value="filters.dng_status ?? 'all'"
                        :options="dngStatusOptions"
                        placeholder="Trạng thái DNG"
                        all-label="Tất cả"
                        @update:model-value="(v) => setFilter('dng_status', v || 'all')"
                        @change="() => setFilter('dng_status', filters.dng_status)"
                    />
                </FilterPanel>

                <!-- Table -->
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-10">
                                <Checkbox
                                    :model-value="isAllSelected"
                                    :disabled="eligibleStudents.length === 0"
                                    aria-label="Chọn tất cả"
                                    @update:model-value="toggleAll"
                                />
                            </TableHead>
                            <TableHead class="w-8"></TableHead>
                            <TableHead>Sinh viên</TableHead>
                            <TableHead class="text-center">Charges</TableHead>
                            <TableHead class="text-right">Số dư</TableHead>
                            <TableHead class="w-44">Số tiền DNG</TableHead>
                            <TableHead>Trạng thái DNG</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="student in eligibleStudents" :key="student.student_id">
                            <!-- Main row -->
                            <TableRow>
                                <TableCell>
                                    <Checkbox
                                        :model-value="selectedIds.includes(student.student_id)"
                                        :aria-label="`Chọn ${student.student_code}`"
                                        @update:model-value="(checked) => toggle(student.student_id, checked)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-6 w-6"
                                        :aria-label="expandedRows.has(student.student_id) ? 'Thu gọn' : 'Mở rộng'"
                                        @click="toggleRow(student.student_id)"
                                    >
                                        <ChevronDown v-if="expandedRows.has(student.student_id)" class="h-3.5 w-3.5" />
                                        <ChevronRight v-else class="h-3.5 w-3.5" />
                                    </Button>
                                </TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ student.student_name }}</div>
                                    <div class="text-muted-foreground flex items-center gap-1.5 text-xs">
                                        {{ student.student_code }}
                                        <span v-if="student.campus_name" class="opacity-60">· {{ student.campus_name }}</span>
                                    </div>
                                    <!-- HL: needs charge creation indicator -->
                                    <Badge
                                        v-if="student.needs_charge_creation"
                                        variant="outline"
                                        class="mt-1 border-orange-200 bg-orange-50 text-xs text-orange-700"
                                    >
                                        <AlertTriangle class="mr-1 h-3 w-3" />
                                        Có đăng ký chưa có charge
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center">
                                    <span class="text-sm font-medium">{{ student.charge_count }}</span>
                                </TableCell>
                                <TableCell class="text-right font-medium text-red-600">
                                    {{ formatCurrency(student.next_push_amount) }}
                                    <div
                                        v-if="student.has_split_plan"
                                        class="text-muted-foreground mt-0.5 text-[10px] font-normal"
                                    >
                                        Đợt tiếp theo · còn {{ student.pending_installment_count }} đợt
                                    </div>
                                    <div
                                        v-if="student.has_split_plan && student.next_push_amount !== student.balance"
                                        class="text-muted-foreground mt-0.5 text-[10px] font-normal"
                                    >
                                        Số dư tổng: {{ formatCurrency(student.balance) }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Input
                                        type="number"
                                        :model-value="getAmount(student)"
                                        class="h-8 text-right"
                                        min="1"
                                        @input="(e) => setAmount(student, (e.target as HTMLInputElement).value)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" :class="getDngStatusBadge(student).class">
                                        {{ getDngStatusBadge(student).label }}
                                    </Badge>
                                    <div v-if="student.active_dng" class="text-muted-foreground mt-0.5 text-xs">
                                        #{{ student.active_dng.id }} · {{ formatCurrency(student.active_dng.amount) }}
                                    </div>
                                </TableCell>
                            </TableRow>

                            <!-- Expanded row: charge breakdown -->
                            <TableRow v-if="expandedRows.has(student.student_id)">
                                <TableCell colspan="7" class="bg-muted/30 p-0">
                                    <div class="space-y-1.5 px-12 py-3">
                                        <!-- Pending registrations (HL, needs charge creation) -->
                                        <div v-if="student.pending_registrations.length > 0" class="mb-2 space-y-1">
                                            <p class="text-xs font-medium text-orange-700">
                                                ⚠ Đăng ký học lại chưa có charge (sẽ tự tạo khi Push DNG):
                                            </p>
                                            <div
                                                v-for="reg in student.pending_registrations"
                                                :key="reg.id"
                                                class="rounded-md border border-orange-200 bg-orange-50/50 px-3 py-1.5 text-xs"
                                            >
                                                <span class="font-mono font-medium">{{ reg.unit_code }}</span>
                                                <span class="ml-1 text-gray-700">{{ reg.unit_name }}</span>
                                                <span v-if="reg.semester_name" class="text-muted-foreground ml-2 opacity-75">
                                                    ({{ reg.semester_name }})
                                                </span>
                                                <span class="ml-auto float-right font-semibold text-orange-700">
                                                    {{ formatCurrency(reg.retake_fee) }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Existing charges -->
                                        <div
                                            v-for="charge in student.charges"
                                            :key="charge.id"
                                            class="flex items-center justify-between rounded-md border bg-white px-3 py-2 text-xs"
                                        >
                                            <div class="space-y-0.5">
                                                <div class="font-medium">{{ chargeTypeLabel(charge.charge_type) }}</div>
                                                <div class="text-muted-foreground">
                                                    {{ charge.description ?? '—' }}
                                                    <span v-if="charge.semester" class="ml-1 opacity-75">({{ charge.semester }})</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-4 text-right">
                                                <div>
                                                    <div class="text-muted-foreground">Gốc</div>
                                                    <div>{{ formatCurrency(charge.amount) }}</div>
                                                </div>
                                                <div v-if="charge.paid > 0">
                                                    <div class="text-muted-foreground">Đã trả</div>
                                                    <div class="text-green-600">{{ formatCurrency(charge.paid) }}</div>
                                                </div>
                                                <div>
                                                    <div class="text-muted-foreground">Số dư</div>
                                                    <div class="font-semibold text-red-600">{{ formatCurrency(charge.balance) }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <p v-if="student.charges.length === 0 && student.pending_registrations.length === 0" class="text-muted-foreground text-xs">
                                            Không có charge nào.
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </template>

                        <TableRow v-if="eligibleStudents.length === 0">
                            <TableCell colspan="7" class="text-muted-foreground py-8 text-center">
                                Không có sinh viên nào phù hợp với bộ lọc hiện tại.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <DataPagination
                    :pagination-data="students"
                    item-name="sinh viên"
                    :page-size-options="[25, 50, 100, 200]"
                    @navigate="handlePaginationNavigate"
                    @page-size-change="handlePageSizeChange"
                />
            </CardContent>
        </Card>
    </div>

    <!-- Push DNG Dialog -->
    <Dialog v-model:open="pushDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Push DNG</DialogTitle>
                <DialogDescription>
                    Tạo yêu cầu thanh toán DNG cho {{ selectedIds.length }} sinh viên đã chọn.
                    Tổng: {{ formatCurrency(totalSelectedAmount) }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitPush">
                <!-- Warning: will cancel existing DNG -->
                <div v-if="hasStudentsWithActiveDng" class="flex items-start gap-2 rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-700">
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                        Một số sinh viên đã có DNG đang chờ cùng loại phí. DNG cũ sẽ bị hủy tự động trước khi tạo mới.
                    </span>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="push-description">
                        Mô tả khoản phí <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="push-description"
                        v-model="pushForm.description"
                        placeholder="VD: Học phí kỳ 1 năm học 2025-2026"
                        :class="{ 'border-red-400': pushForm.errors.description }"
                    />
                    <p v-if="pushForm.errors.description" class="text-xs text-red-500">{{ pushForm.errors.description }}</p>
                </div>

                <!-- Due date -->
                <div class="space-y-2">
                    <Label>Hạn thanh toán nội bộ <span class="text-red-500">*</span></Label>
                    <DatePicker
                        v-model="pushForm.due_date"
                        placeholder="Chọn hạn thanh toán"
                    />
                    <p v-if="pushForm.errors.due_date" class="text-xs text-red-500">{{ pushForm.errors.due_date }}</p>
                </div>

                <!-- Semester (global top-bar context) -->
                <div class="space-y-2">
                    <Label>Kỳ học</Label>
                    <p class="text-sm font-medium">{{ selectedLabel }}</p>
                    <p v-if="pushForm.errors.semester_id" class="text-xs text-red-500">{{ pushForm.errors.semester_id }}</p>
                </div>

                <!-- Estimate time -->
                <div class="space-y-2">
                    <Label>Thời hạn thanh toán (MM/YY)</Label>
                    <Popover v-model:open="estimateTimePickerOpen">
                        <PopoverTrigger as-child>
                            <Button variant="outline" class="w-full justify-start font-normal">
                                <CalendarIcon class="mr-2 h-4 w-4" />
                                {{ pushForm.estimate_time || 'Chọn tháng/năm' }}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-64 space-y-3 p-4" align="start">
                            <div class="space-y-1.5">
                                <Label>Tháng</Label>
                                <Select v-model="estimateMonth">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="m in estimateMonthOptions" :key="m.value" :value="m.value">
                                            {{ m.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-1.5">
                                <Label>Năm</Label>
                                <Select v-model="estimateYear">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="y in estimateYearOptions" :key="y.value" :value="y.value">
                                            {{ y.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="button" class="w-full" @click="applyEstimateTimeSelection">Áp dụng</Button>
                        </PopoverContent>
                    </Popover>
                    <p class="text-muted-foreground text-xs">Chuỗi MM/YY gửi lên hệ thống DNG.</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="pushDialogOpen = false">Hủy</Button>
                    <Button
                        type="submit"
                        :disabled="pushForm.processing || selectedIds.length === 0"
                    >
                        <Send class="mr-2 h-4 w-4" />
                        Push {{ selectedIds.length }} sinh viên → DNG
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
