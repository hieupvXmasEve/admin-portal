<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { Eye } from 'lucide-vue-next';
import { reactive } from 'vue';
import { route } from 'ziggy-js';

interface FormSurvey {
    id: number;
    form_id: number;
    form_version_id: number;
    course_offering_id: number;
    form: {
        id: number;
        code: string;
        title: string;
    };
    course_offering: {
        id: number;
        course_code: string;
        course_title: string;
        section_code: string;
        semester: {
            id: number;
            code: string;
            name: string;
        } | null;
        unit: {
            id: number;
            code: string;
            name: string;
        };
    };
    total_students: number;
    completed_count: number;
    pending_count: number;
    created_at: string;
}

interface Semester {
    id: number;
    code: string;
    name: string;
    start_date: string;
    end_date: string;
}

interface Props {
    surveys: PaginatedResponse<FormSurvey>;
    filters: {
        search?: string;
        semester_id?: number;
        per_page?: number;
    };
    semesters: Semester[];
}

const props = defineProps<Props>();
console.log('props', props.surveys.data);
const searchForm = reactive({
    search: props.filters.search || '',
    semester_id: props.filters.semester_id?.toString() || 'all',
    per_page: props.filters.per_page || 20,
});

const applyFilters = () => {
    const params = new URLSearchParams();

    if (searchForm.search) {
        params.set('search', searchForm.search);
    }
    if (searchForm.semester_id && searchForm.semester_id !== 'all') {
        params.set('semester_id', searchForm.semester_id);
    }
    if (searchForm.per_page) {
        params.set('per_page', searchForm.per_page.toString());
    }

    const url = `/surveys${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['surveys', 'filters'],
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getCompletionRate = (survey: FormSurvey): number => {
    if (survey.total_students === 0) {
        return 0;
    }
    return Math.round((survey.completed_count / survey.total_students) * 100);
};

const getCompletionRateColor = (rate: number): string => {
    if (rate >= 80) {
        return 'text-green-600';
    }
    if (rate >= 50) {
        return 'text-yellow-600';
    }
    return 'text-red-600';
};

// Table columns definition
const columns: ColumnDef<FormSurvey>[] = [
    {
        accessorKey: 'course_offering.course_code',
        header: 'Course Code',
        cell: ({ row }) => row.original.course_offering.course_code,
    },
    {
        accessorKey: 'course_offering.course_title',
        header: 'Course Title',
        cell: ({ row }) => row.original.course_offering.course_title,
    },
    {
        accessorKey: 'course_offering.section_code',
        header: 'Section Code',
        cell: ({ row }) => row.original.course_offering.section_code,
    },
    {
        accessorKey: 'form.title',
        header: 'Survey Form',
        cell: ({ row }) => row.original.form.title,
    },
    {
        accessorKey: 'semester',
        header: 'Semester',
        cell: ({ row }) => row.original.course_offering.semester?.code || 'N/A',
    },
    {
        accessorKey: 'statistics',
        header: 'Statistics',
        cell: ({ row }) => {
            const survey = row.original;
            const rate = getCompletionRate(survey);
            return {
                total: survey.total_students,
                completed: survey.completed_count,
                pending: survey.pending_count,
                rate,
            };
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Created At',
        cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions',
    },
];

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['surveys'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    searchForm.per_page = pageSize;
    applyFilters();
};

const viewSurvey = (survey: FormSurvey) => {
    router.visit(route('surveys.show', { survey: survey.id }));
};
</script>

<template>
    <Head title="Course Surveys" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Course Surveys</h2>
            <p class="text-muted-foreground mt-1 text-sm">View and manage course survey assignments</p>
        </div>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Filter Surveys</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="searchForm.search" type="text" placeholder="Search by course code, title, or form..." @input="debouncedSearch" />
                </div>

                <div class="space-y-2">
                    <Label for="semester">Semester</Label>
                    <Select v-model="searchForm.semester_id" @update:model-value="applyFilters">
                        <SelectTrigger id="semester">
                            <SelectValue placeholder="All Semesters" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="String(semester.id)"> {{ semester.code }} - {{ semester.name }} </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="per_page">Items per page</Label>
                    <Select v-model="searchForm.per_page" @update:model-value="applyFilters">
                        <SelectTrigger id="per_page">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="10">10</SelectItem>
                            <SelectItem :value="20">20</SelectItem>
                            <SelectItem :value="50">50</SelectItem>
                            <SelectItem :value="100">100</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </CardContent>
    </Card>

    <Card class="mt-6">
        <CardContent class="pt-6">
            <DataTable :columns="columns" :data="surveys.data">
                <template #cell-statistics="{ row }">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Total:</span>
                            <span class="font-medium">{{ row.original.total_students }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Completed:</span>
                            <Badge variant="default">{{ row.original.completed_count }}</Badge>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Pending:</span>
                            <Badge variant="secondary">{{ row.original.pending_count }}</Badge>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Rate:</span>
                            <span :class="['font-semibold', getCompletionRateColor(getCompletionRate(row.original))]"> {{ getCompletionRate(row.original) }}% </span>
                        </div>
                    </div>
                </template>
                <template #cell-actions="{ row }">
                    <Button variant="ghost" size="sm" @click="viewSurvey(row.original)">
                        <Eye class="mr-2 h-4 w-4" />
                        View Details
                    </Button>
                </template>
            </DataTable>

            <div class="mt-4">
                <DataPagination :pagination-data="surveys" item-name="surveys" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </div>
        </CardContent>
    </Card>
</template>
