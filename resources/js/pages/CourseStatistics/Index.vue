<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { BarChart3, BookOpen, Calendar, ClipboardList, Eye, TrendingDown, TrendingUp, Users, UserX } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CourseStatistic {
    id: number;
    course_offering_id: number;
    course_code: string;
    course_name: string;
    credit_hours: number;
    semester: string;
    semester_code: string;
    section_code: string;
    instructor_name: string;
    delivery_mode: string;
    total_students: number;
    students_absent_exceeded: number;
    absent_exceeded_percentage: number;
    average_attendance: number;
    average_grade: number;
    grade_distribution: {
        'A+': number;
        A: number;
        'B+': number;
        B: number;
        'C+': number;
        C: number;
        'D+': number;
        D: number;
        F: number;
    };
    pass_rate: number;
    max_capacity: number;
    current_enrollment: number;
    total_sessions: number;
    allowed_absences: number;
}

interface Props {
    statistics: PaginatedResponse<CourseStatistic>;
    semesters: Array<{ id: number; name: string; code: string }>;
    filters: {
        semester_id?: number;
        search?: string;
        per_page?: number;
        sort?: string;
        direction?: string;
    };
}

const props = defineProps<Props>();

const data = computed(() => props.statistics.data);

const filters = ref({
    semester_id: props.filters.semester_id || 'all',
    search: props.filters.search || '',
    per_page: props.filters.per_page || 15,
    sort: props.filters.sort || 'course_code',
    direction: props.filters.direction || 'asc',
});

