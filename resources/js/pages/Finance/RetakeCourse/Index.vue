<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertCircle, CreditCard, Layers } from 'lucide-vue-next';
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
    // Appended by controller: summary across all pending registrations for this student
    pending_units_count: number;
    pending_total_fee: number;
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
    chargeTypeOptions: { value: string; label: string }[];
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

// DNG creation dialog — triggers aggregate DNG for ALL pending charges of the student
const showDngDialog = ref(false);
const selectedRegistration = ref<RetakeRegistration | null>(null);

const dngForm = useForm({
    registration_id: null as number | null,
    payment_deadline: '',
});

const openDngDialog = (registration: RetakeRegistration) => {
    selectedRegistration.value = registration;
    dngForm.registration_id = registration.id;
    dngForm.payment_deadline = '';
    showDngDialog.value = true;
};

const submitDng = () => {
    dngForm.post(route('finance.retake-course.charge.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showDngDialog.value = false;
            selectedRegistration.value = null;
            dngForm.reset();
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
        header: 'Phí môn này',
        accessorKey: 'retake_fee',
        enableSorting: true,
        cell: ({ row }) => {
            const fee = parseFloat(row.original.retake_fee);
            return h('div', { class: 'font-mono text-sm' }, fee.toLocaleString('vi-VN') + ' đ');
        },
    },
    {
        header: 'Tổng DNG sẽ tạo',
        id: 'pending_total',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.pending_units_count;
            const total = row.original.pending_total_fee;
            const isMulti = count > 1;
            return h('div', { class: 'space-y-0.5' }, [
                h('div', { class: 'font-mono text-sm font-medium' + (isMulti ? ' text-amber-600' : '') },
                    total.toLocaleString('vi-VN') + ' đ'),
                isMulti
                    ? h('div', { class: 'flex items-center gap-1 text-xs text-amber-600' }, [
                        h(Layers, { class: 'h-3 w-3' }),
                        `${count} môn gộp`,
                    ])
                    : null,
            ]);
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

    <!-- Info banner explaining aggregate DNG behavior -->
    <div class="mt-4 flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>
            Khi bấm <strong>Tạo DNG</strong>, hệ thống sẽ tự động gộp <strong>tất cả môn học lại đang chờ</strong>
            của sinh viên đó thành <strong>1 yêu cầu thanh toán DNG duy nhất</strong>.
            Sinh viên thanh toán 1 lần, hệ thống tự động ghi nhận và enroll từng môn.
        </span>
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
                <Button variant="outline" size="sm" class="gap-1.5" @click="openDngDialog(row.original)">
                    <CreditCard class="h-3.5 w-3.5" />
                    Tạo DNG
                </Button>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="registrations" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- DNG Creation Dialog -->
    <Dialog v-model:open="showDngDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Tạo yêu cầu thanh toán DNG</DialogTitle>
                <DialogDescription v-if="selectedRegistration">
                    {{ selectedRegistration.student.full_name }} ({{ selectedRegistration.student.student_id }})
                </DialogDescription>
            </DialogHeader>

            <!-- Multi-unit warning -->
            <div
                v-if="selectedRegistration && selectedRegistration.pending_units_count > 1"
                class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
            >
                <Layers class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    Sinh viên này có <strong>{{ selectedRegistration.pending_units_count }} môn</strong> đang chờ thanh toán.
                    Hệ thống sẽ gộp tất cả thành
                    <strong>1 DNG: {{ selectedRegistration.pending_total_fee.toLocaleString('vi-VN') }} đ</strong>.
                </div>
            </div>

            <!-- Single unit info -->
            <div v-else-if="selectedRegistration" class="rounded-md border bg-muted/50 p-3 text-sm">
                <div class="font-medium">{{ selectedRegistration.unit.code }} — {{ selectedRegistration.unit.name }}</div>
                <div class="mt-1 text-muted-foreground">
                    Phí: <span class="font-mono font-medium">{{ parseFloat(selectedRegistration.retake_fee).toLocaleString('vi-VN') }} đ</span>
                </div>
            </div>

            <form @submit.prevent="submitDng" class="space-y-4">
                <!-- Hạn thanh toán -->
                <div class="space-y-1.5">
                    <Label>Hạn thanh toán <span class="text-destructive">*</span></Label>
                    <Input v-model="dngForm.payment_deadline" type="date" :min="new Date().toISOString().split('T')[0]" />
                    <p v-if="dngForm.errors.payment_deadline" class="text-xs text-destructive">{{ dngForm.errors.payment_deadline }}</p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showDngDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="dngForm.processing || !dngForm.payment_deadline">
                        {{ dngForm.processing ? 'Đang xử lý...' : 'Xác nhận tạo DNG' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
