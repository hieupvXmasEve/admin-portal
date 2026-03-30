<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldInput } from '@/components/ui/number-field';
import { Separator } from '@/components/ui/separator';
import { useApi, useStudentSearch } from '@/composables';
import type { StudentBasic } from '@/types/finance';
import { formatCurrency } from '@/utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Clock, Loader2, QrCode, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

// DNG form data auto-filled from student
interface StudentDngData {
    student_id: number;
    campus_code: string;
    student_code: string;
    student_name: string;
    email: string;
    student_address: string;
    cccd: string;
    latest_dng_request: {
        id: number;
        status: string;
        item_id: string;
        description: string | null;
        created_at: string | null;
    } | null;
}

// DNG payment request response
interface DngPaymentResponse {
    id: number;
    status: string;
    description: string | null;
    dng_transaction_id: string | null;
    dng_payment_id: string | null;
}

interface PrefillProps {
    student: StudentBasic;
    dng_data: StudentDngData;
    amount: number | null;
    fee_type: string;
    description: string;
    source_context: string | null;
}

interface Props {
    prefill: PrefillProps | null;
}

const props = defineProps<Props>();

// Student search
const { searchQuery: studentSearch, searchResults, isLoading: isSearching, reset: resetStudentSearch } = useStudentSearch({ limit: 5 });

const api = useApi();

// State
const selectedStudent = ref<StudentBasic | null>(props.prefill?.student ?? null);
const studentDngData = ref<StudentDngData | null>(props.prefill?.dng_data ?? null);
const loadingDngData = ref(false);
const amount = ref<number | null>(props.prefill?.amount ?? null);
const feeType = ref(props.prefill?.fee_type ?? 'HP');
const feeDescription = ref(props.prefill?.description ?? '');
const itemId = ref('');
// estimate_time in MM/YY format per DNG spec
const now = new Date();
const estimateTime = ref(`${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getFullYear()).slice(-2)}`);
const submitting = ref(false);
const dngResponse = ref<DngPaymentResponse | null>(null);
const apiError = ref('');

if (studentDngData.value) {
    itemId.value = `${studentDngData.value.student_code}_${Date.now()}`;
}

// Computed
const canSubmit = computed(() => selectedStudent.value && studentDngData.value && amount.value && amount.value > 0 && feeType.value && feeDescription.value.trim().length > 0 && itemId.value && !submitting.value && !dngResponse.value);

// Student selection → fetch DNG data
const selectStudent = async (student: StudentBasic) => {
    selectedStudent.value = student;
    resetStudentSearch();
    apiError.value = '';

    loadingDngData.value = true;
    try {
        const res = await api.get(`/finance/payments/${student.id}/dng-data`);
        const dngData = res.data.value as unknown as StudentDngData | null;

        if (dngData) {
            studentDngData.value = dngData;
            // Auto-generate item_id from student code + timestamp
            itemId.value = `${dngData.student_code}_${Date.now()}`;
        }
    } catch {
        toast.error('Không thể tải thông tin sinh viên');
    } finally {
        loadingDngData.value = false;
    }
};

const clearStudent = () => {
    selectedStudent.value = null;
    studentDngData.value = null;
    amount.value = null;
    feeDescription.value = '';
    dngResponse.value = null;
    apiError.value = '';
    itemId.value = '';
};

// Submit to DNG API
const handleSubmit = async () => {
    if (!studentDngData.value || !amount.value) return;

    submitting.value = true;
    apiError.value = '';

    try {
        const payload = {
            student_id: studentDngData.value.student_id,
            campus_code: studentDngData.value.campus_code,
            student_code: studentDngData.value.student_code,
            fee_type: feeType.value,
            description: feeDescription.value.trim(),
            item_id: itemId.value,
            amount: amount.value,
            type: feeType.value,
            student_name: studentDngData.value.student_name,
            email: studentDngData.value.email,
            estimate_time: estimateTime.value,
            student_address: studentDngData.value.student_address || 'N/A',
            cccd: studentDngData.value.cccd || '',
            fee_types: [feeType.value],
        };

        const res = await api.post<DngPaymentResponse>('/api/v1/finance/dng/payment-requests', payload);

        const createdRequest = res.data.value?.data ?? null;

        if (createdRequest) {
            dngResponse.value = createdRequest;
            toast.success('Đã tạo yêu cầu thanh toán DNG thành công');
        } else {
            apiError.value = 'Phản hồi không hợp lệ từ DNG';
            toast.error('Có lỗi xảy ra');
        }
    } catch {
        apiError.value = 'Không thể kết nối đến cổng thanh toán DNG';
        toast.error('Có lỗi xảy ra khi tạo yêu cầu thanh toán');
    } finally {
        submitting.value = false;
    }
};

