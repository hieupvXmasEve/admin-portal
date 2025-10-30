<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { FailReasonDistribution, FailedStudent, FailedStudentsSummary, FailedUnitDistribution, PaginatedResponse, Program, Semester, Unit } from '@/types';
import { Head } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertTriangle, BookOpen, Download, ShieldCheck, TrendingDown, UserX, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import OverridePassDialog from './OverridePassDialog.vue';

interface FailedStudentFilters {
    semester_id: string;
    program_id: string;
    unit_id: string;
    attempt_number: string;
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface Props {
    failed_students: PaginatedResponse<FailedStudent>;
    summary: FailedStudentsSummary | null;
    fail_reasons: FailReasonDistribution | null;
    unit_distribution: FailedUnitDistribution[] | null;
    semesters: Semester[];
    programs: Program[];
    units: Unit[];
    filters: Partial<FailedStudentFilters>;
}

const props = defineProps<Props>();

const data = computed(() => props.failed_students.data);

const filterConfig = useInertiaFilters<FailedStudentFilters>({
    baseUrl: '/failed-students',
    initialFilters: {
        semester_id: props.filters.semester_id?.toString() || '',
        program_id: props.filters.program_id?.toString() || 'all',
        unit_id: props.filters.unit_id?.toString() || 'all',
        attempt_number: props.filters.attempt_number || 'all',
        search: props.filters.search || '',
        per_page: props.filters.per_page || 15,
        sort: props.filters.sort || null,
        direction: (props.filters.direction as 'asc' | 'desc') || null,
    },
    emptyFilters: {
        semester_id: '',
        program_id: 'all',
        unit_id: 'all',
        attempt_number: 'all',
        search: '',
        per_page: 15,
        sort: null,
        direction: null,
    },
    defaultValues: {
        per_page: 15,
        program_id: 'all',
        unit_id: 'all',
        attempt_number: 'all',
    },
    only: ['failed_students', 'summary', 'fail_reasons', 'unit_distribution', 'filters'],
    debounce: 400,
});

const { filters: activeFilters, hasActiveFilters, clearFilters, handleSearch, handleSortChange, handlePageSizeChange, handlePaginationNavigate } = filterConfig;

// Get sort values directly from filters object (avoiding reactive proxy issues)
const currentSort = computed(() => {
    const sortValue = activeFilters.sort;
    return sortValue ? String(sortValue) : undefined;
});
const currentDirection = computed(() => {
    const dirValue = activeFilters.direction;
    return dirValue ? (String(dirValue) as 'asc' | 'desc') : undefined;
});

const handleExport = () => {
    const queryParams: Record<string, string | number> = {};

    if (activeFilters.semester_id) {
        queryParams.semester_id = activeFilters.semester_id;
    }
    if (activeFilters.program_id && activeFilters.program_id !== 'all') {
        queryParams.program_id = activeFilters.program_id;
    }
    if (activeFilters.unit_id && activeFilters.unit_id !== 'all') {
        queryParams.unit_id = activeFilters.unit_id;
    }
    if (activeFilters.attempt_number && activeFilters.attempt_number !== 'all') {
        queryParams.attempt_number = activeFilters.attempt_number;
    }
    if (activeFilters.search) {
        queryParams.search = activeFilters.search;
    }

    const params = new URLSearchParams(queryParams as Record<string, string>);
    window.location.href = `/failed-students/export?${params.toString()}`;
};

const columns: ColumnDef<FailedStudent>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
    },
    {
        header: 'Student ID',
        id: 'student_id',
        enableSorting: true,
    },
    {
        header: 'Student Name',
        id: 'student_name',
        enableSorting: true,
    },
    {
        header: 'Program',
        id: 'program',
        enableSorting: false,
    },
    {
        header: 'Unit',
        id: 'unit',
        enableSorting: true,
    },
    {
        header: 'Section',
        id: 'section',
        enableSorting: false,
    },
    {
        header: 'Lecturer',
        id: 'lecturer',
        enableSorting: false,
    },
    {
        header: 'Final %',
        id: 'final_percentage',
        enableSorting: true,
    },
    {
        header: 'Grade',
        id: 'grade',
        enableSorting: false,
    },
    {
        header: 'Attendance %',
        id: 'attendance',
        enableSorting: true,
    },
    {
        header: 'Attempt',
        id: 'attempt',
        enableSorting: true,
    },
    {
        header: 'Actions',
        id: 'actions',
        enableSorting: false,
    },
];

const getAttemptBadgeVariant = (attemptNumber: number) => {
    if (attemptNumber === 1) return 'default';
    if (attemptNumber === 2) return 'warning';
    return 'destructive';
};

