<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginationLink } from '@/types';
import type { QueryTicket } from '@/types/forms';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { computed, reactive } from 'vue';
import { route } from 'ziggy-js';

interface QuestionOption {
    id: number;
    text: string;
    code: string;
}

interface PaginationSummary {
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    per_page: number;
    links: PaginationLink[];
}

interface Filters {
    status?: string | null;
    question_id?: string | number | null;
    per_page?: number;
}

interface Props {
    tickets: QueryTicket[];
    pagination: PaginationSummary;
    filters: Filters;
    statusOptions: string[];
    questions: QuestionOption[];
}

const props = defineProps<Props>();

const filterState = reactive({
    status: props.filters.status ?? '',
    question: props.filters.question_id ? String(props.filters.question_id) : '',
    per_page: props.filters.per_page ?? props.pagination.per_page ?? 25,
});

const columns: ColumnDef<QueryTicket>[] = [
    {
        id: 'submitted_at',
        header: 'Submitted',
        accessorFn: (row) => row.response?.submitted_at ?? '',
    },
    {
        id: 'topic',
        header: 'Topic',
        accessorFn: (row) => row.topic?.title ?? row.custom_topic_text ?? 'Other',
    },
    {
        id: 'content',
        header: 'Question',
        accessorFn: (row) => row.response?.form?.title ?? '—',
    },
    {
        id: 'campus',
        header: 'Campus',
        accessorFn: (row) => row.response?.campus?.name ?? '',
    },
    {
        id: 'student',
        header: 'Student',
        accessorFn: (row) => row.response?.student?.full_name ?? 'Anonymous',
    },
    {
        id: 'status',
        header: 'Status',
        accessorFn: (row) => row.status,
    },
    {
        id: 'created_at',
        header: 'Created',
        accessorFn: (row) => row.created_at ?? '',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

const statusLabelMap = computed<Record<string, string>>(() => ({
    open: 'Open',
    pending: 'Pending',
    answered: 'Answered',
    closed: 'Closed',
}));

const statusVariantMap = computed<Record<string, 'default' | 'outline' | 'secondary' | 'destructive'>>(() => ({
    open: 'secondary',
    pending: 'outline',
    answered: 'default',
    closed: 'destructive',
}));

const applyFilters = () => {
    const query: Record<string, string | number> = {};

    if (filterState.status && filterState.status !== 'all') {
        query.status = filterState.status;
    }

    if (filterState.question && filterState.question !== 'all') {
        query.question_id = filterState.question;
    }

    query.per_page = filterState.per_page;

    router.get(route('forms.queries.index'), query, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterState.status = '';
    filterState.question = '';
    applyFilters();
};

const viewTicket = (ticket: QueryTicket) => {
    router.visit(route('forms.queries.show', ticket.id));
};

const handlePaginationNavigate = (url: string) => {
    router.get(url, {}, { preserveState: true, replace: true });
};

const handlePageSizeChange = (pageSize: number) => {
    filterState.per_page = pageSize;
    applyFilters();
};

const formatDate = (value?: string | null) => {
    if (!value) return '—';
    try {
        return format(new Date(value), 'dd MMM yyyy HH:mm');
    } catch {
        return value;
    }
};

const tickets = computed(() => props.tickets ?? []);
</script>

<template>
    <Head title="Student Queries" />

    <div class="space-y-6">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Student Queries</h1>
                <p class="text-muted-foreground">Review and respond to student-submitted queries across campuses.</p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="clearFilters">Reset Filters</Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="status-filter">Status</Label>
                        <Select id="status-filter" v-model="filterState.status" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem v-for="option in props.statusOptions" :key="option" :value="option">
                                    {{ statusLabelMap[option] || option }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="question-filter">Question</Label>
                        <Select id="question-filter" v-model="filterState.question" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All questions" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All questions</SelectItem>
                                <SelectItem v-for="question in props.questions" :key="question.id" :value="String(question.id)">
                                    {{ question.text }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Query Tickets</CardTitle>
            </CardHeader>
            <CardContent>
                <DataTable :data="tickets" :columns="columns" :empty-message="'No query tickets found.'">
                    <template #cell-submitted_at="{ row }">
                        <span class="text-sm">{{ formatDate(row.original.response?.submitted_at) }}</span>
                    </template>

                    <template #cell-topic="{ row }">
                        <div class="flex flex-col">
                            <span class="font-medium">{{ row.original.topic?.title || row.original.custom_topic_text || 'Other' }}</span>
                            <span class="text-muted-foreground text-xs">Ticket #{{ row.original.id }}</span>
                        </div>
                    </template>

                    <template #cell-content="{ row }">
                        <div class="text-muted-foreground max-w-96 truncate text-sm">{{ row.original.response?.form?.title || '—' }}</div>
                    </template>

                    <template #cell-campus="{ row }">
                        <span class="text-sm">{{ row.original.response?.campus?.name || '—' }}</span>
                    </template>

                    <template #cell-student="{ row }">
                        <span class="text-sm">{{ row.original.response?.student?.full_name || 'Anonymous' }}</span>
                        <span v-if="row.original.response?.student?.student_id" class="text-muted-foreground block text-xs">
                            {{ row.original.response.student.student_id }}
                        </span>
                    </template>

                    <template #cell-status="{ row }">
                        <Badge :variant="statusVariantMap[row.original.status] || 'secondary'">
                            {{ statusLabelMap[row.original.status] || row.original.status }}
                        </Badge>
                    </template>

                    <template #cell-created_at="{ row }">
                        <span class="text-sm">{{ formatDate(row.original.created_at) }}</span>
                    </template>

                    <template #cell-actions="{ row }">
                        <Button variant="outline" size="sm" @click="viewTicket(row.original)">View</Button>
                    </template>
                </DataTable>

                <DataPagination class="mt-4" :pagination-data="props.pagination" item-name="tickets" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