// Status badge variant
const statusVariant = computed(() => {
    const s = dngResponse.value?.status;
    if (s === 'pushed_to_dng') return 'secondary';
    if (s === 'paid_uninvoiced' || s === 'paid_invoiced') return 'default';
    if (s === 'failed') return 'destructive';
    return 'outline';
});

const statusLabel = computed(() => {
    const map: Record<string, string> = {
        pending: 'Đang chờ',
        pushed_to_dng: 'Đã gửi DNG',
        paid_uninvoiced: 'Đã thanh toán',
        paid_invoiced: 'Đã xuất hóa đơn',
        reconciled: 'Đã đối soát',
        failed: 'Thất bại',
    };
    return map[dngResponse.value?.status ?? ''] ?? dngResponse.value?.status;
});
</script>

<template>
    <Head title="Tạo yêu cầu thanh toán" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link :href="route('finance.payments.index')">
                <Button variant="outline" size="icon">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </Link>
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Tạo yêu cầu thanh toán</h1>
                <p class="text-muted-foreground mt-1">Tạo khoản phí qua cổng thanh toán DNG</p>
            </div>
        </div>

        <Card v-if="props.prefill?.source_context === 'settlement_no_cash'" class="border-blue-200 bg-blue-50/60">
            <CardHeader>
                <CardTitle class="text-base text-blue-900">Settlement prefill</CardTitle>
                <CardDescription class="text-blue-800">This request was started from the settlement worklist for a student with no unapplied cash.</CardDescription>
            </CardHeader>
        </Card>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Student Selection -->
                <Card>
                    <CardHeader>
                        <CardTitle>Sinh viên</CardTitle>
                        <CardDescription>Chọn sinh viên cần tạo yêu cầu thanh toán</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="selectedStudent" class="flex items-center justify-between rounded-lg border p-4">
                            <div>
                                <p class="font-medium">{{ selectedStudent.full_name }}</p>
                                <p class="text-muted-foreground text-sm">{{ selectedStudent.student_id }} - {{ selectedStudent.email }}</p>
                                <p v-if="studentDngData" class="text-muted-foreground mt-1 text-xs">Campus: {{ studentDngData.campus_code }}</p>
                            </div>
                            <Button type="button" variant="outline" size="sm" :disabled="!!dngResponse" @click="clearStudent"> Thay đổi </Button>
                        </div>
                        <div v-else class="space-y-2">
                            <div class="relative">
                                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                <Input v-model="studentSearch" placeholder="Tìm kiếm sinh viên theo tên, MSSV, email..." class="pl-10" />
                            </div>
                            <div v-if="searchResults.length > 0" class="rounded-lg border">
                                <div v-for="student in searchResults" :key="student.id" class="hover:bg-muted cursor-pointer border-b p-3 last:border-b-0" @click="selectStudent(student)">
                                    <p class="font-medium">{{ student.full_name }}</p>
                                    <p class="text-muted-foreground text-sm">{{ student.student_id }} - {{ student.email }}</p>
                                </div>
                            </div>
                            <p v-if="isSearching" class="text-muted-foreground text-sm">Đang tìm kiếm...</p>
                        </div>
                        <div v-if="loadingDngData" class="text-muted-foreground flex items-center gap-2 text-sm">
                            <Loader2 class="h-4 w-4 animate-spin" />
                            Đang tải thông tin DNG...
                        </div>
                        <div v-if="studentDngData?.latest_dng_request" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <div class="font-medium">Student already has a DNG request</div>
                            <div class="mt-1">Request #{{ studentDngData.latest_dng_request.id }} · {{ studentDngData.latest_dng_request.status }}</div>
                            <div class="mt-1 font-mono text-xs">{{ studentDngData.latest_dng_request.item_id }}</div>
                            <div v-if="studentDngData.latest_dng_request.description" class="mt-2">{{ studentDngData.latest_dng_request.description }}</div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Details (before submission) -->
                <Card v-if="studentDngData && !dngResponse">
                    <CardHeader>
                        <CardTitle>Chi tiết khoản phí</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Amount -->
                            <div class="space-y-2">
                                <NumberField
                                    id="amount"
                                    :default-value="amount ?? 0"
                                    :model-value="amount ?? 0"
                                    @update:model-value="
                                        (v: number | null) => {
                                            if (v) {
                                                amount = v;
                                            } else {
                                                amount = null;
                                            }
                                        }
                                    "
                                    :format-options="{
                                        style: 'currency',
                                        currency: 'VND',
                                        currencyDisplay: 'code',
                                        currencySign: 'accounting',
                                    }"
                                >
                                    <Label for="amount">Số tiền (VNĐ) *</Label>
                                    <NumberFieldContent>
                                        <NumberFieldInput />
                                    </NumberFieldContent>
                                </NumberField>
                            </div>

                            <!-- Fee Type -->
                            <div class="space-y-2">
                                <Label for="fee_type">Loại phí *</Label>
                                <Input v-model="feeType" placeholder="VD: HP, LPT, ..." />
                                <p class="text-muted-foreground text-xs">Mã loại phí trên hệ thống DNG</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="fee_description">Mô tả khoản phí *</Label>
                            <Input id="fee_description" v-model="feeDescription" placeholder="Ví dụ: Thu học phí còn thiếu của invoice chưa thanh toán" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Item ID -->
                            <div class="space-y-2">
                                <Label for="item_id">Mã khoản phí *</Label>
                                <Input v-model="itemId" placeholder="Mã định danh khoản phí" />
                            </div>

                            <!-- Estimate Time (MM/YY format per DNG spec) -->
                            <div class="space-y-2">
                                <Label for="estimate_time">Thời hạn thanh toán (MM/YY)</Label>
                                <Input v-model="estimateTime" placeholder="05/26" />
                                <p class="text-muted-foreground text-xs">Tháng/năm hết hạn, VD: 05/26</p>
                            </div>
                        </div>

                        <!-- Error display -->
                        <p v-if="apiError" class="text-sm text-red-500">{{ apiError }}</p>
                    </CardContent>
                </Card>

                <!-- DNG Response / QR Code (after submission) -->
                <Card v-if="dngResponse">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <QrCode class="h-5 w-5" />
                                Kết quả yêu cầu thanh toán
                            </CardTitle>
                            <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Mã giao dịch DNG</span>
                                <span class="font-mono">{{ dngResponse.dng_transaction_id ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Mã thanh toán DNG</span>
                                <span class="font-mono">{{ dngResponse.dng_payment_id ?? '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Số tiền</span>
                                <span class="font-semibold">{{ formatCurrency(amount ?? 0) }}</span>
                            </div>
                        </div>

                        <Separator />

                        <div class="flex flex-col items-center gap-3 py-4 text-center">
                            <Clock class="text-muted-foreground h-8 w-8" />
                            <p class="text-muted-foreground text-sm">Debt was pushed successfully. Student payment actions should now request a QR link or installment link from the student finance APIs.</p>
                        </div>

                        <div class="flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                            <p class="text-sm text-blue-700 dark:text-blue-300">Yêu cầu đã được gửi đến DNG. Frontend student actions should fetch the third-party payment link from the dedicated student APIs before redirecting the student.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Hành động</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Button v-if="!dngResponse" type="button" class="w-full" :disabled="!canSubmit" @click="handleSubmit">
                            <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                            Gửi yêu cầu đến DNG
                        </Button>
                        <Link v-if="dngResponse" :href="route('finance.payments.index')">
                            <Button class="w-full">Quay lại danh sách</Button>
                        </Link>
                        <Link :href="route('finance.payments.index')">
                            <Button type="button" variant="outline" class="w-full">Hủy bỏ</Button>
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Hướng dẫn</CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground space-y-2 text-sm">
                        <p>• Chọn sinh viên và nhập thông tin khoản phí</p>
                        <p>• Hệ thống sẽ đẩy khoản nợ lên cổng <strong>DNG</strong> và tạo mã QR</p>
                        <p>• Sinh viên quét QR để thanh toán</p>
                        <p>• DNG gửi webhook xác nhận → hệ thống tự động ghi nhận</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
