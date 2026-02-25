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
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface AnalysisRow {
    year_intake: string;
    year_start: number;
    total_students: number;
    ne: number;
    intake_pre_uni_gc: number;
    intake_course: number;
    defer: number;
    do: number;
    change_campus: number;
    graduated: number;
    pending: number;
    do_transfer: number;
    pending_rate: number;
    df_rate: number;
    do_rate: number;
    graduated_rate: number;
}

interface StatusRow {
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
    status_per_page: number;
    page: number;
}

interface Props {
    rows: AnalysisRow[];
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

const summary = computed(() => {
    return props.rows.reduce(
        (acc, row) => {
            acc.intake_pre_uni_gc += row.intake_pre_uni_gc;
            acc.intake_course += row.intake_course;
            acc.defer += row.defer;
            acc.do += row.do;
            acc.change_campus += row.change_campus;
            acc.graduated += row.graduated;
            acc.pending += row.pending;
            acc.do_transfer += row.do_transfer;
            acc.ne += row.ne;
            return acc;
        },
        {
            intake_pre_uni_gc: 0,
            intake_course: 0,
            defer: 0,
            do: 0,
            change_campus: 0,
            graduated: 0,
            pending: 0,
            do_transfer: 0,
            ne: 0,
        },
    );
});

const { filters, applyFilters, handlePaginationNavigate } = useTableFilters<StatusFilters>(
    studentRoutes.studentLifecycleYearlyAnalysis(),
    props.statusFilters,
    ['statusTable', 'statusFilters'],
);

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

    window.location.href = `${studentRoutes.studentLifecycleYearlyAnalysisExport()}?${params.toString()}`;
};
</script>

