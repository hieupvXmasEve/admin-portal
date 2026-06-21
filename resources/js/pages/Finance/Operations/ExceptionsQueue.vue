<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResponse } from '@/types';
import { type Semester } from '@/types/finance';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, CheckCircle2, CircleSlash, DollarSign, FileQuestion, Link2Off, PauseCircle, RotateCcw, Wrench } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface ExceptionItem {
    id: number;
    type: 'missing_charge' | 'zero_tuition_waived' | 'deferred_enrolled' | 'retake_no_charge' | 'defer_no_case' | 'mismatch';
    student_id: number;
    student_code: string;
    student_name: string;
    description: string;
    severity: 'high' | 'medium' | 'low';
    context: Record<string, any>;
    created_at: string;
    fixable: boolean;
}

interface ExceptionCounts {
    missing_charge: number;
    zero_tuition_waived: number;
    deferred_enrolled: number;
    retake_no_charge: number;
    defer_no_case: number;
    mismatch: number;
}

interface Props {
    exceptions: PaginatedResponse<ExceptionItem>;
    counts: ExceptionCounts;
    semesters: Semester[];
    filters: {
        semester_id: string | null;
        type: string;
    };
    currentSemester: Semester | null;
}

const props = defineProps<Props>();
const { showConfirmDialog } = useGlobalConfirmDialog();
const { selectedId: globalSemesterId, selectedLabel } = useFinanceSemester();

// Semester is driven by the global top-bar SemesterSwitcher (shared `semester` prop).
const activeTab = ref(props.filters.type || 'all');

watch(activeTab, () => {
    applyFilters();
});

const applyFilters = () => {
    router.get(
        route('finance.operations.exceptions'),
        {
            type: activeTab.value !== 'all' ? activeTab.value : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true });
};

// Get exception type icon
const getTypeIcon = (type: string) => {
    switch (type) {
        case 'missing_charge':
            return DollarSign;
        case 'zero_tuition_waived':
            return CircleSlash;
        case 'deferred_enrolled':
            return PauseCircle;
        case 'retake_no_charge':
            return RotateCcw;
        case 'defer_no_case':
            return FileQuestion;
        case 'mismatch':
            return Link2Off;
        default:
            return AlertTriangle;
    }
};

const getTypeLabel = (type: string) => {
    switch (type) {
        case 'missing_charge':
            return 'Missing Charge';
        case 'zero_tuition_waived':
            return 'Zero Tuition (Waived)';
        case 'deferred_enrolled':
            return 'Deferred (Retained)';
        case 'retake_no_charge':
            return 'Retake - No Charge';
        case 'defer_no_case':
            return 'Defer - No Case';
        case 'mismatch':
            return 'Mismatch';
        default:
            return type;
    }
};

