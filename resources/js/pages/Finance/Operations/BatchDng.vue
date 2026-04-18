<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi, useGlobalConfirmDialog } from '@/composables';
import type { PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarIcon, Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface BatchStudent {
    student_id: number;
    student_code: string;
    student_name: string;
    active_due: number;
    net_amount_to_collect: number;
    latest_dng_request: { id: number; status: string } | null;
}

interface Props {
    students: PaginatedResponse<BatchStudent>;
    feeTypes: { value: string; label: string }[];
    semesters: Array<{
        id: number;
        name: string;
        code: string;
    }>;
}

const props = defineProps<Props>();
const api = useApi();
const confirmDialog = useGlobalConfirmDialog();

// Common batch settings (mirrors single DNG create form)
const now = new Date();
const commonType = ref('HP');
const feeDescription = ref('');
const commonSemesterId = ref('');
const commonDueDate = ref('');
const estimateTime = ref(`${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getFullYear()).slice(-2)}`);
const estimateTimePickerOpen = ref(false);
const estimateMonth = ref(String(now.getMonth() + 1).padStart(2, '0'));
const estimateYear = ref(String(now.getFullYear()));

// Per-student overrides
const amountOverrides = ref<Record<number, number>>({});
const selectedIds = ref<number[]>([]);
const isSubmitting = ref(false);

const eligibleStudents = computed(() =>
    props.students.data.filter((s) => s.net_amount_to_collect > 0),
);

const getAmount = (student: BatchStudent): number =>
    amountOverrides.value[student.student_id] ?? student.net_amount_to_collect;

const setAmount = (student: BatchStudent, val: string) => {
    const num = parseFloat(val);
    if (!isNaN(num) && num > 0) {
        amountOverrides.value[student.student_id] = num;
    } else {
        delete amountOverrides.value[student.student_id];
    }
};

const isAllSelected = computed(
    () => eligibleStudents.value.length > 0 && selectedIds.value.length === eligibleStudents.value.length,
);

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

const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(val);

const totalAmount = computed(() =>
    selectedIds.value.reduce((sum, id) => {
        const s = props.students.data.find((s) => s.student_id === id);
        return sum + (s ? getAmount(s) : 0);
    }, 0),
);

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

const estimateYearOptions = Array.from({ length: 6 }, (_, index) => {
    const year = now.getFullYear() - 1 + index;

    return {
        value: String(year),
        label: `Năm ${year}`,
    };
});

const applyEstimateTimeSelection = () => {
    estimateTime.value = `${estimateMonth.value}/${estimateYear.value.slice(-2)}`;
    estimateTimePickerOpen.value = false;
};

const validateBatchSettings = (): boolean => {
    if (selectedIds.value.length === 0) {
        return false;
    }

    if (!feeDescription.value.trim()) {
        toast.error('Vui lòng nhập mô tả khoản phí');
        return false;
    }

    if (!commonSemesterId.value) {
        toast.error('Vui lòng chọn kỳ học nội bộ');
        return false;
    }

    if (!commonDueDate.value) {
        toast.error('Vui lòng chọn hạn thanh toán nội bộ');
        return false;
    }

    return true;
};

const submitBatchDng = async () => {
    isSubmitting.value = true;

    try {
        const records = selectedIds.value.map((id) => {
            const s = props.students.data.find((s) => s.student_id === id)!;

            return {
                student_id: id,
                amount: getAmount(s),
                type: commonType.value,
                description: feeDescription.value.trim(),
                semester_id: Number(commonSemesterId.value),
                due_date: commonDueDate.value,
                estimate_time: estimateTime.value,
            };
        });

        const res = await api.post('/api/v1/finance/dng/batch', { records });
        if (res.error.value) throw new Error(String(res.error.value));

        const data = (res.data.value as any)?.data;
        toast.success(`Đã tạo ${data?.created ?? selectedIds.value.length} DNG request thành công`);
        selectedIds.value = [];
        amountOverrides.value = {};
    } catch (e: any) {
        toast.error(e.message ?? 'Không thể tạo DNG hàng loạt');
        throw e;
    } finally {
        isSubmitting.value = false;
    }
};

const handleSubmit = () => {
    if (!validateBatchSettings() || isSubmitting.value) {
        return;
    }

    confirmDialog.showConfirmDialog(
        {
            title: 'Xác nhận tạo DNG hàng loạt',
            message: `Bạn có chắc muốn tạo ${selectedIds.value.length} yêu cầu DNG với tổng số tiền ${formatCurrency(totalAmount.value)} không?`,
            confirmText: 'Xác nhận tạo',
            cancelText: 'Hủy',
        },
        {
            onConfirm: submitBatchDng,
        },
    );
};
</script>

<template>
    <Head title="Tạo DNG hàng loạt" />

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
                    <h1 class="text-2xl font-bold tracking-tight">Tạo DNG hàng loạt</h1>
                    <p class="text-muted-foreground text-sm">Tạo yêu cầu thanh toán DNG cho sinh viên chưa có tiền trong tài khoản.</p>
                </div>
            </div>
            <Button :disabled="selectedIds.length === 0 || isSubmitting" @click="handleSubmit">
                <Send class="mr-2 h-4 w-4" />
                Gửi {{ selectedIds.length > 0 ? `(${selectedIds.length})` : '' }}
                {{ selectedIds.length > 0 ? `· ${formatCurrency(totalAmount)}` : '' }}
            </Button>
        </div>

        <!-- Common settings -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Cài đặt chung</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-2">
                        <Label>Loại phí</Label>
                        <Select v-model="commonType">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="ft in feeTypes" :key="ft.value" :value="ft.value">
                                    {{ ft.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>Mô tả khoản phí <span class="text-red-500">*</span></Label>
                        <Input v-model="feeDescription" placeholder="VD: Học phí kỳ 1 năm học 2025-2026" />
                    </div>
                    <div class="space-y-2">
                        <Label>Kỳ học nội bộ <span class="text-red-500">*</span></Label>
                        <Select v-model="commonSemesterId">
                            <SelectTrigger>
                                <SelectValue placeholder="Chọn kỳ học" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in props.semesters" :key="semester.id" :value="semester.id.toString()">
                                    {{ semester.name }} ({{ semester.code }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label>Hạn thanh toán nội bộ <span class="text-red-500">*</span></Label>
                        <DatePicker v-model="commonDueDate" placeholder="Chọn hạn thanh toán" />
                    </div>
                    <div class="space-y-2 xl:col-span-2">
                        <Label>Thời hạn thanh toán (MM/YY)</Label>
                        <Popover v-model:open="estimateTimePickerOpen">
                            <PopoverTrigger as-child>
                                <Button variant="outline" class="w-full justify-start text-left font-normal">
                                    <CalendarIcon class="mr-2 h-4 w-4" />
                                    {{ estimateTime || 'Chọn tháng/năm' }}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-72 space-y-4 p-4" align="start">
                                <div class="space-y-2">
                                    <Label>Tháng</Label>
                                    <Select v-model="estimateMonth">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Chọn tháng" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="month in estimateMonthOptions" :key="month.value" :value="month.value">
                                                {{ month.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label>Năm</Label>
                                    <Select v-model="estimateYear">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Chọn năm" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="year in estimateYearOptions" :key="year.value" :value="year.value">
                                                {{ year.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="button" class="w-full" @click="applyEstimateTimeSelection">Áp dụng</Button>
                            </PopoverContent>
                        </Popover>
                        <p class="text-muted-foreground text-xs">Chọn theo tháng/năm, hệ thống lưu chuỗi MM/YY cho DNG.</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Students table -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">
                    Sinh viên cần thu tiền
                    <span class="text-muted-foreground ml-2 font-normal text-sm">({{ eligibleStudents.length }} sinh viên)</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
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
                            <TableHead>Sinh viên</TableHead>
                            <TableHead class="text-right">Cần thu</TableHead>
                            <TableHead class="w-44">Số tiền DNG</TableHead>
                            <TableHead>DNG hiện tại</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="student in eligibleStudents" :key="student.student_id">
                            <TableCell>
                                <Checkbox
                                    :model-value="selectedIds.includes(student.student_id)"
                                    :aria-label="`Chọn ${student.student_code}`"
                                    @update:model-value="(checked) => toggle(student.student_id, checked)"
                                />
                            </TableCell>
                            <TableCell>
                                <div class="font-medium">{{ student.student_name }}</div>
                                <div class="text-muted-foreground text-xs">{{ student.student_code }}</div>
                            </TableCell>
                            <TableCell class="text-right font-medium text-red-600">
                                {{ formatCurrency(student.net_amount_to_collect) }}
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
                                <span v-if="student.latest_dng_request" class="text-xs text-blue-600">
                                    #{{ student.latest_dng_request.id }} · {{ student.latest_dng_request.status }}
                                </span>
                                <span v-else class="text-muted-foreground text-xs">Chưa có</span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="eligibleStudents.length === 0">
                            <TableCell colspan="5" class="text-muted-foreground py-8 text-center">
                                Không có sinh viên nào cần tạo DNG.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