<template>
    <Head title="Student Lifecycle Yearly Analysis" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Lifecycle Yearly Analysis</h1>
                <p class="text-muted-foreground mt-1">
                    Cohort metrics by intake year (from semester start date).
                </p>
            </div>
            <Badge variant="outline">Generated {{ new Date(meta.generated_at).toLocaleString('vi-VN') }}</Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Yearly Table</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="rows.length === 0" class="text-muted-foreground py-8 text-center">
                    No data available for yearly lifecycle analysis.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1300px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-muted/40 border-b">
                                <th class="px-3 py-2 text-left font-semibold">Year intake</th>
                                <th class="px-3 py-2 text-right font-semibold">Intake Pre.Uni (GC)</th>
                                <th class="px-3 py-2 text-right font-semibold">INTAKE COURSE</th>
                                <th class="px-3 py-2 text-right font-semibold">DEFER</th>
                                <th class="px-3 py-2 text-right font-semibold">DO</th>
                                <th class="px-3 py-2 text-right font-semibold">CHANGE CAMPUS</th>
                                <th class="px-3 py-2 text-right font-semibold">GRADUATED</th>
                                <th class="px-3 py-2 text-right font-semibold">PENDING</th>
                                <th class="px-3 py-2 text-right font-semibold">DO - Transfer</th>
                                <th class="px-3 py-2 text-right font-semibold text-blue-500">NE</th>
                                <th class="px-3 py-2 text-right font-semibold">Pending RATE</th>
                                <th class="px-3 py-2 text-right font-semibold">DF RATE</th>
                                <th class="px-3 py-2 text-right font-semibold">DO RATE</th>
                                <th class="px-3 py-2 text-right font-semibold">GRADUATED RATE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.year_start" class="border-b">
                                <td class="px-3 py-2">{{ row.year_intake }}</td>
                                <td class="px-3 py-2 text-right">{{ row.intake_pre_uni_gc }}</td>
                                <td class="px-3 py-2 text-right">{{ row.intake_course }}</td>
                                <td class="px-3 py-2 text-right">{{ row.defer }}</td>
                                <td class="px-3 py-2 text-right">{{ row.do }}</td>
                                <td class="px-3 py-2 text-right">{{ row.change_campus }}</td>
                                <td class="px-3 py-2 text-right">{{ row.graduated }}</td>
                                <td class="px-3 py-2 text-right">{{ row.pending }}</td>
                                <td class="px-3 py-2 text-right">{{ row.do_transfer }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-blue-500">{{ row.ne }}</td>
                                <td class="px-3 py-2 text-right">{{ formatRate(row.pending_rate) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatRate(row.df_rate) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatRate(row.do_rate) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatRate(row.graduated_rate) }}</td>
                            </tr>
                            <tr class="bg-muted/40 border-b font-semibold">
                                <td class="px-3 py-2">Summary</td>
                                <td class="px-3 py-2 text-right">{{ summary.intake_pre_uni_gc }}</td>
                                <td class="px-3 py-2 text-right">{{ summary.intake_course }}</td>
                                <td class="px-3 py-2 text-right">{{ summary.defer }}</td>
                                <td class="px-3 py-2 text-right">{{ summary.do }}</td>
                                <td class="px-3 py-2 text-right">{{ summary.change_campus }}</td>
                                <td class="px-3 py-2 text-right">{{ summary.graduated }}</td>
                                <td class="px-3 py-2 text-right">{{ summary.pending }}</td>
                                <td class="px-3 py-2 text-right">{{ summary.do_transfer }}</td>
                                <td class="px-3 py-2 text-right text-blue-500">{{ summary.ne }}</td>
                                <td class="px-3 py-2 text-right">-</td>
                                <td class="px-3 py-2 text-right">-</td>
                                <td class="px-3 py-2 text-right">-</td>
                                <td class="px-3 py-2 text-right">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle>Student Status by Semester</CardTitle>
                        <CardDescription>
                            Cumulative students up to selected semester. Current filter: {{ selectedSemesterLabel }}
                        </CardDescription>
                    </div>
                    <Button variant="outline" :disabled="!filters.selected_semester_id" @click="handleExport">
                        Export Excel
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Semester</Label>
                        <Select
                            :model-value="filters.selected_semester_id ? String(filters.selected_semester_id) : 'none'"
                            @update:model-value="(val) => applyFilters({ selected_semester_id: val === 'none' ? null : Number(val), page: 1 })"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Select semester</SelectItem>
                                <SelectItem v-for="semester in statusOptions.semesters" :key="semester.id" :value="String(semester.id)">
                                    {{ semester.code }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Current Status</Label>
                        <Select
                            :model-value="filters.current_status ?? 'all'"
                            @update:model-value="(val) => applyFilters({ current_status: val === 'all' ? null : String(val), page: 1 })"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem v-for="status in statusOptions.statuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div v-if="!statusTable || statusTable.data.length === 0" class="text-muted-foreground py-8 text-center">
                    No students found for selected semester.
                </div>

                <div v-else class="space-y-3">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1600px] border-collapse text-sm">
                            <thead>
                                <tr class="bg-muted/40 border-b">
                                    <th class="px-3 py-2 text-left font-semibold">Student ID</th>
                                    <th class="px-3 py-2 text-left font-semibold">Full Name</th>
                                    <th class="px-3 py-2 text-left font-semibold">Program</th>
                                    <th class="px-3 py-2 text-left font-semibold">Intake Semester</th>
                                    <th class="px-3 py-2 text-right font-semibold">Intake Year</th>
                                    <th class="px-3 py-2 text-left font-semibold">Status @ Selected Semester</th>
                                    <th class="px-3 py-2 text-left font-semibold">Current Status</th>
                                    <th class="px-3 py-2 text-left font-semibold">Latest Action Type</th>
                                    <th class="px-3 py-2 text-left font-semibold">Latest Action Semester</th>
                                    <th class="px-3 py-2 text-left font-semibold">Defer Start Semester</th>
                                    <th class="px-3 py-2 text-left font-semibold">Dropout Semester</th>
                                    <th class="px-3 py-2 text-left font-semibold">Campus</th>
                                    <th class="px-3 py-2 text-center font-semibold text-blue-500">NE</th>
                                    <th class="px-3 py-2 text-left font-semibold">Updated At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in statusTable.data" :key="`${row.student_id}-${row.intake_semester}`" class="border-b">
                                    <td class="px-3 py-2 font-medium">{{ row.student_id }}</td>
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

                    <DataPagination
                        :pagination-data="statusTable"
                        @navigate="handlePaginationNavigate"
                        @page-size-change="(size) => applyFilters({ status_per_page: size, page: 1 })"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
