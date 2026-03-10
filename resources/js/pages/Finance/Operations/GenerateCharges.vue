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
import DataPagination from '@/components/DataPagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency, type Semester } from '@/types/finance';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    ArrowUpDown,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Play,
    Upload,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    student_type?: string; // EGC | Course - type hiện tại của student
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
const isExporting = ref(false);

// Preview list filter/sort/pagination (client-side)
const previewSearch = ref('');
const previewStatusFilter = ref<string>('all'); // all | new | update | skip
const previewSortBy = ref<'student_id' | 'full_name' | 'student_type' | 'status' | 'action_status' | 'estimated_amount'>('student_id');
const previewSortDir = ref<'asc' | 'desc'>('asc');
const previewPage = ref(1);
const previewPerPage = ref(25);

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

// Filtered, sorted, paginated preview students
const filteredPreviewStudents = computed(() => {
    if (!previewResult.value?.students) return [];
    let list = [...previewResult.value.students];

    // Search filter
    const q = previewSearch.value.trim().toLowerCase();
    if (q) {
        list = list.filter(
            s =>
                (s.student_id ?? '').toLowerCase().includes(q) ||
                (s.full_name ?? '').toLowerCase().includes(q)
        );
    }

    // Status filter
    if (previewStatusFilter.value !== 'all') {
        list = list.filter(s => {
            if (previewStatusFilter.value === 'new') return s.will_create_invoice;
            if (previewStatusFilter.value === 'update') return s.has_existing_charge;
            if (previewStatusFilter.value === 'skip') return !s.will_create_invoice && !s.has_existing_charge;
            return true;
        });
    }

    // Sort
    const col = previewSortBy.value;
    const dir = previewSortDir.value === 'asc' ? 1 : -1;
    const getActionStatus = (s: PreviewStudent) =>
        s.will_create_invoice ? 'new' : s.has_existing_charge ? 'update' : 'skip';
    list.sort((a, b) => {
        let va: string | number;
        let vb: string | number;
        if (col === 'estimated_amount') {
            va = a.estimated_amount ?? 0;
            vb = b.estimated_amount ?? 0;
            return (Number(va) - Number(vb)) * dir;
        }
        if (col === 'action_status') {
            va = getActionStatus(a);
            vb = getActionStatus(b);
        } else if (col === 'student_type') {
            va = a.student_type ?? a.status ?? '';
            vb = b.student_type ?? b.status ?? '';
        } else {
            va = String(a[col as keyof PreviewStudent] ?? '');
            vb = String(b[col as keyof PreviewStudent] ?? '');
        }
        return String(va).localeCompare(String(vb)) * dir;
    });

    return list;
});

const sortedPreviewStudents = computed(() => filteredPreviewStudents.value);

const paginatedPreviewStudents = computed(() => {
    const list = sortedPreviewStudents.value;
    const start = (previewPage.value - 1) * previewPerPage.value;
    return list.slice(start, start + previewPerPage.value);
});

const previewPaginationMeta = computed(() => {
    const total = sortedPreviewStudents.value.length;
    const lastPage = Math.max(1, Math.ceil(total / previewPerPage.value));
    const from = total === 0 ? 0 : (previewPage.value - 1) * previewPerPage.value + 1;
    const to = Math.min(previewPage.value * previewPerPage.value, total);
    const basePath = route('finance.operations.generate-charges');
    return {
        from,
        to,
        total,
        current_page: previewPage.value,
        last_page: lastPage,
        per_page: previewPerPage.value,
        prev_page_url: previewPage.value > 1 ? `${basePath}?page=${previewPage.value - 1}` : null,
        next_page_url: previewPage.value < lastPage ? `${basePath}?page=${previewPage.value + 1}` : null,
        links: [],
    };
});

const hasPreviewFilters = computed(
    () => previewSearch.value.trim() !== '' || previewStatusFilter.value !== 'all'
);

function clearPreviewFilters() {
    previewSearch.value = '';
    previewStatusFilter.value = 'all';
    previewPage.value = 1;
}

function setPreviewSort(col: typeof previewSortBy.value) {
    if (previewSortBy.value === col) {
        previewSortDir.value = previewSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        previewSortBy.value = col;
        previewSortDir.value = 'asc';
    }
    previewPage.value = 1;
}

function handlePreviewNavigate(url: string) {
    try {
        const u = new URL(url, window.location.origin);
        const p = u.searchParams.get('page');
        if (p) previewPage.value = Math.max(1, parseInt(p, 10) || 1);
    } catch {
        /* ignore */
    }
}

function handlePreviewPageSizeChange(size: number) {
    previewPerPage.value = size;
    previewPage.value = 1;
}

