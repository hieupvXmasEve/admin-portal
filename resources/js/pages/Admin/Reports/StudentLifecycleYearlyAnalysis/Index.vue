<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTableFilters } from '@/composables/useFilters';
import type { PaginatedResponse } from '@/types';
import { getStudentStatusBadgeClass, getStudentStatusLabel } from '@/types/student';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface MatrixSemester {
    id: number;
    code: string;
    name: string;
    start_date: string;
}

type StatusCounts = Record<string, number>;

interface Cohort {
    intake_semester_id: number;
    intake_semester_code: string;
    intake_semester_start: string;
    size: number;
    ne: number;
    cells: Record<number, StatusCounts>;
    current: StatusCounts;
    ever_deferred: number;
    ever_dropped: number;
    df_rate: number;
    do_rate: number;
    graduated_rate: number;
    pending_rate: number;
}

interface SemesterEvent {
    semester_id: number;
    semester_code: string;
    entered: number;
    moved_in: StatusCounts;
}

interface Matrix {
    semesters: MatrixSemester[];
    statuses: string[];
    cohorts: Cohort[];
    events: SemesterEvent[];
    totals: {
        size: number;
        ne: number;
        current: StatusCounts;
        ever_deferred: number;
        ever_dropped: number;
        df_rate: number;
        do_rate: number;
        graduated_rate: number;
        pending_rate: number;
    };
}

interface StatusRow {
    id: number;
    student_id: string;
    full_name: string;
    program_name: string | null;
    intake_semester: string | null;
    intake_year: number | null;
    current_campus: string | null;
    status_at_selected_semester: string;
    current_status: string;
    latest_action_type: string | null;
    latest_action_effective_semester: string | null;
    ne: boolean;
    defer_start_semester: string | null;
    dropout_semester: string | null;
    updated_at: string | null;
}

interface SemesterOption {
    id: number;
    name: string;
    code: string;
    start_date: string;
    is_active: boolean;
}

interface StatusFilters {
    selected_semester_id: number | null;
    current_status: string | null;
    cohort_semester_id: number | null;
    status_per_page: number;
    page: number;
}

interface Props {
    matrix: Matrix;
    statusTable: PaginatedResponse<StatusRow> | null;
    statusFilters: StatusFilters;
    statusOptions: {
        semesters: SemesterOption[];
        statuses: Array<{ value: string; label: string }>;
    };
    meta: {
        campus_id: number | null;
        generated_at: string;
    };
}

const props = defineProps<Props>();

const formatRate = (value: number): string => `${value.toFixed(2)}%`;
const formatDateTime = (value: string | null): string => (value ? new Date(value).toLocaleString('vi-VN') : '-');
const formatActionType = (value: string | null): string => (value ? value.replaceAll('_', ' ') : '-');

const cellCount = (cohort: Cohort, semesterId: number, status: string): number => cohort.cells[semesterId]?.[status] ?? 0;

/** A column only accounts for a cohort once that cohort has actually entered. */
const cohortHasEntered = (cohort: Cohort, semester: MatrixSemester): boolean => semester.start_date >= cohort.intake_semester_start;

const columnTotal = (cohort: Cohort, semesterId: number): number => Object.values(cohort.cells[semesterId] ?? {}).reduce((sum, n) => sum + n, 0);

/**
 * A cell is a (cohort, semester, status) slice. Clicking it drives the student
 * table below rather than opening a second list, so the count and the names
 * behind it always come from the same query.
 */