const getTypeBadgeClass = (type: string) => {
    switch (type) {
        case 'missing_charge':
            return 'bg-red-100 text-red-800';
        case 'zero_tuition_waived':
            return 'bg-sky-100 text-sky-800';
        case 'deferred_enrolled':
            return 'bg-teal-100 text-teal-800';
        case 'retake_no_charge':
            return 'bg-orange-100 text-orange-800';
        case 'defer_no_case':
            return 'bg-purple-100 text-purple-800';
        case 'mismatch':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getSeverityBadgeClass = (severity: string) => {
    switch (severity) {
        case 'high':
            return 'bg-red-500 text-white';
        case 'medium':
            return 'bg-yellow-500 text-white';
        case 'low':
            return 'bg-blue-500 text-white';
        default:
            return 'bg-gray-500 text-white';
    }
};

// Fix exception
const fixingId = ref<number | null>(null);

const runFixException = async (exception: ExceptionItem) => {
    if (!exception.fixable) {
        toast.error('Exception này cần xử lý thủ công');
        return;
    }

    fixingId.value = exception.id;

    try {
        const response = await fetch(route('api.finance.operations.fix-exception', exception.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                semester_id: globalSemesterId.value ?? undefined,
            }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            toast.success(data.data?.message ?? 'Đã sửa lỗi thành công');
            router.reload({ only: ['exceptions', 'counts'] });
            return;
        }

        toast.error(data.message || 'Không thể sửa lỗi');
    } catch {
        toast.error('Lỗi khi sửa exception');
    } finally {
        fixingId.value = null;
    }
};

const fixException = (exception: ExceptionItem) => {
    showConfirmDialog(
        {
            title: 'Xác nhận sửa lỗi billing',
            message: `Tạo dữ liệu billing còn thiếu cho sinh viên ${exception.student_name} (${exception.student_code})? Thao tác có thể sinh khoản phí / điều chỉnh và được ghi vào dữ liệu tài chính.`,
            confirmText: 'Sửa ngay',
            cancelText: 'Huỷ bỏ',
        },
        {
            onConfirm: () => runFixException(exception),
        },
    );
};

// Total exceptions
const totalExceptions = computed(() => {
    return (
        props.counts.missing_charge +
        props.counts.zero_tuition_waived +
        props.counts.deferred_enrolled +
        props.counts.retake_no_charge +
        props.counts.defer_no_case +
        props.counts.mismatch
    );
});

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Billing Exceptions" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Billing Exceptions</h1>
                <p class="text-muted-foreground mt-1">Hàng đợi các trường hợp thiếu dữ liệu billing · {{ selectedLabel }}</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            <Card :class="{ 'ring-primary ring-2': activeTab === 'all' }" class="cursor-pointer" @click="activeTab = 'all'">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription>Tổng cộng</CardDescription>
                        <AlertTriangle class="h-4 w-4 text-gray-400" />
                    </div>
                    <CardTitle class="text-2xl">{{ totalExceptions }}</CardTitle>
                </CardHeader>
            </Card>

            <Card :class="{ 'ring-2 ring-red-500': activeTab === 'missing_charge' }" class="cursor-pointer" @click="activeTab = 'missing_charge'">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription>Missing Charge</CardDescription>
                        <DollarSign class="h-4 w-4 text-red-500" />
                    </div>
                    <CardTitle class="text-2xl text-red-600">{{ counts.missing_charge }}</CardTitle>
                </CardHeader>
            </Card>

            <Card :class="{ 'ring-2 ring-sky-500': activeTab === 'zero_tuition_waived' }" class="cursor-pointer" @click="activeTab = 'zero_tuition_waived'">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription>Zero Tuition</CardDescription>
                        <CircleSlash class="h-4 w-4 text-sky-500" />
                    </div>
                    <CardTitle class="text-2xl text-sky-600">{{ counts.zero_tuition_waived }}</CardTitle>
                </CardHeader>
            </Card>

            <Card :class="{ 'ring-2 ring-teal-500': activeTab === 'deferred_enrolled' }" class="cursor-pointer" @click="activeTab = 'deferred_enrolled'">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription>Deferred</CardDescription>
                        <PauseCircle class="h-4 w-4 text-teal-500" />
                    </div>
                    <CardTitle class="text-2xl text-teal-600">{{ counts.deferred_enrolled }}</CardTitle>
                </CardHeader>
            </Card>

            <Card :class="{ 'ring-2 ring-orange-500': activeTab === 'retake_no_charge' }" class="cursor-pointer" @click="activeTab = 'retake_no_charge'">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription>Retake No Charge</CardDescription>
                        <RotateCcw class="h-4 w-4 text-orange-500" />
                    </div>
                    <CardTitle class="text-2xl text-orange-600">{{ counts.retake_no_charge }}</CardTitle>
                </CardHeader>
            </Card>

            <Card :class="{ 'ring-2 ring-purple-500': activeTab === 'defer_no_case' }" class="cursor-pointer" @click="activeTab = 'defer_no_case'">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription>Defer No Case</CardDescription>
                        <FileQuestion class="h-4 w-4 text-purple-500" />
                    </div>
                    <CardTitle class="text-2xl text-purple-600">{{ counts.defer_no_case }}</CardTitle>
                </CardHeader>
            </Card>

            <Card :class="{ 'ring-2 ring-yellow-500': activeTab === 'mismatch' }" class="cursor-pointer" @click="activeTab = 'mismatch'">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <CardDescription>Mismatch</CardDescription>
                        <Link2Off class="h-4 w-4 text-yellow-500" />
                    </div>
                    <CardTitle class="text-2xl text-yellow-600">{{ counts.mismatch }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <!-- Exception Type Descriptions -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Mô tả loại exception</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 text-sm md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <div class="flex items-start gap-2">
                        <DollarSign class="mt-0.5 h-4 w-4 text-red-500" />
                        <div>
                            <span class="font-medium">Missing Charge:</span>
                            <span class="text-muted-foreground"> SV đăng ký học nhưng chưa có phí học kỳ cần thu</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <CircleSlash class="mt-0.5 h-4 w-4 text-sky-500" />
                        <div>
                            <span class="font-medium">Zero Tuition:</span>
                            <span class="text-muted-foreground"> Tuition plan kỳ này = 0, không cần sinh charge (thông tin)</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <PauseCircle class="mt-0.5 h-4 w-4 text-teal-500" />
                        <div>
                            <span class="font-medium">Deferred (Retained):</span>
                            <span class="text-muted-foreground"> SV bảo lưu nhưng vẫn giữ lớp — không cần sinh phí (thông tin)</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <RotateCcw class="mt-0.5 h-4 w-4 text-orange-500" />
                        <div>
                            <span class="font-medium">Retake No Charge:</span>
                            <span class="text-muted-foreground"> SV học lại nhưng chưa có phí retake</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <FileQuestion class="mt-0.5 h-4 w-4 text-purple-500" />
                        <div>
                            <span class="font-medium">Defer No Case:</span>
                            <span class="text-muted-foreground"> Defer action nhưng chưa có defer_case</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <Link2Off class="mt-0.5 h-4 w-4 text-yellow-500" />
                        <div>
                            <span class="font-medium">Mismatch:</span>
                            <span class="text-muted-foreground"> Invoice total ≠ sum charges, payment chưa allocate</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Exceptions Table -->
        <Card>
            <CardHeader>
                <CardTitle>
                    Danh sách Exceptions
                    <Badge variant="outline" class="ml-2">{{ exceptions.total || 0 }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Loại</TableHead>
                            <TableHead>Sinh viên</TableHead>
                            <TableHead>Mô tả</TableHead>
                            <TableHead>Mức độ</TableHead>
                            <TableHead>Thời gian</TableHead>
                            <TableHead class="text-right">Thao tác</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="exceptions.data.length === 0">
                            <TableCell colspan="6" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <CheckCircle2 class="h-12 w-12 text-green-500" />
                                    <p class="text-muted-foreground">Không có exception nào!</p>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="exception in exceptions.data" :key="exception.id">
                            <TableCell>
                                <Badge :class="getTypeBadgeClass(exception.type)">
                                    <component :is="getTypeIcon(exception.type)" class="mr-1 h-3 w-3" />
                                    {{ getTypeLabel(exception.type) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div>
                                    <div class="font-medium">{{ exception.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ exception.student_code }}</div>
                                </div>
                            </TableCell>
                            <TableCell class="max-w-[300px]">
                                <p class="text-sm">{{ exception.description }}</p>
                            </TableCell>
                            <TableCell>
                                <Badge :class="getSeverityBadgeClass(exception.severity)" class="text-xs">
                                    {{ exception.severity.toUpperCase() }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-sm">
                                {{ new Date(exception.created_at).toLocaleDateString('vi-VN') }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        v-if="exception.fixable"
                                        variant="outline"
                                        size="sm"
                                        :disabled="fixingId === exception.id"
                                        @click="fixException(exception)"
                                    >
                                        <Wrench class="mr-1 h-4 w-4" />
                                        {{ fixingId === exception.id ? 'Đang xử lý...' : 'Fix now' }}
                                    </Button>
                                    <Link :href="route('finance.students.charges', exception.student_id)">
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
        <DataPagination :pagination-data="exceptions" @navigate="handlePaginationNavigate" />
    </div>
</template>
