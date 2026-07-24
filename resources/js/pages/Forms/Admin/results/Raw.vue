<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import FormResponseView from '@/components/forms/FormResponseView.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import type { FormBuilderQuestion, FormBuilderSection, FormResponse, FormTarget } from '@/types/forms';
import type { Student } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { ArrowLeft, User, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

interface StudentAssignment {
    id: number;
    student_id: number;
    form_target_id: number;
    status: 'not_started' | 'completed';
    response_id: number | null;
    completed_at: string | null;
    student: Student;
    response?: FormResponse & {
        answers: any[];
    };
}

interface RawFilters {
    search: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    target: FormTarget;
    responses: PaginatedResponse<StudentAssignment>;
    filters?: Partial<RawFilters>;
}>();

const data = computed(() => props.responses.data);

const { filters, hasActiveFilters, clearAllFilters, handleSearch, setFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useDataTable<RawFilters>({
    baseUrl: route('forms.admin.results.raw', props.target.id),
    initialFilters: {
        search: props.filters?.search || '',
        status: props.filters?.status || 'all',
        sort: props.filters?.sort || null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        status: 'all',
        per_page: 15,
        direction: 'asc',
    },
    only: ['responses', 'filters'],
    debounce: 400,
    immediateFields: ['status'],
});

const showResponseDialog = ref(false);
const selectedAssignment = ref<StudentAssignment | null>(null);

const openDetail = (assignment: StudentAssignment) => {
    if (!assignment.response_id) return;
    selectedAssignment.value = assignment;
    showResponseDialog.value = true;
};

// Map form version to FormBuilder format for the view component
const formSections = computed<FormBuilderSection[]>(() => {
    if (!props.target.form_version?.sections) return [];

    return props.target.form_version.sections.map((section) => ({
        id: section.id,
        title: section.title,
        description: section.description,
        order_index: section.order_index,
        questions: (section.questions || []).map((q) => ({
            id: q.id,
            code: q.code,
            text: q.text,
            type: q.type,
            is_required: q.is_required,
            help_text: q.help_text,
            order_index: q.order_index,
            options: (q.options || []).map((opt) => ({
                id: opt.id,
                value: opt.value,
                label: opt.label,
                order_index: opt.order_index,
                allows_free_text: opt.allows_free_text,
            })),
        })),
    }));
});

const formQuestions = computed<FormBuilderQuestion[]>(() => {
    if (!props.target.form_version?.questions) return [];

    // Filter out questions that belong to sections to avoid duplication
    const sectionQuestionIds = new Set<number>();
    props.target.form_version.sections?.forEach((section) => {
        section.questions?.forEach((q) => {
            sectionQuestionIds.add(q.id);
        });
    });

    return props.target.form_version.questions
        .filter((q) => !sectionQuestionIds.has(q.id))
        .map((q) => ({
            id: q.id,
            code: q.code,
            text: q.text,
            type: q.type,
            is_required: q.is_required,
            help_text: q.help_text,
            order_index: q.order_index,
            options: (q.options || []).map((opt) => ({
                id: opt.id,
                value: opt.value,
                label: opt.label,
                order_index: opt.order_index,
                allows_free_text: opt.allows_free_text,
            })),
        }));
});

const mappedAnswers = computed(() => {
    if (!selectedAssignment.value || !selectedAssignment.value.response?.answers) return [];

    return selectedAssignment.value.response.answers.map((ans) => ({
        question_id: ans.question_id,
        question_code: ans.question?.code || '',
        question_text: ans.question?.text || '',
        question_type: ans.question?.type || 'short_text',
        answer_text: ans.answer_text ?? null,
        answer_number: ans.answer_number ?? null,
        answer_date: ans.answer_date ?? null,
        selected_options: ans.selected_options
            ? ans.selected_options.map((opt: any) => ({
                  id: opt.id,
                  text: opt.label,
                  value: opt.value,
              }))
            : [],
    }));
});

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'outline' | 'destructive' | 'warning' => {
    switch (status) {
        case 'submitted':
            return 'default';
        case 'approved':
            return 'secondary';
        case 'rejected':
            return 'destructive';
        case 'not_started':
            return 'warning';
        default:
            return 'outline';
    }
};

const getStatusLabel = (assignment: StudentAssignment) => {
    if (assignment.status === 'not_started') return 'Pending';
    return assignment.response?.status || assignment.status;
};

const columns: ColumnDef<StudentAssignment>[] = [
    {
        header: 'Student',
        id: 'student',
        accessorKey: 'student.full_name',
        enableSorting: false,
        cell: ({ row }) => {
            const assignment = row.original;
            if (assignment.response?.anonymized) {
                return h('div', { class: 'flex items-center gap-2 text-muted-foreground italic' }, [h(User, { class: 'w-4 h-4' }), 'Anonymous']);
            }
            return h('div', { class: 'flex flex-col' }, [h('div', { class: 'font-medium' }, assignment.student?.full_name || 'Unknown'), h('div', { class: 'text-xs text-muted-foreground' }, assignment.student?.student_id || '---')]);
        },
    },
    {
        header: 'Completed At',
        id: 'submitted_at',
        accessorKey: 'completed_at',
        enableSorting: true,
        cell: ({ row }) => (row.original.completed_at ? format(new Date(row.original.completed_at), 'PPP p') : '---'),
    },
    {
        header: 'Status',
        id: 'status',
        cell: ({ row }) => {
            const assignment = row.original;
            const status = assignment.status === 'not_started' ? 'not_started' : assignment.response?.status || assignment.status;
            return h(Badge, { variant: getStatusBadgeVariant(status) }, () => getStatusLabel(assignment));
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const assignment = row.original;
            return h(
                Button,
                {
                    variant: 'outline',
                    size: 'sm',
                    disabled: !assignment.response_id,
                    onClick: () => openDetail(assignment),
                },
                () => 'View Detail',
            );
        },
    },
];
</script>

<template>
    <Head :title="`Raw Responses: ${target.form?.title}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="router.visit('/forms/admin/results')">
                    <ArrowLeft class="h-5 w-5" />
                </Button>
                <div>
                    <h1 class="text-2xl font-bold">Raw Responses</h1>
                    <p class="text-muted-foreground">{{ target.form?.title }} ({{ target.semester?.name }})</p>
                </div>
            </div>
        </div>

        <div class="bg-card space-y-4 rounded-lg border p-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-[250px] flex-1">
                    <DebouncedInput placeholder="Search students..." :model-value="filters.search" @update:model-value="handleSearch" />
                </div>

                <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearAllFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear Filters
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <div class="flex flex-col gap-1">
                    <Label class="text-muted-foreground text-xs font-semibold">Status</Label>
                    <Select :model-value="filters.status" @update:model-value="(v) => setFilter('status', String(v))">
                        <SelectTrigger class="w-40">
                            <SelectValue placeholder="All Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="submitted">Submitted</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>

        <DataTable :data="data" :columns="columns" enable-server-sorting :initial-sort="currentSort" :initial-direction="currentDirection" @sort-change="handleSortChange" />

        <DataPagination :pagination-data="responses" item-name="responses" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

        <!-- Response Detail Dialog -->
        <Dialog v-model:open="showResponseDialog">
            <DialogContent class="max-h-[90vh] !max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Survey Response Details</DialogTitle>
                    <DialogDescription v-if="selectedAssignment">
                        <template v-if="selectedAssignment.response?.anonymized"> Anonymous Response </template>
                        <template v-else> {{ selectedAssignment.student?.full_name }} ({{ selectedAssignment.student?.student_id }}) </template>
                        <span class="text-muted-foreground ml-2 text-xs"> Completed: {{ selectedAssignment.completed_at ? format(new Date(selectedAssignment.completed_at), 'PPP p') : '---' }} </span>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedAssignment?.response && target.form_version">
                    <FormResponseView :title="target.form?.title || 'Survey Response'" :description="target.form?.description" :type="target.form?.type || 'survey'" :sections="formSections" :questions="formQuestions" :answers="mappedAnswers" />
                </div>
                <div v-else class="text-muted-foreground bg-muted/20 rounded-lg border py-8 text-center">
                    <p>No response data available</p>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
