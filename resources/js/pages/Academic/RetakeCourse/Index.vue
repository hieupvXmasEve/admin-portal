<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Ban, Plus } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { route } from 'ziggy-js';

interface RetakeRegistration {
    id: number;
    student: { id: number; full_name: string; student_id: string };
    unit: { id: number; code: string; name: string };
    course_offering: { id: number; section_code: string | null; semester: { name: string }; campus: { name: string } | null } | null;
    semester: { id: number; name: string; code: string };
    campus: { id: number; name: string; code: string };
    status: string;
    attempt_number: number;
    retake_fee: string;
    payment_deadline: string | null;
    approved_at: string | null;
    created_at: string;
    approved_by: { name: string } | null;
}

interface Filters {
    search: string;
    status: string | null;
    semester_id: number | null;
    campus_id: number | null;
    unit_id: number | null;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

interface Props {
    registrations: PaginatedResponse<RetakeRegistration>;
    filters?: Partial<Filters>;
    semesters: { id: number; name: string; code: string }[];
    campuses: { id: number; name: string; code: string }[];
}

const props = defineProps<Props>();

const {
    filters,
    hasActiveFilters,
    isLoading,
    currentSort,
    currentDirection,
    handleSearch,
    handleSortChange,
    handlePaginationNavigate,
    handlePageSizeChange,
    handleFilterChange,
    clearAllFilters,
} = useDataTable<Filters>({
    baseUrl: route('academic.retake-course.index'),
    initialFilters: {
        search: props.filters?.search || '',
        status: props.filters?.status || null,
        semester_id: props.filters?.semester_id || null,
        campus_id: props.filters?.campus_id || null,
        unit_id: props.filters?.unit_id || null,
        sort: props.filters?.sort || null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'desc',
        search: '',
        status: null,
        semester_id: null,
        campus_id: null,
        unit_id: null,
        sort: null,
    },
    only: ['registrations', 'filters'],
    debounce: 400,
});

const data = computed(() => props.registrations.data);

const statusVariant = (status: string) => {
    switch (status) {
        case 'approved':
            return 'info';
        case 'payment_pending':
            return 'warning';
        case 'paid':
            return 'purple';
        case 'enrolled':
            return 'success';
        case 'cancelled':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'approved':
            return 'Đã duyệt';
        case 'payment_pending':
            return 'Chờ thanh toán';
        case 'paid':
            return 'Đã thanh toán';
        case 'enrolled':
            return 'Đã ghi danh';
        case 'cancelled':
            return 'Đã hủy';
        default:
            return status;
    }
};

// Cancel dialog state
const cancelDialogOpen = ref(false);
const cancelTarget = ref<RetakeRegistration | null>(null);
const cancelForm = useForm({ reason: '' });

const openCancelDialog = (registration: RetakeRegistration) => {
    cancelTarget.value = registration;
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelDialogOpen.value = true;
};

const submitCancel = () => {
    if (!cancelTarget.value) return;
    cancelForm.post(route('academic.retake-course.cancel', cancelTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelDialogOpen.value = false;
            cancelTarget.value = null;
            cancelForm.reset();
        },
    });
};

const columns: ColumnDef<RetakeRegistration>[] = [
    {
        header: '#',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.registrations.current_page;
            const perPage = props.registrations.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Sinh viên',
        id: 'student',
        enableSorting: false,
        cell: ({ row }) =>
            h('div', [
                h('div', { class: 'font-medium' }, row.original.student.full_name),
                h('div', { class: 'text-xs text-muted-foreground font-mono' }, row.original.student.student_id),
            ]),
    },
    {
        header: 'Môn học',
        id: 'unit',
        enableSorting: false,
        cell: ({ row }) =>
            h('div', [
                h('div', { class: 'font-mono text-sm font-medium' }, row.original.unit.code),
                h('div', { class: 'text-xs text-muted-foreground max-w-[200px] truncate' }, row.original.unit.name),
            ]),
    },
    {
        header: 'Học kỳ',
        id: 'semester',
        enableSorting: false,
        cell: ({ row }) => row.original.semester.name,
    },
    {
        header: 'Trạng thái',
        id: 'status',
        enableSorting: false,
        cell: 'status',
    },
    {
        header: 'Phí học lại',
        accessorKey: 'retake_fee',
        enableSorting: true,
        cell: ({ row }) => {
            const fee = parseFloat(row.original.retake_fee);
            return h('div', { class: 'font-mono text-sm' }, fee.toLocaleString('vi-VN') + ' đ');
        },
    },
    {
        header: 'Hạn TT',
        accessorKey: 'payment_deadline',
        enableSorting: true,
        cell: ({ row }) => {
            if (!row.original.payment_deadline) return '—';
            return new Date(row.original.payment_deadline).toLocaleDateString('vi-VN');
        },
    },
    {
        header: 'Ngày tạo',
        accessorKey: 'created_at',
        enableSorting: true,
        cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString('vi-VN'),
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: 'actions',
    },
];
</script>

