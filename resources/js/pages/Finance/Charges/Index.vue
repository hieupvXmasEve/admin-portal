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
import {
    formatCurrency,
    getChargeStatusBadgeClass,
    getChargeStatusLabel,
    getChargeTypeBadgeClass,
    getChargeTypeLabel,
    type ChargeStatus,
    type ChargeType,
    type FinanceCharge,
    type Semester,
} from '@/types/finance';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Plus, Search, XCircle } from 'lucide-vue-next';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { computed, ref, watch } from 'vue';

interface Props {
    charges: PaginatedResponse<FinanceCharge>;
    chargeTypes: { value: string; label: string }[];
    semesters: Semester[];
    student?: {
        id: number;
        full_name: string;
        student_id: string;
    } | null;
    filters: {
        search: string;
        student_id?: number | null;
        semester_id: string;
        charge_type: string;
        status: string;
    };
}

const props = defineProps<Props>();

// Local filter state
const search = ref(props.filters.search || '');
const semesterId = ref(props.filters.semester_id || 'all');
const chargeType = ref(props.filters.charge_type || 'all');
const status = ref(props.filters.status || 'all');

const chargesIndexRoute = computed(() =>
    props.student ? route('finance.students.charges', props.student.id) : route('finance.charges.index'),
);

const createChargeRoute = computed(() =>
    props.student ? route('finance.charges.create', { student_id: props.student.id }) : route('finance.charges.create'),
);

const pageDescription = computed(() =>
    props.student
        ? `Quản lý các khoản phí và tín dụng của ${props.student.full_name} (${props.student.student_id})`
        : 'Quản lý các khoản phí và tín dụng của sinh viên',
);

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

const applyFilters = () => {
    router.get(
        chargesIndexRoute.value,
        {
            search: search.value || undefined,
            semester_id: semesterId.value !== 'all' ? semesterId.value : undefined,
            charge_type: chargeType.value !== 'all' ? chargeType.value : undefined,
            status: status.value !== 'all' ? status.value : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const handleFilterChange = () => {
    applyFilters();
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true });
};

// Computed statistics
const totalCharges = computed(() => {
    return props.charges.data.filter((c) => c.amount > 0 && c.status === 'active').reduce((sum, c) => sum + +c.amount, 0);
});

const totalCredits = computed(() => {
    return props.charges.data.filter((c) => c.amount < 0 && c.status === 'active').reduce((sum, c) => sum + Math.abs(c.amount), 0);
});

const statusOptions = [
    { value: 'all', label: 'Tất cả trạng thái' },
    { value: 'active', label: 'Hoạt động' },
    { value: 'voided', label: 'Đã hủy' },
];

const { showConfirmDialog } = useGlobalConfirmDialog();

const handleVoidCharge = (charge: FinanceCharge) => {
    showConfirmDialog(
        {
            title: 'Xác nhận hủy khoản phí',
            message: `Bạn có chắc chắn muốn hủy khoản phí "${charge.description}" của sinh viên ${charge.student?.full_name}? Hành động này không thể hoàn tác.`,
            confirmText: 'Hủy khoản phí',
        },
        {
            onConfirm: () => {
                router.post(route('finance.charges.void', charge.id), {
                    void_reason: 'Manual void',
                });
            },
        },
    );
};
</script>

<template>

    <Head title="Finance Charges" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Finance Charges</h1>
                <p class="text-muted-foreground mt-1">{{ pageDescription }}</p>
            </div>
            <Link :href="createChargeRoute">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Tạo khoản phí mới
                </Button>
            </Link>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Tổng phí (Trang này)</CardDescription>
                    <CardTitle class="text-lg text-red-600">{{ formatCurrency(totalCharges) }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Tổng tín dụng (Trang này)</CardDescription>
                    <CardTitle class="text-lg text-green-600">{{ formatCurrency(totalCredits) }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Số dòng hiển thị</CardDescription>
                    <CardTitle class="text-lg">{{ charges.data.length }} / {{ charges.total }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle>Bộ lọc</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="relative">
                        <Search class="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input v-model="search" placeholder="Tìm sinh viên, mô tả..." class="pl-10" />
                    </div>
                    <Select v-model="semesterId" @update:model-value="handleFilterChange">
                        <SelectTrigger>
                            <SelectValue placeholder="Chọn học kỳ" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả học kỳ</SelectItem>
                            <SelectItem v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                                {{ sem.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="chargeType" @update:model-value="handleFilterChange">
                        <SelectTrigger>
                            <SelectValue placeholder="Loại phí" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả loại phí</SelectItem>
                            <SelectItem v-for="type in chargeTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="status" @update:model-value="handleFilterChange">
                        <SelectTrigger>
                            <SelectValue placeholder="Trạng thái" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <!-- Charges Table -->
        <Card>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Sinh viên</TableHead>
                            <TableHead>Loại phí</TableHead>
                            <TableHead>Mô tả</TableHead>
                            <TableHead>Học kỳ</TableHead>
                            <TableHead class="text-right">Số tiền</TableHead>
                            <TableHead>Trạng thái</TableHead>
                            <TableHead class="text-right">Thao tác</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="charges.data.length === 0">
                            <TableCell colspan="9" class="text-muted-foreground py-12 text-center">
                                Không có dữ liệu phí nào
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="charge in charges.data" :key="charge.id">
                            <TableCell>
                                <div>
                                    <div class="font-medium">{{ charge.student?.full_name ?? 'N/A' }}</div>
                                    <div class="text-muted-foreground text-xs">{{ charge.student?.student_id }}</div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :class="getChargeTypeBadgeClass(charge.charge_type as ChargeType)">
                                    {{ getChargeTypeLabel(charge.charge_type as ChargeType) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="max-w-[200px] truncate">{{ charge.description }}</TableCell>
                            <TableCell>{{ charge.semester?.name ?? '-' }}</TableCell>
                            <TableCell class="text-right font-medium"
                                :class="charge.amount < 0 ? 'text-green-600' : 'text-red-600'">
                                {{ formatCurrency(charge.amount) }}
                            </TableCell>
                            <TableCell>
                                <Badge :class="getChargeStatusBadgeClass(charge.status as ChargeStatus)">
                                    {{ getChargeStatusLabel(charge.status as ChargeStatus) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="route('finance.charges.show', charge.id)">
                                        <Button variant="outline" size="sm">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                    <Button v-if="charge.status === 'active'" variant="outline" size="sm"
                                        class="text-red-600 hover:text-red-700" @click="handleVoidCharge(charge)">
                                        <XCircle class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Pagination -->
        <DataPagination :pagination-data="charges" @navigate="handlePaginationNavigate" />
    </div>
</template>
