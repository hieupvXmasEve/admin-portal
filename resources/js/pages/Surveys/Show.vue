<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import FormResponseView from '@/components/forms/FormResponseView.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { FormBuilderQuestion, FormBuilderSection } from '@/types/forms';
import { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, CheckCircle, Clock, Eye, FileText, Users, XCircle } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { route } from 'ziggy-js';
import { debounce } from 'lodash-es';

interface FormSurvey {
    id: number;
    form_id: number;
    form_version_id: number;
    course_offering_id: number;
    form: {
        id: number;
        code: string;
        title: string;
        description: string | null;
        type: 'feedback' | 'survey' | 'query';
    };
    form_version?: {
        id: number;
        sections?: Array<{
            id: number;
            title: string;
            description: string | null;
            order_index: number;
            questions?: Array<{
                id: number;
                code: string;
                text: string;
                type: string;
                is_required: boolean;
                help_text: string | null;
                order_index: number;
                options?: Array<{
                    id: number;
                    value: string;
                    label: string;
                    order_index: number;
                }>;
            }>;
        }>;
        questions?: Array<{
            id: number;
            code: string;
            text: string;
            type: string;
            is_required: boolean;
            help_text: string | null;
            order_index: number;
            options?: Array<{
                id: number;
                value: string;
                label: string;
                order_index: number;
            }>;
        }>;
    };
    course_offering: {
        id: number;
        course_code: string;
        course_title: string;
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
    created_at: string;
}

interface StudentFormSurvey {
    id: number;
    form_survey_id: number;
    student_id: number;
    status: 'not_started' | 'in_progress' | 'completed';
    response_id: number | null;
    completed_at: string | null;
    student: {
        id: number;
        student_id: string;
        full_name: string;
        email: string;
    };
    response?: {
        id: number;
        submitted_at: string;
        answers?: Array<{
            question_id: number;
            question_code?: string;
            question_text: string;
            question_type: string;
            answer_text: string | null;
            answer_number: number | null;
            answer_date: string | null;
            selected_options?: Array<{
                id: number;
                text: string;
                value: string;
            }>;
        }>;
    };
}

interface Statistics {
    total_students: number;
    completed: number;
    pending: number;
    completion_rate: number;
}

interface Props {
    survey: FormSurvey;
    studentSurveys: PaginatedResponse<StudentFormSurvey>;
    statistics: Statistics;
    filters: {
        status?: string;
        search?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const searchForm = reactive({
    search: props.filters.search || '',
    status: props.filters.status || 'all',
    per_page: props.filters.per_page || 20,
});

const selectedStudentSurvey = ref<StudentFormSurvey | null>(null);
const showResponseDialog = ref(false);

const applyFilters = () => {
    const params = new URLSearchParams();

    if (searchForm.search) {
        params.set('search', searchForm.search);
    }
    if (searchForm.status && searchForm.status !== 'all') {
        params.set('status', searchForm.status);
    }
    if (searchForm.per_page) {
        params.set('per_page', searchForm.per_page.toString());
    }

    const url = route('surveys.show', { survey: props.survey.id });
    const fullUrl = `${url}${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(fullUrl, {
        preserveState: true,
        preserveScroll: true,
        only: ['studentSurveys', 'statistics', 'filters'],
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const formatDate = (date: string | null) => {
    if (!date) {
        return 'N/A';
    }
    return new Date(date).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Map formVersion to FormBuilderSection/FormBuilderQuestion format
const formSections = computed<FormBuilderSection[]>(() => {
    if (!props.survey.form_version?.sections) {
        return [];
    }
    return props.survey.form_version.sections.map((section) => ({
        id: section.id,
        title: section.title,
        description: section.description || undefined,
        order_index: section.order_index,
        questions: (section.questions || []).map((q) => ({
            id: q.id,
            code: q.code,
            text: q.text,
            type: q.type as any,
            is_required: q.is_required,
            help_text: q.help_text || undefined,
            order_index: q.order_index,
            options: (q.options || []).map((opt) => ({
                id: opt.id,
                value: opt.value,
                label: opt.label,
                order_index: opt.order_index,
                allows_free_text: false,
            })),
        })),
    }));
});

const formQuestions = computed<FormBuilderQuestion[]>(() => {
    if (!props.survey.form_version?.questions) {
        return [];
    }
    // Filter out questions that belong to sections
    const sectionQuestionIds = new Set<number>();
    props.survey.form_version.sections?.forEach((section) => {
        section.questions?.forEach((q) => {
            sectionQuestionIds.add(q.id);
        });
    });

    return props.survey.form_version.questions
        .filter((q) => !sectionQuestionIds.has(q.id))
        .map((q) => ({
            id: q.id,
            code: q.code,
            text: q.text,
            type: q.type as any,
            is_required: q.is_required,
            help_text: q.help_text || undefined,
            order_index: q.order_index,
            options: (q.options || []).map((opt) => ({
                id: opt.id,
                value: opt.value,
                label: opt.label,
                order_index: opt.order_index,
                allows_free_text: false,
            })),
        }));
});

// Map answers to include question_code
const mappedAnswers = computed(() => {
    if (!selectedStudentSurvey.value?.response?.answers) {
        return [];
    }
    return selectedStudentSurvey.value.response.answers.map((answer) => {
        // Find question code from formVersion
        let questionCode: string | undefined;
        if (props.survey.form_version) {
            // Search in sections
            for (const section of props.survey.form_version.sections || []) {
                const question = section.questions?.find((q) => q.id === answer.question_id);
                if (question) {
                    questionCode = question.code;
                    break;
                }
            }
            // Search in standalone questions
            if (!questionCode) {
                const question = props.survey.form_version.questions?.find((q) => q.id === answer.question_id);
                if (question) {
                    questionCode = question.code;
                }
            }
        }
        return {
            ...answer,
            question_code: questionCode || answer.question_code,
        };
    });
});

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return { variant: 'default' as const, label: 'Completed', icon: CheckCircle };
        case 'in_progress':
            return { variant: 'secondary' as const, label: 'In Progress', icon: Clock };
        default:
            return { variant: 'outline' as const, label: 'Not Started', icon: XCircle };
    }
};

const viewResponse = (studentSurvey: StudentFormSurvey) => {
    if (!studentSurvey.response_id) {
        return;
    }
    selectedStudentSurvey.value = studentSurvey;
    showResponseDialog.value = true;
};

const formatAnswer = (answer: StudentFormSurvey['response']['answers'][0]) => {
    if (answer.answer_text) {
        return answer.answer_text;
    }
    if (answer.answer_number !== null) {
        return answer.answer_number.toString();
    }
    if (answer.answer_date) {
        return formatDate(answer.answer_date);
    }
    if (answer.selected_options && answer.selected_options.length > 0) {
        return answer.selected_options.map((opt) => opt.text).join(', ');
    }
    return 'N/A';
};

// Table columns definition
const columns: ColumnDef<StudentFormSurvey>[] = [
    {
        accessorKey: 'student.student_id',
        header: 'Student ID',
        cell: ({ row }) => row.original.student.student_id,
    },
    {
        accessorKey: 'student.full_name',
        header: 'Student Name',
        cell: ({ row }) => row.original.student.full_name,
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const badge = getStatusBadge(row.original.status);
            return badge;
        },
    },
    {
        accessorKey: 'completed_at',
        header: 'Completed At',
        cell: ({ row }) => formatDate(row.original.completed_at),
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
        only: ['studentSurveys'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    searchForm.per_page = pageSize;
    applyFilters();
};
</script>

<template>
    <Head :title="`Survey: ${survey.form.title}`" />

    <div class="flex items-center justify-between">
        <div>
            <Button variant="ghost" size="sm" as-child class="mb-4">
                <Link :href="route('surveys.index')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Surveys
                </Link>
            </Button>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Survey Details</h2>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ survey.course_offering.course_code }} - {{ survey.course_offering.course_title }}
            </p>
        </div>
    </div>

    <!-- Survey Information -->
    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Survey Information</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <Label class="text-sm font-medium text-muted-foreground">Course</Label>
                    <p class="text-lg font-semibold">
                        {{ survey.course_offering.course_code }} - {{ survey.course_offering.course_title }}
                    </p>
                </div>
                <div>
                    <Label class="text-sm font-medium text-muted-foreground">Semester</Label>
                    <p class="text-lg">
                        {{ survey.course_offering.semester?.code || 'N/A' }}
                    </p>
                </div>
                <div>
                    <Label class="text-sm font-medium text-muted-foreground">Survey Form</Label>
                    <p class="text-lg font-semibold">{{ survey.form.title }}</p>
                    <p v-if="survey.form.description" class="text-sm text-muted-foreground mt-1">
                        {{ survey.form.description }}
                    </p>
                </div>
                <div>
                    <Label class="text-sm font-medium text-muted-foreground">Created At</Label>
                    <p class="text-lg">{{ formatDate(survey.created_at) }}</p>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Statistics Cards -->
    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Total Students</p>
                        <p class="text-2xl font-bold">{{ statistics.total_students }}</p>
                    </div>
                    <Users class="h-8 w-8 text-muted-foreground" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Completed</p>
                        <p class="text-2xl font-bold text-green-600">{{ statistics.completed }}</p>
                    </div>
                    <CheckCircle class="h-8 w-8 text-green-600" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Pending</p>
                        <p class="text-2xl font-bold text-orange-600">{{ statistics.pending }}</p>
                    </div>
                    <Clock class="h-8 w-8 text-orange-600" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Completion Rate</p>
                        <p
                            :class="[
                                'text-2xl font-bold',
                                statistics.completion_rate >= 80
                                    ? 'text-green-600'
                                    : statistics.completion_rate >= 50
                                      ? 'text-yellow-600'
                                      : 'text-red-600',
                            ]"
                        >
                            {{ statistics.completion_rate }}%
                        </p>
                    </div>
                    <FileText class="h-8 w-8 text-muted-foreground" />
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Students List -->
    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Student Surveys</CardTitle>
            <CardDescription>View and manage student survey completion status</CardDescription>
        </CardHeader>
        <CardContent>
            <!-- Filters -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="search">Search</Label>
                    <DebouncedInput
                        id="search"
                        v-model="searchForm.search"
                        placeholder="Search by student ID or name..."
                        @update:model-value="debouncedSearch"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <Select v-model="searchForm.status" @update:model-value="applyFilters">
                        <SelectTrigger id="status">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
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

            <!-- Data Table -->
            <DataTable :columns="columns" :data="studentSurveys.data">
                <template #cell-status="{ row }">
                    <Badge :variant="getStatusBadge(row.original.status).variant">
                        <component
                            :is="getStatusBadge(row.original.status).icon"
                            class="mr-1 h-3 w-3"
                        />
                        {{ getStatusBadge(row.original.status).label }}
                    </Badge>
                </template>
                <template #cell-actions="{ row }">
                    <Button
                        v-if="row.original.status === 'completed' && row.original.response_id"
                        variant="ghost"
                        size="sm"
                        @click="viewResponse(row.original)"
                    >
                        <Eye class="mr-2 h-4 w-4" />
                        View Response
                    </Button>
                    <span v-else class="text-muted-foreground text-sm">No response yet</span>
                </template>
            </DataTable>

            <div class="mt-4">
                <DataPagination
                    :pagination-data="studentSurveys"
                    item-name="student surveys"
                    @navigate="handlePaginationNavigate"
                    @page-size-change="handlePageSizeChange"
                />
            </div>
        </CardContent>
    </Card>

    <!-- Response Detail Dialog -->
    <Dialog v-model:open="showResponseDialog">
        <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Survey Response Details</DialogTitle>
                <DialogDescription v-if="selectedStudentSurvey">
                    {{ selectedStudentSurvey.student.full_name }} ({{ selectedStudentSurvey.student.student_id }})
                    <span class="ml-2 text-xs text-muted-foreground">
                        Submitted: {{ formatDate(selectedStudentSurvey.response?.submitted_at) }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedStudentSurvey?.response && survey.form_version">
                <FormResponseView
                    :title="survey.form.title"
                    :description="survey.form.description || undefined"
                    :type="survey.form.type"
                    :sections="formSections"
                    :questions="formQuestions"
                    :answers="mappedAnswers"
                />
            </div>
            <div v-else class="text-muted-foreground py-8 text-center">
                <p>No response data available</p>
            </div>
        </DialogContent>
    </Dialog>
</template>