// Export Excel
async function exportPreviewExcel() {
    if (!previewResult.value?.students?.length) return;
    isExporting.value = true;
    try {
        const studentsToExport = sortedPreviewStudents.value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(route('api.finance.operations.export-preview-charges'), {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
                Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
            body: JSON.stringify({ students: studentsToExport }),
        });

        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err?.message || `Export failed: ${response.statusText}`);
        }

        const contentDisposition = response.headers.get('content-disposition');
        let filename = `generate_charges_preview_${new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '-')}.xlsx`;
        if (contentDisposition) {
            const match = contentDisposition.match(/filename="?([^";\n]+)"?/);
            if (match) filename = match[1];
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        toast.success('Đã xuất Excel thành công!');
    } catch (e) {
        console.error('Export error:', e);
        toast.error(e instanceof Error ? e.message : 'Lỗi khi xuất Excel');
    } finally {
        isExporting.value = false;
    }
}

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
            previewPage.value = 1;
            previewSearch.value = '';
            previewStatusFilter.value = 'all';
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
                            <CardHeader class="flex flex-row items-center justify-between space-y-0">
                                <CardTitle class="text-base">
                                    Preview danh sách ({{ sortedPreviewStudents.length }} sinh viên)
                                </CardTitle>
                                <div class="flex items-center gap-2">
                                    <Button variant="outline" size="sm" :disabled="isExporting"
                                        @click="exportPreviewExcel">
                                        <Download class="mr-2 h-4 w-4" />
                                        {{ isExporting ? 'Đang xuất...' : 'Export Excel' }}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4 p-0">
                                <!-- Filters -->
                                <div class="flex flex-wrap items-center gap-3 border-b px-6 py-4">
                                    <Input v-model.trim="previewSearch" placeholder="Tìm mã SV, họ tên..."
                                        class="max-w-[220px]" @keyup.enter="previewPage = 1" />
                                    <Select v-model="previewStatusFilter">
                                        <SelectTrigger class="w-[160px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tất cả trạng thái</SelectItem>
                                            <SelectItem value="new">Mới (New Invoice)</SelectItem>
                                            <SelectItem value="update">Update/Merge</SelectItem>
                                            <SelectItem value="skip">Skipped</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button v-if="hasPreviewFilters" variant="ghost" size="sm"
                                        @click="clearPreviewFilters">
                                        <X class="mr-1 h-4 w-4" />
                                        Xóa filter
                                    </Button>
                                </div>

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="text-center w-14">STT</TableHead>
                                            <TableHead>
                                                <button type="button"
                                                    class="flex items-center gap-1 font-medium hover:opacity-80"
                                                    @click="setPreviewSort('student_id')">
                                                    Mã SV
                                                    <ArrowUpDown v-if="previewSortBy !== 'student_id'"
                                                        class="h-4 w-4 opacity-50" />
                                                    <ArrowUp v-else-if="previewSortDir === 'asc'"
                                                        class="h-4 w-4" />
                                                    <ArrowDown v-else class="h-4 w-4" />
                                                </button>
                                            </TableHead>
                                            <TableHead>
                                                <button type="button"
                                                    class="flex items-center gap-1 font-medium hover:opacity-80"
                                                    @click="setPreviewSort('full_name')">
                                                    Họ tên
                                                    <ArrowUpDown v-if="previewSortBy !== 'full_name'"
                                                        class="h-4 w-4 opacity-50" />
                                                    <ArrowUp v-else-if="previewSortDir === 'asc'"
                                                        class="h-4 w-4" />
                                                    <ArrowDown v-else class="h-4 w-4" />
                                                </button>
                                            </TableHead>
                                            <TableHead>
                                                <button type="button"
                                                    class="flex items-center gap-1 font-medium hover:opacity-80"
                                                    @click="setPreviewSort('student_type')">
                                                    Type
                                                    <ArrowUpDown v-if="previewSortBy !== 'student_type'"
                                                        class="h-4 w-4 opacity-50" />
                                                    <ArrowUp v-else-if="previewSortDir === 'asc'"
                                                        class="h-4 w-4" />
                                                    <ArrowDown v-else class="h-4 w-4" />
                                                </button>
                                            </TableHead>
                                            <TableHead>Charge Breakdown</TableHead>
                                            <TableHead>
                                                <button type="button"
                                                    class="ml-auto flex items-center gap-1 font-medium hover:opacity-80"
                                                    @click="setPreviewSort('estimated_amount')">
                                                    Net Due
                                                    <ArrowUpDown v-if="previewSortBy !== 'estimated_amount'"
                                                        class="h-4 w-4 opacity-50" />
                                                    <ArrowUp v-else-if="previewSortDir === 'asc'"
                                                        class="h-4 w-4" />
                                                    <ArrowDown v-else class="h-4 w-4" />
                                                </button>
                                            </TableHead>
                                            <TableHead>
                                                <button type="button"
                                                    class="flex items-center gap-1 font-medium hover:opacity-80"
                                                    @click="setPreviewSort('action_status')">
                                                    Trạng thái
                                                    <ArrowUpDown v-if="previewSortBy !== 'action_status'"
                                                        class="h-4 w-4 opacity-50" />
                                                    <ArrowUp v-else-if="previewSortDir === 'asc'"
                                                        class="h-4 w-4" />
                                                    <ArrowDown v-else class="h-4 w-4" />
                                                </button>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(student, index) in paginatedPreviewStudents"
                                            :key="student.id">
                                            <TableCell>
                                                <div class="text-muted-foreground text-xs text-center">
                                                    {{ (previewPage - 1) * previewPerPage + index + 1 }}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div class="font-mono text-sm">{{ student.student_id }}</div>
                                            </TableCell>
                                            <TableCell>
                                                <div class="font-medium">{{ student.full_name }}</div>
                                            </TableCell>
                                            <TableCell>
                                                <div class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                    {{ student.student_type ?? student.status }}
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

                                <!-- Pagination -->
                                <div v-if="sortedPreviewStudents.length > 0" class="border-t px-6 py-4">
                                    <DataPagination
                                        :pagination-data="previewPaginationMeta"
                                        item-name="sinh viên"
                                        @navigate="handlePreviewNavigate"
                                        @page-size-change="handlePreviewPageSizeChange"
                                    />
                                </div>
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