const overrideDialogOpen = ref(false);
const selectedStudent = ref<FailedStudent | null>(null);

const openOverrideDialog = (student: FailedStudent) => {
    console.log('student', student);
    selectedStudent.value = student;
    overrideDialogOpen.value = true;
};
</script>

<template>
    <Head title="Failed Students" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Failed Students</h1>
                <p class="text-muted-foreground">Overview of students who failed courses</p>
            </div>
            <Button v-if="filters.semester_id" @click="handleExport" variant="outline" size="sm">
                <Download class="mr-2 h-4 w-4" />
                Export to Excel
            </Button>
        </div>

        <!-- Summary Cards -->
        <div v-if="summary" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Failed Students</CardTitle>
                    <UserX class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-red-600">{{ summary.total_failed_students }}</div>
                    <p class="text-muted-foreground mt-1 text-xs">Unique students who failed</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Failed Courses</CardTitle>
                    <BookOpen class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ summary.total_failed_courses }}</div>
                    <p class="text-muted-foreground mt-1 text-xs">Course offerings with failures</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Avg Attendance</CardTitle>
                    <TrendingDown class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ summary.average_attendance_of_failed.toFixed(1) }}%</div>
                    <p class="text-muted-foreground mt-1 text-xs">Of failed students</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Retake Eligible</CardTitle>
                    <Users class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-orange-600">{{ summary.retake_eligible_count }}</div>
                    <p class="text-muted-foreground mt-1 text-xs">Can retake (≤2 attempts)</p>
                </CardContent>
            </Card>
        </div>

        <!-- Most Failed Units Card -->
        <Card v-if="summary && summary.most_failed_units.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 text-orange-500" />
                    Most Failed Units
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div v-for="(unit, index) in summary.most_failed_units" :key="unit.unit_code" class="flex items-center justify-between border-b pb-2 last:border-0">
                        <div class="flex items-center gap-3">
                            <Badge variant="outline" class="font-mono">{{ index + 1 }}</Badge>
                            <div>
                                <p class="font-semibold">{{ unit.unit_code }}</p>
                                <p class="text-muted-foreground text-sm">{{ unit.unit_name }}</p>
                            </div>
                        </div>
                        <Badge variant="destructive" class="font-bold">{{ unit.count }} students</Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <div class="space-y-2">
                        <Label for="semester">Semester *</Label>
                        <Select v-model="activeFilters.semester_id">
                            <SelectTrigger id="semester">
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="String(semester.id)">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="program">Program</Label>
                        <Select v-model="activeFilters.program_id">
                            <SelectTrigger id="program">
                                <SelectValue placeholder="All Programs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Programs</SelectItem>
                                <SelectItem v-for="program in programs" :key="program.id" :value="String(program.id)">
                                    {{ program.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="unit">Unit</Label>
                        <Select v-model="activeFilters.unit_id">
                            <SelectTrigger id="unit">
                                <SelectValue placeholder="All Units" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Units</SelectItem>
                                <SelectItem v-for="unit in units" :key="unit.id" :value="String(unit.id)"> {{ unit.code }} - {{ unit.name }} </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="attempt">Attempt Number</Label>
                        <Select v-model="activeFilters.attempt_number">
                            <SelectTrigger id="attempt">
                                <SelectValue placeholder="All Attempts" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Attempts</SelectItem>
                                <SelectItem value="1">1st Attempt</SelectItem>
                                <SelectItem value="2">2nd Attempt</SelectItem>
                                <SelectItem value="3+">3rd+ Attempt</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="search">Search</Label>
                        <DebouncedInput id="search" :model-value="activeFilters.search" placeholder="Student ID or name..." @update:model-value="handleSearch" />
                    </div>
                </div>

                <div v-if="hasActiveFilters" class="mt-4">
                    <Button variant="ghost" size="sm" @click="clearFilters">Clear Filters</Button>
                </div>
            </CardContent>
        </Card>

        <!-- No Semester Selected -->
        <Card v-if="!activeFilters.semester_id" class="border-orange-200 bg-orange-50">
            <CardContent class="flex items-center gap-3 py-6">
                <AlertTriangle class="h-6 w-6 text-orange-600" />
                <p class="font-medium text-orange-800">Please select a semester to view failed students data.</p>
            </CardContent>
        </Card>

        <!-- Data Table -->
        <Card v-else>
            <CardHeader>
                <CardTitle>Failed Students List ({{ failed_students.total }} records)</CardTitle>
            </CardHeader>
            <CardContent>
                <DataTable :columns="columns" :data="data" enable-server-sorting :initial-sort="currentSort" :initial-direction="currentDirection" @sort-change="handleSortChange">
                    <template #cell-no="{ row }">
                        {{ (failed_students.current_page - 1) * failed_students.per_page + row.index + 1 }}
                    </template>
                    <template #cell-student_id="{ row }">
                        <div class="font-mono text-sm">{{ row.original.student_id }}</div>
                    </template>
                    <template #cell-student_name="{ row }">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ row.original.student_name }}</span>
                                <TooltipProvider v-if="row.original.override_pass">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Badge variant="warning" class="flex items-center gap-1">
                                                <AlertTriangle class="h-3 w-3" />
                                                Override
                                            </Badge>
                                        </TooltipTrigger>
                                        <TooltipContent class="max-w-xs">
                                            <p class="font-semibold">Override Reason:</p>
                                            <p class="text-sm">{{ row.original.override_reason }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                            <div class="text-muted-foreground text-xs">{{ row.original.student_email }}</div>
                        </div>
                    </template>
                    <template #cell-program="{ row }">
                        <div class="text-sm">{{ row.original.program_name }}</div>
                    </template>
                    <template #cell-unit="{ row }">
                        <div>
                            <div class="text-sm font-semibold">{{ row.original.unit_code }}</div>
                            <div class="text-muted-foreground text-xs">{{ row.original.unit_name }}</div>
                        </div>
                    </template>
                    <template #cell-section="{ row }">
                        <Badge variant="outline" class="font-mono">
                            {{ row.original.course_offering_section }}
                        </Badge>
                    </template>
                    <template #cell-lecturer="{ row }">
                        <div class="text-sm">{{ row.original.lecturer_name }}</div>
                    </template>
                    <template #cell-final_percentage="{ row }">
                        <div class="font-semibold" :class="row.original.final_percentage < 50 ? 'text-red-600' : ''">{{ row.original.final_percentage.toFixed(2) }}%</div>
                    </template>
                    <template #cell-grade="{ row }">
                        <Badge variant="destructive" class="font-bold">
                            {{ row.original.final_letter_grade }}
                        </Badge>
                    </template>
                    <template #cell-attendance="{ row }">
                        <div :class="row.original.attendance_percentage < 80 ? 'font-semibold text-orange-600' : ''">{{ row.original.attendance_percentage.toFixed(2) }}%</div>
                    </template>
                    <template #cell-attempt="{ row }">
                        <Badge :variant="getAttemptBadgeVariant(row.original.attempt_number)">
                            {{ row.original.attempt_number }}
                            {{ row.original.attempt_number === 1 ? 'st' : row.original.attempt_number === 2 ? 'nd' : 'rd+' }}
                        </Badge>
                    </template>
                    <template #cell-actions="{ row }">
                        <Button v-if="!row.original.override_pass" variant="outline" size="sm" @click="openOverrideDialog(row.original)">
                            <ShieldCheck class="mr-2 h-4 w-4" />
                            Override Pass
                        </Button>
                        <Badge v-else variant="secondary" class="flex items-center gap-1">
                            <ShieldCheck class="h-3 w-3" />
                            Overridden
                        </Badge>
                    </template>
                </DataTable>

                <DataPagination class="mt-4" :pagination-data="failed_students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>

        <!-- Analytics Section -->
        <div v-if="fail_reasons && unit_distribution" class="grid gap-4 lg:grid-cols-2">
            <!-- Fail Reason Distribution -->
            <Card>
                <CardHeader>
                    <CardTitle>Fail Reason Distribution</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Low Grade Only (&lt;50%)</span>
                            <Badge variant="destructive">{{ fail_reasons.low_grade }}</Badge>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Poor Attendance Only (&lt;80%)</span>
                            <Badge variant="warning">{{ fail_reasons.poor_attendance }}</Badge>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm">Both Low Grade & Poor Attendance</span>
                            <Badge variant="secondary">{{ fail_reasons.both }}</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Unit Distribution -->
            <Card>
                <CardHeader>
                    <CardTitle>Top 10 Failed Units</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <div v-for="unit in unit_distribution" :key="unit.unit_code" class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ unit.unit_code }}</span>
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                    <div
                                        class="h-full bg-red-600"
                                        :style="{
                                            width: `${(unit.count / (unit_distribution[0]?.count || 1)) * 100}%`,
                                        }"
                                    ></div>
                                </div>
                                <span class="w-8 text-right font-semibold">{{ unit.count }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Override Pass Dialog -->
        <OverridePassDialog v-model:open="overrideDialogOpen" :student="selectedStudent" @success="() => {}" />
    </div>
</template>