const drillDown = (cohort: Cohort, semester: MatrixSemester, status: string): void => {
    if (cellCount(cohort, semester.id, status) === 0) {
        return;
    }

    applyFilters({
        selected_semester_id: semester.id,
        current_status: status,
        cohort_semester_id: cohort.intake_semester_id,
        page: 1,
    });

    document.getElementById('student-status-table')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const isDrilledInto = (cohort: Cohort, semester: MatrixSemester, status: string): boolean =>
    filters.value.cohort_semester_id === cohort.intake_semester_id && filters.value.selected_semester_id === semester.id && filters.value.current_status === status;

const drillLabel = (cohort: Cohort, semester: MatrixSemester, status: string): string => `Xem ${cellCount(cohort, semester.id, status)} sinh viên ${getStudentStatusLabel(status)} của khoá ${cohort.intake_semester_code} tại ${semester.code}`;

const activeCohortLabel = computed(() => {
    const id = filters.value.cohort_semester_id;
    return id === null ? null : (props.matrix.cohorts.find((c) => c.intake_semester_id === id)?.intake_semester_code ?? null);
});

const clearDrillDown = (): void => applyFilters({ cohort_semester_id: null, current_status: null, page: 1 });

const movedInLabel = (event: SemesterEvent): string => {
    const parts = Object.entries(event.moved_in).filter(([, n]) => n > 0);
    return parts.length === 0 ? '-' : parts.map(([status, n]) => `${getStudentStatusLabel(status)} +${n}`).join(', ');
};

const { filters, applyFilters, handlePaginationNavigate } = useTableFilters<StatusFilters>(studentRoutes.studentLifecycleYearlyAnalysis(), props.statusFilters, ['statusTable', 'statusFilters']);

const selectedSemesterLabel = computed(() => {
    const selected = props.statusOptions.semesters.find((semester) => semester.id === filters.value.selected_semester_id);
    return selected ? `${selected.code} (${selected.name})` : 'N/A';
});

const handleExport = () => {
    if (!filters.value.selected_semester_id) {
        return;
    }

    const params = new URLSearchParams({
        selected_semester_id: String(filters.value.selected_semester_id),
    });

    if (filters.value.current_status) {
        params.set('current_status', filters.value.current_status);
    }

    if (filters.value.cohort_semester_id) {
        params.set('cohort_semester_id', String(filters.value.cohort_semester_id));
    }

    window.location.href = `${studentRoutes.studentLifecycleYearlyAnalysisExport()}?${params.toString()}`;
};
</script>

<template>
    <Head title="Phân tích vòng đời sinh viên" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Phân tích vòng đời sinh viên</h1>
                <p class="text-muted-foreground mt-1">Theo dõi sinh viên từng khoá qua các kỳ học.</p>
            </div>
            <Badge variant="outline">Generated {{ new Date(meta.generated_at).toLocaleString('vi-VN') }}</Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Sinh viên theo khoá và kỳ</CardTitle>
                <CardDescription>Bấm vào một con số để xem danh sách sinh viên.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="matrix.cohorts.length === 0" class="text-muted-foreground py-8 text-center">Chưa có dữ liệu vòng đời sinh viên.</div>

                <div v-else class="space-y-8">
                    <div v-for="cohort in matrix.cohorts" :key="cohort.intake_semester_id" class="space-y-2">
                        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                            <h3 class="text-lg font-semibold">{{ cohort.intake_semester_code }}</h3>
                            <span class="text-muted-foreground text-sm">
                                {{ cohort.size }} sinh viên · NE {{ cohort.ne }} · Bảo lưu {{ formatRate(cohort.df_rate) }} · Thôi học {{ formatRate(cohort.do_rate) }} · Tốt nghiệp {{ formatRate(cohort.graduated_rate) }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-muted/40 border-b">
                                        <th class="px-3 py-2 text-left font-semibold">Trạng thái</th>
                                        <th v-for="semester in matrix.semesters" :key="semester.id" class="px-3 py-2 text-right font-semibold">
                                            {{ semester.code }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="status in matrix.statuses" :key="status" class="border-b">
                                        <td class="px-3 py-2">
                                            <span class="rounded px-2 py-1 text-xs font-medium" :class="getStudentStatusBadgeClass(status)">
                                                {{ getStudentStatusLabel(status) }}
                                            </span>
                                        </td>
                                        <td v-for="semester in matrix.semesters" :key="semester.id" class="px-1 py-1 text-right tabular-nums">
                                            <span v-if="!cohortHasEntered(cohort, semester)" class="text-muted-foreground px-2 py-1">-</span>
                                            <span v-else-if="cellCount(cohort, semester.id, status) === 0" class="text-muted-foreground px-2 py-1">0</span>
                                            <button
                                                v-else
                                                type="button"
                                                class="hover:bg-muted focus-visible:ring-ring w-full rounded px-2 py-1 text-right tabular-nums underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                                :class="isDrilledInto(cohort, semester, status) ? 'bg-primary/10 text-primary font-semibold' : ''"
                                                :aria-label="drillLabel(cohort, semester, status)"
                                                :title="drillLabel(cohort, semester, status)"
                                                @click="drillDown(cohort, semester, status)"
                                            >
                                                {{ cellCount(cohort, semester.id, status) }}
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="bg-muted/30 border-b-2 font-semibold">
                                        <td class="px-3 py-2">Tổng</td>
                                        <td v-for="semester in matrix.semesters" :key="semester.id" class="px-3 py-2 text-right tabular-nums">
                                            {{ cohortHasEntered(cohort, semester) ? columnTotal(cohort, semester.id) : '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold">Biến động theo kỳ</h3>
                        <p class="text-muted-foreground text-sm">Mỗi kỳ có bao nhiêu sinh viên nhập học mới và bao nhiêu người đổi trạng thái.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-muted/40 border-b">
                                        <th class="px-3 py-2 text-left font-semibold">Kỳ học</th>
                                        <th class="px-3 py-2 text-right font-semibold">Nhập học mới</th>
                                        <th class="px-3 py-2 text-left font-semibold">Chuyển sang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="event in matrix.events" :key="event.semester_id" class="border-b">
                                        <td class="px-3 py-2">{{ event.semester_code }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums" :class="event.entered === 0 ? 'text-muted-foreground' : ''">
                                            {{ event.entered }}
                                        </td>
                                        <td class="px-3 py-2">{{ movedInLabel(event) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold">Hiện trạng từng khoá</h3>
                        <p class="text-muted-foreground text-sm">Tình hình mỗi khoá tính đến kỳ đang học.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-muted/40 border-b">
                                        <th class="px-3 py-2 text-left font-semibold">Khoá</th>
                                        <th class="px-3 py-2 text-right font-semibold">Sĩ số</th>
                                        <th class="px-3 py-2 text-right font-semibold text-blue-500">NE</th>
                                        <th v-for="status in matrix.statuses" :key="status" class="px-3 py-2 text-right font-semibold">
                                            {{ getStudentStatusLabel(status) }}
                                        </th>
                                        <th class="px-3 py-2 text-right font-semibold">Tỷ lệ bảo lưu</th>
                                        <th class="px-3 py-2 text-right font-semibold">Tỷ lệ thôi học</th>
                                        <th class="px-3 py-2 text-right font-semibold">Tỷ lệ tốt nghiệp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cohort in matrix.cohorts" :key="cohort.intake_semester_id" class="border-b">
                                        <td class="px-3 py-2">{{ cohort.intake_semester_code }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ cohort.size }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-blue-500 tabular-nums">{{ cohort.ne }}</td>
                                        <td v-for="status in matrix.statuses" :key="status" class="px-3 py-2 text-right tabular-nums">
                                            {{ cohort.current[status] ?? 0 }}
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ formatRate(cohort.df_rate) }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatRate(cohort.do_rate) }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatRate(cohort.graduated_rate) }}</td>
                                    </tr>
                                    <tr class="bg-muted/30 font-semibold">
                                        <td class="px-3 py-2">Tất cả khoá</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ matrix.totals.size }}</td>
                                        <td class="px-3 py-2 text-right text-blue-500 tabular-nums">{{ matrix.totals.ne }}</td>
                                        <td v-for="status in matrix.statuses" :key="status" class="px-3 py-2 text-right tabular-nums">
                                            {{ matrix.totals.current[status] ?? 0 }}
                                        </td>
                                        <td class="px-3 py-2 text-right">{{ formatRate(matrix.totals.df_rate) }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatRate(matrix.totals.do_rate) }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatRate(matrix.totals.graduated_rate) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle>Danh sách sinh viên theo kỳ</CardTitle>
                        <CardDescription>
                            <template v-if="activeCohortLabel"> Sinh viên khoá {{ activeCohortLabel }} trong kỳ {{ selectedSemesterLabel }} </template>
                            <template v-else> Sinh viên trong kỳ {{ selectedSemesterLabel }} </template>
                        </CardDescription>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button v-if="activeCohortLabel" variant="ghost" size="sm" @click="clearDrillDown">Bỏ lọc khoá</Button>
                        <Button variant="outline" :disabled="!filters.selected_semester_id" @click="handleExport">Xuất Excel</Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Kỳ học</Label>
                        <Select :model-value="filters.selected_semester_id ? String(filters.selected_semester_id) : 'none'" @update:model-value="(val) => applyFilters({ selected_semester_id: val === 'none' ? null : Number(val), page: 1 })">
                            <SelectTrigger>
                                <SelectValue placeholder="Chọn kỳ học" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Chọn kỳ học</SelectItem>
                                <SelectItem v-for="semester in statusOptions.semesters" :key="semester.id" :value="String(semester.id)">
                                    {{ semester.code }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Trạng thái</Label>
                        <Select :model-value="filters.current_status ?? 'all'" @update:model-value="(val) => applyFilters({ current_status: val === 'all' ? null : String(val), page: 1 })">
                            <SelectTrigger>
                                <SelectValue placeholder="Tất cả trạng thái" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Tất cả trạng thái</SelectItem>
                                <SelectItem v-for="status in statusOptions.statuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div v-if="!statusTable || statusTable.data.length === 0" class="text-muted-foreground py-8 text-center">Không tìm thấy sinh viên nào khớp bộ lọc.</div>

                <div v-else class="space-y-3">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1600px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-muted/40 border-b">
                                    <th class="px-3 py-2 text-left font-semibold">Mã SV</th>
                                    <th class="px-3 py-2 text-left font-semibold">Họ tên</th>
                                    <th class="px-3 py-2 text-left font-semibold">Chương trình</th>
                                    <th class="px-3 py-2 text-left font-semibold">Kỳ nhập học</th>
                                    <th class="px-3 py-2 text-right font-semibold">Năm nhập học</th>
                                    <th class="px-3 py-2 text-left font-semibold">Trạng thái trong kỳ</th>
                                    <th class="px-3 py-2 text-left font-semibold">Trạng thái hiện tại</th>
                                    <th class="px-3 py-2 text-left font-semibold">Quyết định gần nhất</th>
                                    <th class="px-3 py-2 text-left font-semibold">Kỳ hiệu lực</th>
                                    <th class="px-3 py-2 text-left font-semibold">Kỳ bắt đầu bảo lưu</th>
                                    <th class="px-3 py-2 text-left font-semibold">Kỳ thôi học</th>
                                    <th class="px-3 py-2 text-left font-semibold">Cơ sở</th>
                                    <th class="px-3 py-2 text-center font-semibold text-blue-500">NE</th>
                                    <th class="px-3 py-2 text-left font-semibold">Cập nhật lúc</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in statusTable.data" :key="`${row.student_id}-${row.intake_semester}`" class="hover:bg-muted/50 cursor-pointer border-b" @click="router.visit(studentRoutes.hub.lifecycle(row.id))">
                                    <td class="px-3 py-2 font-medium text-blue-600 hover:underline">{{ row.student_id }}</td>
                                    <td class="px-3 py-2">{{ row.full_name }}</td>
                                    <td class="px-3 py-2">{{ row.program_name ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ row.intake_semester ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.intake_year ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded px-2 py-1 text-xs font-medium" :class="getStudentStatusBadgeClass(row.status_at_selected_semester)">
                                            {{ getStudentStatusLabel(row.status_at_selected_semester) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="rounded px-2 py-1 text-xs font-medium" :class="getStudentStatusBadgeClass(row.current_status)">
                                            {{ getStudentStatusLabel(row.current_status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ formatActionType(row.latest_action_type) }}</td>
                                    <td class="px-3 py-2">{{ row.latest_action_effective_semester ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ row.defer_start_semester ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ row.dropout_semester ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ row.current_campus ?? '-' }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-blue-500">{{ row.ne ? 'Yes' : 'No' }}</td>
                                    <td class="px-3 py-2">{{ formatDateTime(row.updated_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <DataPagination :pagination-data="statusTable" @navigate="handlePaginationNavigate" @page-size-change="(size) => applyFilters({ status_per_page: size, page: 1 })" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
