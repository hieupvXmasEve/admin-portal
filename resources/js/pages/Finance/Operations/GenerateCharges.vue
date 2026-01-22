<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { formatCurrency, type Semester } from '@/types/finance';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    FileSpreadsheet,
    Play,
    Upload,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface ChargeTypeOption {
    value: string;
    label: string;
    description: string;
    is_credit: boolean;
}

interface PreviewStudent {
    id: number;
    student_id: string;
    full_name: string;
    status: string;
    has_existing_charge: boolean;
    estimated_amount: number;
    warning: string | null;
    breakdown?: { label: string; amount: number; }[];
    will_create_invoice?: boolean;
}

interface PreviewResult {
    students: PreviewStudent[];
    total_students: number;
    new_charges_count: number;
    skip_count: number;
    total_amount: number;
    warnings: string[];
}

interface Props {
    semesters: Semester[];
    chargeTypes: ChargeTypeOption[];
    currentSemester: Semester | null;
}

import { useApi } from '@/composables/useApiRequest'; // Add this import

const props = defineProps<Props>();

const api = useApi(); // Instantiate API

// Wizard steps
const currentStep = ref(1);
const totalSteps = 4;

// Step 1: Semester Selection
const selectedSemesterId = ref(props.currentSemester?.id ? String(props.currentSemester.id) : '');

// Step 2: Scope Selection
type ScopeType = 'all_eligible' | 'by_filter' | 'upload_list';
const scopeType = ref<ScopeType>('all_eligible');
const filterProgramId = ref<string>('all');
const filterEnrollmentStatus = ref<string>('active');
const uploadedStudentIds = ref<string>('');
const uploadedFile = ref<File | null>(null);

// Step 3: Charge Type Selection
const selectedChargeTypes = ref<string[]>(['tuition_term', 'egc_level_fee', 'voucher']);
const customAmount = ref<number>(0);
const useCustomAmount = ref(false);
const skipIfIssuedOrPaid = ref(true);
const onlyUpdateDraft = ref(true);
const mergeInvoice = ref(true);

// Step 4: Preview & Confirm
const previewResult = ref<PreviewResult | null>(null);
const isLoadingPreview = ref(false);
const isGenerating = ref(false);

// Computed
const canProceedStep1 = computed(() => !!selectedSemesterId.value);
const canProceedStep2 = computed(() => {
    if (scopeType.value === 'all_eligible') return true;
    if (scopeType.value === 'by_filter') return true;
    if (scopeType.value === 'upload_list') return uploadedStudentIds.value.trim().length > 0;
    return false;
});
const canProceedStep3 = computed(() => selectedChargeTypes.value.length > 0);

const selectedSemester = computed(() => {
    return props.semesters.find(s => String(s.id) === selectedSemesterId.value);
});

// Navigation
const goToStep = (step: number) => {
    if (step >= 1 && step <= totalSteps) {
        currentStep.value = step;
    }
};