<template>
    <Head title="Đăng ký học lại" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Đăng ký học lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Quản lý danh sách đăng ký học lại cho sinh viên.</p>
        </div>
        <Button @click="router.visit(route('academic.retake-course.create'))" class="gap-2">
            <Plus class="h-4 w-4" />
            Đăng ký mới
        </Button>
    </div>

    <div class="mt-6 flex flex-col gap-4">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="w-full max-w-xs">
                <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" placeholder="Tìm SV, MSSV, mã môn..." />
            </div>
            <Select :model-value="filters.status ?? undefined" @update:model-value="(v: string) => handleFilterChange('status', v || null)">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="approved">Đã duyệt</SelectItem>
                    <SelectItem value="payment_pending">Chờ TT</SelectItem>
                    <SelectItem value="paid">Đã TT</SelectItem>
                    <SelectItem value="enrolled">Đã ghi danh</SelectItem>
                    <SelectItem value="cancelled">Đã hủy</SelectItem>
                </SelectContent>
            </Select>
            <Select :model-value="filters.semester_id?.toString() ?? undefined" @update:model-value="(v: string) => handleFilterChange('semester_id', v ? Number(v) : null)">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Học kỳ" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="sem in semesters" :key="sem.id" :value="sem.id.toString()">{{ sem.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select :model-value="filters.campus_id?.toString() ?? undefined" @update:model-value="(v: string) => handleFilterChange('campus_id', v ? Number(v) : null)">
                <SelectTrigger class="w-[140px]">
                    <SelectValue placeholder="Cơ sở" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="c in campuses" :key="c.id" :value="c.id.toString()">{{ c.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Button variant="outline" size="sm" @click="clearAllFilters" :disabled="!hasActiveFilters" v-if="hasActiveFilters">
                Xóa bộ lọc
            </Button>
        </div>

        <DataTable
            :data="data"
            :columns="columns"
            :loading="isLoading"
            :initial-sort="currentSort ?? undefined"
            :initial-direction="currentDirection ?? undefined"
            @sort-change="handleSortChange"
        >
            <template #cell-status="{ row }">
                <Badge :variant="statusVariant(row.original.status)">
                    {{ statusLabel(row.original.status) }}
                </Badge>
            </template>
            <template #cell-actions="{ row }">
                <Button
                    v-if="row.original.status === 'approved' || row.original.status === 'payment_pending'"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 text-destructive hover:text-destructive"
                    @click="openCancelDialog(row.original)"
                >
                    <Ban class="h-4 w-4" />
                </Button>
                <span v-else></span>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="registrations" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Cancel Dialog -->
    <Dialog v-model:open="cancelDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Hủy đăng ký học lại</DialogTitle>
                <DialogDescription v-if="cancelTarget">
                    {{ cancelTarget.student.full_name }} ({{ cancelTarget.student.student_id }})
                    — {{ cancelTarget.unit.code }}
                    <template v-if="cancelTarget.status === 'payment_pending'">
                        <br />
                        <span class="text-destructive font-medium">Charge và invoice liên quan sẽ bị hủy.</span>
                    </template>
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitCancel" class="space-y-4">
                <div class="space-y-1.5">
                    <Label>Lý do hủy <span class="text-destructive">*</span></Label>
                    <Textarea v-model="cancelForm.reason" placeholder="Nhập lý do hủy (tối thiểu 5 ký tự)..." rows="3" />
                    <p v-if="cancelForm.errors.reason" class="text-xs text-destructive">{{ cancelForm.errors.reason }}</p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="cancelDialogOpen = false">Đóng</Button>
                    <Button type="submit" variant="destructive" :disabled="cancelForm.processing">
                        {{ cancelForm.processing ? 'Đang xử lý...' : 'Xác nhận hủy' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