const applyFilters = (newFilters: typeof filters.value) => {
    const queryParams: Record<string, string | number> = {};

    if (newFilters.semester_id && newFilters.semester_id !== 'all') {
        queryParams.semester_id = newFilters.semester_id;
    }
    if (newFilters.search) {
        queryParams.search = newFilters.search;
    }
    if (newFilters.sort) {
        queryParams.sort = newFilters.sort;
    }
    if (newFilters.direction) {
        queryParams.direction = newFilters.direction;
    }

    router.get('/course-statistics', queryParams, {
        preserveState: true,
        preserveScroll: true,
        only: ['statistics', 'filters'],
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        semester_id: 'all',
        search: '',
        per_page: 15,
        sort: 'course_code',
        direction: 'asc',
    };
    router.get('/course-statistics', {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['statistics', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || (filters.value.semester_id && filters.value.semester_id !== 'all');
});

const columns: ColumnDef<CourseStatistic>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
    },
    {
        header: 'Course',
        id: 'course',
        enableSorting: false,
    },
    {
        header: 'Semester',
        id: 'semester',
        enableSorting: false,
    },
    {
        header: 'Instructor',
        id: 'instructor',
        enableSorting: false,
    },
    {
        header: 'Students',
        id: 'students',
        enableSorting: false,
    },
    {
        header: 'Absent Exceeded',
        id: 'absent_exceeded',
        enableSorting: false,
    },
    {
        header: 'Avg Attendance',
        id: 'average_attendance',
        enableSorting: false,
    },
    {
        header: 'Avg Grade',
        id: 'average_grade',
        enableSorting: false,
    },
    {
        header: 'Pass Rate',
        id: 'pass_rate',
        enableSorting: false,
    },
    {
        header: 'Actions',
        id: 'actions',
        enableSorting: false,
    },
];

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['statistics'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    
    const queryParams: Record<string, string | number> = { per_page: pageSize };
    
    if (filters.value.semester_id && filters.value.semester_id !== 'all') {
        queryParams.semester_id = filters.value.semester_id;
    }
    if (filters.value.search) {
        queryParams.search = filters.value.search;
    }
    if (filters.value.sort) {
        queryParams.sort = filters.value.sort;
    }
    if (filters.value.direction) {
        queryParams.direction = filters.value.direction;
    }

    router.get('/course-statistics', queryParams, {
        preserveState: true,
        preserveScroll: true,
        only: ['statistics', 'filters'],
    });
};

const goToDetailPage = (courseOfferingId: number) => {
    router.visit(`/course-statistics/${courseOfferingId}`);
};

const goToAssessmentScoresPage = (courseOfferingId: number) => {
    router.visit(`/course-statistics/${courseOfferingId}/assessment-scores`);
};
</script>

<template>
    <Head title="Course Statistics" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Course Statistics</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">View attendance and grade statistics for each course offering</p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BarChart3 class="h-5 w-5" />
                    Filters
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label>Semester</Label>
                        <Select
                            :model-value="String(filters.semester_id)"
                            @update:model-value="
                                (value) => {
                                    filters.semester_id = value === 'all' ? 'all' : Number(value);
                                    applyFilters(filters);
                                }
                            "
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All semesters</SelectItem>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="String(semester.id)">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Search</Label>
                        <DebouncedInput :model-value="filters.search" placeholder="Course code or name..." @update:model-value="handleSearch" />
                    </div>

                    <div class="flex items-end space-y-2">
                        <Button v-if="hasActiveFilters" variant="outline" @click="clearFilters" class="w-full"> Clear Filters </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <DataTable :columns="columns" :data="data">
                    <template #cell-no="{ row }">
                        {{ (statistics.current_page - 1) * statistics.per_page + row.index + 1 }}
                    </template>

                    <template #cell-course="{ row }">
                        <div class="space-y-1 min-w-[200px]">
                            <div class="flex items-center gap-2">
                                <BookOpen class="h-4 w-4 text-primary" />
                                <span class="font-mono font-semibold">{{ row.original.course_code }}</span>
                            </div>
                            <div class="text-sm text-gray-700">{{ row.original.course_name }}</div>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span v-if="row.original.section_code">Section {{ row.original.section_code }}</span>
                                <span v-if="row.original.credit_hours">• {{ row.original.credit_hours }} credits</span>
                            </div>
                        </div>
                    </template>

                    <template #cell-semester="{ row }">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Calendar class="h-4 w-4" />
                                <span class="font-medium">{{ row.original.semester }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">{{ row.original.semester_code }}</div>
                        </div>
                    </template>

                    <template #cell-instructor="{ row }">
                        <div class="text-sm">{{ row.original.instructor_name || 'Not assigned' }}</div>
                    </template>

                    <template #cell-students="{ row }">
                        <div class="space-y-1 text-center">
                            <div class="flex items-center gap-1 justify-center">
                                <Users class="h-4 w-4 text-blue-600" />
                                <span class="font-bold text-blue-600">{{ row.original.total_students }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.original.current_enrollment }}/{{ row.original.max_capacity }} enrolled
                            </div>
                        </div>
                    </template>

                    <template #cell-absent_exceeded="{ row }">
                        <div class="space-y-1 text-center">
                            <div class="flex items-center gap-1 justify-center">
                                <UserX
                                    :class="[
                                        'h-4 w-4',
                                        row.original.students_absent_exceeded > 0
                                            ? 'text-red-600'
                                            : 'text-green-600',
                                    ]"
                                />
                                <span
                                    :class="[
                                        'font-bold',
                                        row.original.students_absent_exceeded > 0
                                            ? 'text-red-600'
                                            : 'text-green-600',
                                    ]"
                                >
                                    {{ row.original.students_absent_exceeded }}
                                </span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                of {{ row.original.total_students }} students
                            </div>
                            <div class="text-xs text-muted-foreground">
                                (Max {{ row.original.allowed_absences }} absences)
                            </div>
                        </div>
                    </template>

                    <template #cell-average_attendance="{ row }">
                        <div
                            :class="[
                                'font-mono font-semibold text-center',
                                row.original.average_attendance >= 85
                                    ? 'text-green-600'
                                    : row.original.average_attendance >= 70
                                      ? 'text-yellow-600'
                                      : 'text-red-600',
                            ]"
                        >
                            {{ row.original.average_attendance.toFixed(1) }}%
                        </div>
                    </template>

                    <template #cell-average_grade="{ row }">
                        <div
                            :class="[
                                'font-mono font-semibold text-center',
                                row.original.average_grade >= 80 ? 'text-green-600' : row.original.average_grade >= 60 ? 'text-yellow-600' : 'text-red-600',
                            ]"
                        >
                            {{ row.original.average_grade > 0 ? row.original.average_grade.toFixed(1) : 'N/A' }}
                        </div>
                    </template>

                    <template #cell-pass_rate="{ row }">
                        <div class="flex items-center justify-center gap-1">
                            <TrendingUp v-if="row.original.pass_rate >= 70" :class="['h-4 w-4', row.original.pass_rate >= 90 ? 'text-green-600' : 'text-yellow-600']" />
                            <TrendingDown v-else class="h-4 w-4 text-red-600" />
                            <span
                                :class="[
                                    'font-mono font-semibold',
                                    row.original.pass_rate >= 90 ? 'text-green-600' : row.original.pass_rate >= 70 ? 'text-yellow-600' : 'text-red-600',
                                ]"
                            >
                                {{ row.original.pass_rate.toFixed(1) }}%
                            </span>
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center gap-2">
                            <Button variant="ghost" size="sm" @click="goToDetailPage(row.original.course_offering_id)">
                                <Eye class="h-4 w-4 mr-2" />
                                Attendance
                            </Button>
                            <Button variant="ghost" size="sm" @click="goToAssessmentScoresPage(row.original.course_offering_id)">
                                <ClipboardList class="h-4 w-4 mr-2" />
                                Scores
                            </Button>
                        </div>
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <DataPagination :pagination-data="statistics" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
