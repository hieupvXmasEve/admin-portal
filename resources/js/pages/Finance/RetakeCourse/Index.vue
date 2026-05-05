<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { CreditCard } from 'lucide-vue-next';
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
    retake_fee: string;
    approved_at: string | null;
    created_at: string;
    approved_by: { name: string } | null;
}

interface Filters {
    search: string;
    semester_id: number | null;
    campus_id: number | null;
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
    baseUrl: route('finance.retake-course.index'),
    initialFilters: {
        search: props.filters?.search || '',
        semester_id: props.filters?.semester_id || null,
        campus_id: props.filters?.campus_id || null,
        sort: props.filters?.sort || null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'desc',
        search: '',
        semester_id: null,
        campus_id: null,
        sort: null,
    },
    only: ['registrations', 'filters'],
    debounce: 400,
});

const data = computed(() => props.registrations.data);

// Charge creation dialog
const showChargeDialog = ref(false);
const selectedRegistration = ref<RetakeRegistration | null>(null);
const datePickerPortalTargetRef = ref<HTMLElement | null>(null);

const chargeForm = useForm({
    registration_id: null as number | null,
    amount: '',
    payment_deadline: '',
});

const openChargeDialog = (registration: RetakeRegistration) => {
    selectedRegistration.value = registration;
    chargeForm.registration_id = registration.id;
    chargeForm.amount = registration.retake_fee;
    chargeForm.payment_deadline = '';
    showChargeDialog.value = true;
};

const submitCharge = () => {
    chargeForm.post(route('finance.retake-course.charge.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showChargeDialog.value = false;
            selectedRegistration.value = null;
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
                h('div', { class: 'text-xs text-muted-foreground max-w-[180px] truncate' }, row.original.unit.name),
            ]),
    },
    {
        header: 'Cơ sở',
        id: 'campus',
        enableSorting: false,
        cell: ({ row }) => row.original.campus.name,
    },
    {
        header: 'Học kỳ',
        id: 'semester',
        enableSorting: false,
        cell: ({ row }) => row.original.semester.name,
    },
    {
        header: 'Phí học lại',
        accessorKey: 'retake_fee',
        enableSorting: true,
        cell: ({ row }) => {
            const fee = parseFloat(row.original.retake_fee);
            return h('div', { class: 'font-mono text-sm font-medium' }, fee.toLocaleString('vi-VN') + ' đ');
        },
    },
    {
        header: 'Ngày đăng ký',
        accessorKey: 'created_at',
        enableSorting: true,
        cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString('vi-VN'),
    },
    {
        id: 'actions',
        header: '',
        enableSorting: false,
        cell: 'actions',
    },
];
</script>

<template>
    <Head title="Học lại - Tạo phí" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Học lại - Tạo phí</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Danh sách đăng ký học lại đã duyệt chờ tạo phí thanh toán.</p>
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-4">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="w-full max-w-xs">
                <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" placeholder="Tìm SV, MSSV, mã môn..." />
            </div>
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
            <template #cell-actions="{ row }">
                <Button variant="outline" size="sm" class="gap-1.5" @click="openChargeDialog(row.original)">
                    <CreditCard class="h-3.5 w-3.5" />
                    Tạo phí
                </Button>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="registrations" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Charge Creation Dialog -->
    <Dialog v-model:open="showChargeDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Tạo phí học lại</DialogTitle>
                <DialogDescription v-if="selectedRegistration">
                    {{ selectedRegistration.student.full_name }} - {{ selectedRegistration.unit.code }}
                </DialogDescription>
            </DialogHeader>
            <form ref="datePickerPortalTargetRef" @submit.prevent="submitCharge" class="space-y-4">
                <div>
                    <Label>Số tiền (VNĐ) <span class="text-destructive">*</span></Label>
                    <Input v-model="chargeForm.amount" type="number" min="0" placeholder="Số tiền phí học lại" />
                    <p v-if="chargeForm.errors.amount" class="text-xs text-destructive mt-1">{{ chargeForm.errors.amount }}</p>
                </div>
                <div>
                    <Label>Hạn thanh toán <span class="text-destructive">*</span></Label>
                    <DatePicker v-model="chargeForm.payment_deadline" placeholder="Chọn hạn thanh toán" :portal-to="datePickerPortalTargetRef ?? undefined" />
                    <p v-if="chargeForm.errors.payment_deadline" class="text-xs text-destructive mt-1">{{ chargeForm.errors.payment_deadline }}</p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showChargeDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="chargeForm.processing">
                        {{ chargeForm.processing ? 'Đang xử lý...' : 'Xác nhận tạo phí' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