const nextStep = () => {
    if (currentStep.value < totalSteps) {
        if (currentStep.value === 3) {
            loadPreview();
        }
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

// Load preview
const loadPreview = async () => {
    isLoadingPreview.value = true;
    previewResult.value = null;

    try {
        const payload = {
            semester_id: selectedSemesterId.value,
            scope_type: scopeType.value,
            filter_program_id: filterProgramId.value !== 'all' ? filterProgramId.value : null,
            filter_enrollment_status: filterEnrollmentStatus.value,
            uploaded_student_ids: scopeType.value === 'upload_list'
                ? uploadedStudentIds.value.split('\n').map(s => s.trim()).filter(Boolean)
                : null,
            charge_types: selectedChargeTypes.value,
            custom_amount: useCustomAmount.value ? customAmount.value : null,
            skip_if_issued_or_paid: skipIfIssuedOrPaid.value,
            only_update_draft: onlyUpdateDraft.value,
            merge_invoice: mergeInvoice.value,
        };

        const { data: responseData, error } = await api.post<PreviewResult>(
            route('api.finance.operations.preview-charges'),
            payload
        );

        if (responseData.value?.success) {
            previewResult.value = responseData.value.data;
        } else {
            const msg = responseData.value?.message || error.value || 'Không thể tải preview';
            toast.error(msg);
        }
    } catch (error) {
        console.error('Preview error:', error);
        toast.error('Lỗi khi tải preview');
    } finally {
        isLoadingPreview.value = false;
    }
};

// Generate charges
const generateCharges = async () => {
    if (!previewResult.value) return;

    isGenerating.value = true;

    try {
        const payload = {
            semester_id: selectedSemesterId.value,
            scope_type: scopeType.value,
            filter_program_id: filterProgramId.value !== 'all' ? filterProgramId.value : null,
            filter_enrollment_status: filterEnrollmentStatus.value,
            uploaded_student_ids: scopeType.value === 'upload_list'
                ? uploadedStudentIds.value.split('\n').map(s => s.trim()).filter(Boolean)
                : null,
            charge_types: selectedChargeTypes.value,
            custom_amount: useCustomAmount.value ? customAmount.value : null,
            skip_if_issued_or_paid: skipIfIssuedOrPaid.value,
            only_update_draft: onlyUpdateDraft.value,
            merge_invoice: mergeInvoice.value,
        };

        const { data: responseData, error } = await api.post<{ created_count: number; errors?: string[] }>(
            route('api.finance.operations.run-generate'),
            payload
        );

        if (responseData.value?.success) {
            const data = responseData.value.data;
            if (data.errors && data.errors.length > 0) {
                toast.warning(`Đã chạy xong với ${data.errors.length} lỗi. ${data.created_count} charges được tạo.`);
                console.error('Batch errors:', data.errors);
                // Optionally delay redirect or show errors in a modal
                // For now, we still redirect but maybe user should verify
                setTimeout(() => {
                    router.visit(route('finance.operations.dashboard', { semester_id: selectedSemesterId.value }));
                }, 2000);
            } else {
                toast.success(`Đã tạo ${data.created_count} charges thành công!`);
                router.visit(route('finance.operations.dashboard', { semester_id: selectedSemesterId.value }));
            }
        } else {
            const msg = responseData.value?.message || error.value || 'Có lỗi xảy ra';
            toast.error(msg);
        }
    } catch (error) {
        console.error('Generate error:', error);
        toast.error('Lỗi khi tạo charges');
    } finally {
        isGenerating.value = false;
    }
};

// Toggle charge type
const toggleChargeType = (chargeType: string) => {
    const index = selectedChargeTypes.value.indexOf(chargeType);
    if (index > -1) {
        selectedChargeTypes.value.splice(index, 1);
    } else {
        selectedChargeTypes.value.push(chargeType);
    }
};

defineOptions({
    layout: AppLayout,
});
</script>

<template>

    <Head title="Generate Charges" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Generate Charges</h1>
                <p class="text-muted-foreground mt-1">Wizard tạo phí hàng loạt theo kỳ</p>
            </div>
        </div>

        <!-- Progress Steps -->
        <div class="flex items-center justify-center">
            <div class="flex items-center gap-2">
                <template v-for="step in totalSteps" :key="step">
                    <button @click="step <= currentStep ? goToStep(step) : null" :class="[
                        'flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium transition-colors',
                        currentStep === step
                            ? 'bg-primary text-primary-foreground'
                            : step < currentStep
                                ? 'bg-primary/20 text-primary cursor-pointer hover:bg-primary/30'
                                : 'bg-muted text-muted-foreground cursor-not-allowed'
                    ]">
                        {{ step }}
                    </button>
                    <div v-if="step < totalSteps"
                        :class="['h-1 w-12 rounded', step < currentStep ? 'bg-primary' : 'bg-muted']"></div>
                </template>
            </div>
        </div>

        <!-- Step Content -->
        <Card>
            <!-- Step 1: Semester Selection -->
            <template v-if="currentStep === 1">
                <CardHeader>
                    <CardTitle>Bước 1: Chọn học kỳ</CardTitle>
                    <CardDescription>Chọn học kỳ cần tạo charges</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="max-w-md">
                        <Label>Học kỳ</Label>
                        <Select v-model="selectedSemesterId">
                            <SelectTrigger class="mt-1">
                                <SelectValue placeholder="Chọn học kỳ" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="selectedSemester" class="rounded-lg border bg-muted/50 p-4">
                        <h4 class="font-medium">{{ selectedSemester.name }}</h4>
                        <p class="text-muted-foreground text-sm">
                            Mã: {{ selectedSemester.code }}
                        </p>
                    </div>
                </CardContent>
            </template>

            <!-- Step 2: Scope Selection -->
            <template v-if="currentStep === 2">
                <CardHeader>
                    <CardTitle>Bước 2: Chọn phạm vi</CardTitle>
                    <CardDescription>Xác định danh sách sinh viên cần tạo charges</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-3">
                        <!-- All Eligible -->
                        <div @click="scopeType = 'all_eligible'" :class="[
                            'cursor-pointer rounded-lg border-2 p-4 transition-colors',
                            scopeType === 'all_eligible'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-muted-foreground'
                        ]">
                            <div class="flex items-center gap-3">
                                <Users class="h-8 w-8 text-blue-500" />
                                <div>
                                    <h4 class="font-medium">Tất cả eligible</h4>
                                    <p class="text-muted-foreground text-sm">
                                        Sinh viên đang active trong kỳ
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- By Filter -->
                        <div @click="scopeType = 'by_filter'" :class="[
                            'cursor-pointer rounded-lg border-2 p-4 transition-colors',
                            scopeType === 'by_filter'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-muted-foreground'
                        ]">
                            <div class="flex items-center gap-3">
                                <FileSpreadsheet class="h-8 w-8 text-green-500" />
                                <div>
                                    <h4 class="font-medium">Theo filter</h4>
                                    <p class="text-muted-foreground text-sm">
                                        Lọc theo chương trình, trạng thái
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Upload List -->
                        <div @click="scopeType = 'upload_list'" :class="[
                            'cursor-pointer rounded-lg border-2 p-4 transition-colors',
                            scopeType === 'upload_list'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-muted-foreground'
                        ]">
                            <div class="flex items-center gap-3">
                                <Upload class="h-8 w-8 text-orange-500" />
                                <div>
                                    <h4 class="font-medium">Upload danh sách</h4>
                                    <p class="text-muted-foreground text-sm">
                                        Nhập mã sinh viên cần tạo
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Options -->
                    <div v-if="scopeType === 'by_filter'" class="grid gap-4 rounded-lg border p-4 md:grid-cols-2">
                        <div>
                            <Label>Chương trình</Label>
                            <Select v-model="filterProgramId">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="Chọn chương trình" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tất cả chương trình</SelectItem>
                                    <!-- Programs will be loaded dynamically -->
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Trạng thái đăng ký</Label>
                            <Select v-model="filterEnrollmentStatus">
                                <SelectTrigger class="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="enrolled">Enrolled</SelectItem>
                                    <SelectItem value="all">Tất cả</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Upload Student IDs -->
                    <div v-if="scopeType === 'upload_list'" class="space-y-3 rounded-lg border p-4">
                        <Label>Danh sách mã sinh viên (mỗi dòng 1 mã)</Label>
                        <textarea v-model="uploadedStudentIds"
                            class="border-input bg-background min-h-[150px] w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="SWE001&#10;SWE002&#10;SWE003"></textarea>
                        <p class="text-muted-foreground text-xs">
                            Số lượng: {{uploadedStudentIds.split('\n').filter(s => s.trim()).length}} sinh viên
                        </p>
                    </div>
                </CardContent>
            </template>

            <!-- Step 3: Charge Type Selection -->
            <template v-if="currentStep === 3">
                <CardHeader>
                    <CardTitle>Bước 3: Chọn loại phí</CardTitle>
                    <CardDescription>Chọn loại charges cần tạo cho sinh viên</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div v-for="chargeType in chargeTypes" :key="chargeType.value"
                            @click="toggleChargeType(chargeType.value)" :class="[
                                'cursor-pointer rounded-lg border-2 p-4 transition-colors',
                                selectedChargeTypes.includes(chargeType.value)
                                    ? chargeType.is_credit
                                        ? 'border-green-500 bg-green-50'
                                        : 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground'
                            ]">
                            <div class="flex items-center gap-3">
                                <Checkbox :model-value="selectedChargeTypes.includes(chargeType.value)"
                                    @click.stop="toggleChargeType(chargeType.value)" />
                                <div>
                                    <h4 class="font-medium">{{ chargeType.label }}</h4>
                                    <p class="text-muted-foreground text-sm">{{ chargeType.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Amount Option -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border p-4">
                            <h4 class="mb-2 font-medium">Options</h4>
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <Checkbox id="chk-skip" v-model:checked="skipIfIssuedOrPaid" />
                                    <Label for="chk-skip">Skip if Invoice Issued/Paid</Label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Checkbox id="chk-draft" v-model:checked="onlyUpdateDraft" />
                                    <Label for="chk-draft">Only update Draft invoices</Label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Checkbox id="chk-merge" v-model:checked="mergeInvoice" />
                                    <Label for="chk-merge">Merge with existing Draft</Label>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4">
                            <div class="flex items-center gap-3">
                                <Checkbox v-model="useCustomAmount" />
                                <Label>Sử dụng số tiền tùy chỉnh</Label>
                            </div>
                            <div v-if="useCustomAmount" class="mt-3">
                                <Input v-model.number="customAmount" type="number" placeholder="Nhập số tiền"
                                    class="max-w-xs" />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </template>

            <!-- Step 4: Preview & Confirm -->
            <template v-if="currentStep === 4">
                <CardHeader>
                    <CardTitle>Bước 4: Preview & Xác nhận</CardTitle>
                    <CardDescription>Kiểm tra trước khi tạo charges</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Loading -->
                    <div v-if="isLoadingPreview" class="py-12 text-center">
                        <div
                            class="border-primary mx-auto h-8 w-8 animate-spin rounded-full border-4 border-t-transparent">
                        </div>
                        <p class="text-muted-foreground mt-3">Đang tải preview...</p>
                    </div>

                    <!-- Preview Result -->
                    <template v-else-if="previewResult">
                        <!-- Summary -->
                        <div class="grid gap-4 md:grid-cols-4">
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Tổng sinh viên</CardDescription>
                                    <CardTitle class="text-2xl">{{ previewResult.total_students }}</CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Sẽ tạo charges</CardDescription>
                                    <CardTitle class="text-2xl text-green-600">{{ previewResult.new_charges_count }}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Bỏ qua (đã có)</CardDescription>
                                    <CardTitle class="text-2xl text-yellow-600">{{ previewResult.skip_count }}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Tổng tiền</CardDescription>
                                    <CardTitle class="text-2xl">{{ formatCurrency(previewResult.total_amount) }}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                        </div>

                        <!-- Warnings -->
                        <div v-if="previewResult.warnings.length > 0"
                            class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                            <div class="flex items-center gap-2 text-yellow-800">
                                <AlertTriangle class="h-5 w-5" />
                                <h4 class="font-medium">Cảnh báo</h4>
                            </div>
                            <ul class="mt-2 list-inside list-disc text-sm text-yellow-700">
                                <li v-for="(warning, index) in previewResult.warnings" :key="index">{{ warning }}</li>
                            </ul>
                        </div>

                        <!-- Student Preview Table -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-base">Preview danh sách ({{ previewResult.students.length }}
                                    đầu tiên)</CardTitle>
                            </CardHeader>
                            <CardContent class="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="text-center">STT</TableHead>
                                            <TableHead>Sinh viên</TableHead>
                                            <TableHead>Charge Breakdown</TableHead>
                                            <TableHead class="text-right">Net Due</TableHead>
                                            <TableHead>Trạng thái</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(student, index) in previewResult.students" :key="student.id">
                                            <TableCell>
                                                <div class="text-muted-foreground text-xs text-center">
                                                    {{ index + 1 }}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div class="font-medium">{{ student.full_name }} - {{ student.student_id }}</div>
                                                    <div
                                                        class="mt-1 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                        {{ student.status }}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-sm">
                                                <ul class="space-y-1">
                                                    <li v-for="(item, idx) in student.breakdown || []" :key="idx"
                                                        class="flex justify-between text-xs">
                                                        <span>{{ item.label }}</span>
                                                        <span :class="item.amount < 0 ? 'text-green-600' : ''">{{
                                                            formatCurrency(item.amount) }}</span>
                                                    </li>
                                                    <li v-if="!student.breakdown || student.breakdown.length === 0"
                                                        class="text-muted-foreground italic text-xs">No charges</li>
                                                </ul>
                                                <div v-if="student.warning" class="mt-1 text-xs text-yellow-600">
                                                    Warning: {{ student.warning }}
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-right font-medium">
                                                {{ formatCurrency(student.estimated_amount) }}
                                            </TableCell>
                                            <TableCell>
                                                <div v-if="student.has_existing_charge"
                                                    class="flex items-center gap-1 text-yellow-600">
                                                    <AlertTriangle class="h-3 w-3" />
                                                    <span class="text-xs">Update/Merge</span>
                                                </div>
                                                <div v-else-if="student.will_create_invoice"
                                                    class="flex items-center gap-1 text-green-600">
                                                    <CheckCircle2 class="h-3 w-3" />
                                                    <span class="text-xs">New Invoice</span>
                                                </div>
                                                <div v-else class="text-xs text-gray-400 italic">
                                                    Skipped (No Action)
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </template>
                </CardContent>
            </template>

            <!-- Footer Navigation -->
            <div class="flex items-center justify-between border-t p-6">
                <Button v-if="currentStep > 1" variant="outline" @click="prevStep">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Quay lại
                </Button>
                <div v-else></div>

                <div class="flex gap-2">
                    <Button v-if="currentStep < 4" :disabled="(currentStep === 1 && !canProceedStep1) ||
                        (currentStep === 2 && !canProceedStep2) ||
                        (currentStep === 3 && !canProceedStep3)
                        " @click="nextStep">
                        Tiếp theo
                        <ArrowRight class="ml-2 h-4 w-4" />
                    </Button>

                    <Button v-if="currentStep === 4 && previewResult && previewResult.new_charges_count > 0"
                        :disabled="isGenerating" @click="generateCharges">
                        <Play class="mr-2 h-4 w-4" />
                        {{ isGenerating ? 'Đang xử lý...' : `Tạo ${previewResult.new_charges_count} charges` }}
                    </Button>
                </div>
            </div>
        </Card>
    </div>
</template>
