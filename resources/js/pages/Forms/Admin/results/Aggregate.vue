<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import FormResponseView from '@/components/forms/FormResponseView.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart } from '@/components/ui/chart';
import { DoughnutChart } from '@/components/ui/chart';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import type { FormBuilderQuestion, FormBuilderSection, FormResponse, FormTarget } from '@/types/forms';
import type { Student } from '@/types/models';
import { Deferred, Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { AlertCircle, ArrowLeft, CheckCircle2, ChevronLeft, ChevronRight, Info, Star, User, Users, X } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';

interface AggregateHeader {
    course_info?: {
        code: string;
        name: string;
        section: string;
        instructor: string;
    };
    semester: string;
    form_title: string;
    form_version: string;
    responses_done: number;
    responses_total: number;
    responses_percent: number;
}

interface AggregateOverall {
    average: number;
    positive_percent: number;
    neutral_percent: number;
    negative_percent: number;
}

interface AggregateSection {
    id: number;
    title: string;
    stats?: {
        average: number;
        positive_percent: number;
        negative_percent: number;
        distribution: number[];
    };
    questions: {
        id: number;
        text: string;
        type: string;
        order: number;
        total_responses: number;
        data: any;
    }[];
}

// --- Response tab types (mirrored from Raw.vue) ---
interface StudentAssignment {
    id: number;
    student_id: number;
    form_target_id: number;
    status: 'not_started' | 'completed';
    response_id: number | null;
    completed_at: string | null;
    student: Student;
    response?: FormResponse & { answers: any[] };
}

interface RawFilters {
    search: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

interface Navigation {
    prev_id: number | null;
    next_id: number | null;
    current_index: number | null;
    total: number;
    return_params: string;
}

const props = defineProps<{
    target: FormTarget;
    // Eager props — always available on initial render
    header: AggregateHeader;
    overall: AggregateOverall;
    navigation: Navigation;
    // Deferred props — undefined until Inertia resolves them
    sections?: AggregateSection[];
    responses?: PaginatedResponse<StudentAssignment>;
    responseFilters?: Partial<RawFilters>;
}>();

// --- Back URL (restore Index filter state from ?return param) ---
const backUrl = computed(() => {
    const ret = props.navigation.return_params;
    return ret ? `/forms/admin/results?${ret}` : '/forms/admin/results';
});

const navigateTo = (id: number | null) => {
    if (!id) return;
    const ret = props.navigation.return_params;
    router.visit(`/forms/admin/results/${id}/aggregate${ret ? `?return=${encodeURIComponent(ret)}` : ''}`);
};

// --- Navigation & State ---
const activeTab = ref('summary');
const selectedQuestionId = ref<string>('');

// Flatten questions for easier navigation in "Question" tab
// sections is deferred — default to [] until loaded
const allQuestions = computed(() => {
    return (props.sections ?? []).flatMap((section) =>
        section.questions.map((q) => ({
            ...q,
            section_title: section.title,
        })),
    );
});

// Initialize selected question (runs reactively once sections are deferred-loaded)
const initSelectedQuestion = () => {
    if (!selectedQuestionId.value && allQuestions.value.length > 0) {
        selectedQuestionId.value = String(allQuestions.value[0].id);
    }
};
initSelectedQuestion();
// Re-init when deferred sections arrive
watch(allQuestions, initSelectedQuestion);

const currentQuestion = computed(() => {
    return allQuestions.value.find((q) => String(q.id) === selectedQuestionId.value);
});

const currentQuestionIndex = computed(() => {
    return allQuestions.value.findIndex((q) => String(q.id) === selectedQuestionId.value);
});

const navigateQuestion = (direction: 'prev' | 'next') => {
    const idx = currentQuestionIndex.value;
    if (idx === -1) return;

    const newIdx = direction === 'next' ? idx + 1 : idx - 1;
    if (newIdx >= 0 && newIdx < allQuestions.value.length) {
        selectedQuestionId.value = String(allQuestions.value[newIdx].id);
    }
};

// --- Helpers ---
const getRatingColor = (avg: number) => {
    if (avg >= 4) return 'text-green-600';
    if (avg >= 3) return 'text-amber-500';
    return 'text-red-500';
};

const getRatingBg = (avg: number) => {
    if (avg >= 4) return 'bg-green-50';
    if (avg >= 3) return 'bg-amber-50';
    return 'bg-red-50';
};

const getStatusIcon = (avg: number) => {
    if (avg >= 4) return CheckCircle2;
    if (avg >= 3) return Info;
    return AlertCircle;
};

// Colors for charts
const chartColors = ['bg-blue-500', 'bg-orange-500', 'bg-amber-500', 'bg-emerald-500', 'bg-indigo-500', 'bg-rose-500', 'bg-cyan-500', 'bg-violet-500'];
const chartColorsHex = ['#3b82f6', '#f97316', '#f59e0b', '#10b981', '#6366f1', '#f43f5e', '#06b6d4', '#8b5cf6'];

// Helper: Generate Bar Chart data for rating questions (percentage)
const getRatingBarChartData = (distribution: number[]) => {
    const total = distribution.reduce((sum, val) => sum + val, 0);
    const percentages = distribution.map((count) => (total > 0 ? Math.round((count / total) * 100) : 0));

    return {
        labels: ['1', '2', '3', '4', '5'],
        datasets: [
            {
                label: 'Percentage',
                data: percentages,
                backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e'],
                borderRadius: 4,
            },
        ],
    };
};

const ratingBarChartOptions = {
    indexAxis: 'y' as const,
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: (context: any) => `${context.parsed.x}%`,
            },
        },
    },
    scales: {
        x: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: (value: any) => `${value}%`,
            },
        },
        y: {
            grid: {
                display: false,
            },
        },
    },
};

// Helper: Generate Doughnut Chart data for single choice / likert
const getChoiceChartData = (options: { label: string; count: number; percentage: number }[]) => {
    return {
        labels: options.map((opt) => opt.label),
        datasets: [
            {
                data: options.map((opt) => opt.count),
                backgroundColor: chartColorsHex.slice(0, options.length),
                borderWidth: 2,
                borderColor: '#ffffff',
                percentages: options.map((opt) => opt.percentage),
            },
        ],
    };
};

const doughnutChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'right' as const,
        },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    const total = context.dataset.data.reduce((sum: number, val: number) => sum + val, 0);
                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                },
            },
        },
    },
};

// Plugin to display labels on doughnut chart slices
const doughnutLabelPlugin = {
    id: 'doughnutLabel',
    afterDatasetsDraw(chart: any) {
        const { ctx } = chart;

        chart.data.datasets.forEach((dataset: any, i: number) => {
            const meta = chart.getDatasetMeta(i);
            if (!meta.hidden) {
                meta.data.forEach((element: any, index: number) => {
                    const value = dataset.data[index];
                    let percentage;

                    if (dataset.percentages && dataset.percentages[index] !== undefined) {
                        percentage = dataset.percentages[index] + '%';
                    } else {
                        const total = dataset.data.reduce((acc: number, curr: number) => acc + curr, 0);
                        percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                    }

                    if (value > 0) {
                        const { x, y } = element.tooltipPosition();

                        ctx.save();
                        ctx.fillStyle = '#ffffff';
                        ctx.textAlign = 'center';

                        // Display Value and Percentage on two lines
                        ctx.font = 'bold 14px sans-serif';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(value.toString(), x, y - 2);

                        ctx.font = 'bold 11px sans-serif';
                        ctx.textBaseline = 'top';
                        ctx.fillText(percentage, x, y + 2);

                        ctx.restore();
                    }
                });
            }
        });
    },
};

const doughnutPlugins = [doughnutLabelPlugin];

// ============================================================
// RESPONSES TAB — embedded from Raw.vue
// ============================================================

const { filters: respFilters, hasActiveFilters: respHasFilters, clearFilters: respClearFilters, handleSearch: respHandleSearch, handleSelectFilter: respHandleSelect, handleSortChange: respHandleSortChange, handlePaginationNavigate: respHandlePaginationNavigate, handlePageSizeChange: respHandlePageSizeChange, currentSort: respCurrentSort, currentDirection: respCurrentDirection } = useInertiaFilters<RawFilters>({
    baseUrl: `/forms/admin/results/${props.target.id}/aggregate`,
    initialFilters: {
        search: props.responseFilters?.search || '',
        status: props.responseFilters?.status || 'all',
        sort: props.responseFilters?.sort || null,
        direction: (props.responseFilters?.direction as 'asc' | 'desc') || null,
        per_page: props.responseFilters?.per_page || 15,
    },
    defaultValues: {
        search: '',
        status: 'all',
        sort: null,
        direction: 'asc',
        per_page: 15,
    },
    // Prefix resp_* to avoid collision with list filters; inject ?return= so it survives filter changes
    transform: (f) => {
        const out: Record<string, any> = {};
        if (f.search) out['resp_search'] = f.search;
        if (f.status && f.status !== 'all') out['resp_status'] = f.status;
        if (f.sort) out['resp_sort'] = f.sort;
        if (f.direction && f.direction !== 'asc') out['resp_direction'] = f.direction;
        if (f.per_page && f.per_page !== 15) out['resp_per_page'] = f.per_page;
        if (props.navigation.return_params) out['return'] = props.navigation.return_params;
        return out;
    },
    only: ['responses', 'responseFilters'],
    debounce: 400,
});

// Override pagination navigate to preserve ?return= param
const respHandlePaginationNavigateWithReturn = (url: string) => {
    try {
        const urlObj = url.startsWith('http') ? new URL(url) : new URL(url, window.location.origin);
        const page = urlObj.searchParams.get('page');
        if (page) (respFilters as any).page = parseInt(page);
    } catch {
        // inject return param into the pagination URL
        const separator = url.includes('?') ? '&' : '?';
        const withReturn = props.navigation.return_params
            ? `${url}${separator}return=${encodeURIComponent(props.navigation.return_params)}`
            : url;
        router.visit(withReturn, { preserveState: true, preserveScroll: true, only: ['responses', 'responseFilters'] });
    }
};

const showResponseDialog = ref(false);
const selectedAssignment = ref<StudentAssignment | null>(null);

const openResponseDetail = (assignment: StudentAssignment) => {
    if (!assignment.response_id) return;
    selectedAssignment.value = assignment;
    showResponseDialog.value = true;
};

// Map form version sections for FormResponseView
const formSections = computed<FormBuilderSection[]>(() => {
    if (!props.target.form_version?.sections) return [];
    return props.target.form_version.sections.map((section: any) => ({
        id: section.id,
        title: section.title,
        description: section.description,
        order_index: section.order_index,
        questions: (section.questions || []).map((q: any) => ({
            id: q.id,
            code: q.code,
            text: q.text,
            type: q.type,
            is_required: q.is_required,
            help_text: q.help_text,
            order_index: q.order_index,
            options: (q.options || []).map((opt: any) => ({
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
    const sectionQuestionIds = new Set<number>();
    props.target.form_version.sections?.forEach((section: any) => {
        section.questions?.forEach((q: any) => sectionQuestionIds.add(q.id));
    });
    return props.target.form_version.questions
        .filter((q: any) => !sectionQuestionIds.has(q.id))
        .map((q: any) => ({
            id: q.id,
            code: q.code,
            text: q.text,
            type: q.type,
            is_required: q.is_required,
            help_text: q.help_text,
            order_index: q.order_index,
            options: (q.options || []).map((opt: any) => ({
                id: opt.id,
                value: opt.value,
                label: opt.label,
                order_index: opt.order_index,
                allows_free_text: opt.allows_free_text,
            })),
        }));
});

const mappedAnswers = computed(() => {
    if (!selectedAssignment.value?.response?.answers) return [];
    return selectedAssignment.value.response.answers.map((ans: any) => ({
        question_id: ans.question_id,
        question_code: ans.question?.code || '',
        question_text: ans.question?.text || '',
        question_type: ans.question?.type || 'short_text',
        answer_text: ans.answer_text ?? null,
        answer_number: ans.answer_number ?? null,
        answer_date: ans.answer_date ?? null,
        selected_options: ans.selected_options
            ? ans.selected_options.map((opt: any) => ({ id: opt.id, text: opt.label, value: opt.value }))
            : [],
    }));
});

const getRespStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'outline' | 'destructive' | 'warning' => {
    switch (status) {
        case 'submitted': return 'default';
        case 'approved': return 'secondary';
        case 'rejected': return 'destructive';
        case 'not_started': return 'warning';
        default: return 'outline';
    }
};

const getRespStatusLabel = (assignment: StudentAssignment) => {
    if (assignment.status === 'not_started') return 'Pending';
    return assignment.response?.status || assignment.status;
};

const responseColumns: ColumnDef<StudentAssignment>[] = [
    {
        header: 'Student',
        id: 'student',
        accessorKey: 'student.full_name',
        enableSorting: false,
        cell: ({ row }) => {
            const a = row.original;
            if (a.response?.anonymized) {
                return h('div', { class: 'flex items-center gap-2 text-muted-foreground italic' }, [
                    h(User, { class: 'w-4 h-4' }),
                    'Anonymous',
                ]);
            }
            return h('div', { class: 'flex flex-col' }, [
                h('div', { class: 'font-medium' }, a.student?.full_name || 'Unknown'),
                h('div', { class: 'text-xs text-muted-foreground' }, a.student?.student_id || '---'),
            ]);
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
            const a = row.original;
            const status = a.status === 'not_started' ? 'not_started' : a.response?.status || a.status;
            return h(Badge, { variant: getRespStatusBadgeVariant(status) }, () => getRespStatusLabel(a));
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) =>
            h(Button, { variant: 'outline', size: 'sm', disabled: !row.original.response_id, onClick: () => openResponseDetail(row.original) }, () => 'View Detail'),
    },
];
</script>

<template>
    <Head :title="`Results: ${header.course_info?.code || header.form_title}`" />

    <!-- Prev / Next navigation bar -->
    <div v-if="navigation.total > 1" class="bg-muted/50 sticky top-16 z-20 flex items-center justify-between border-b px-4 py-1.5 text-sm">
        <Button variant="ghost" size="sm" :disabled="!navigation.prev_id" @click="navigateTo(navigation.prev_id)">
            <ChevronLeft class="mr-1 h-4 w-4" />
            Prev
        </Button>
        <span class="text-muted-foreground tabular-nums">
            {{ navigation.current_index ?? '?' }} / {{ navigation.total }}
        </span>
        <Button variant="ghost" size="sm" :disabled="!navigation.next_id" @click="navigateTo(navigation.next_id)">
            Next
            <ChevronRight class="ml-1 h-4 w-4" />
        </Button>
    </div>

    <!-- Header -->
    <div class="bg-card sticky top-16 z-10 border-b" :class="{ 'top-28': navigation.total > 1 }">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div class="flex items-start gap-4">
                <Button variant="ghost" size="icon" @click="router.visit(backUrl)" class="-ml-2">
                    <ArrowLeft class="h-5 w-5" />
                </Button>
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <Badge variant="secondary" class="text-xs">{{ header.form_version }}</Badge>
                        <span class="text-muted-foreground text-xs font-bold">{{ header.semester }}</span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        <span v-if="header.course_info" class="text-primary mr-2">{{ header.course_info.code }}</span>
                        <span>{{ header.course_info?.name || header.form_title }}</span>
                    </h1>
                </div>
            </div>

            <div class="text-muted-foreground flex items-center gap-6 text-sm">
                <div class="hidden text-right md:block" v-if="header.course_info">
                    <div class="text-foreground font-medium">{{ header.course_info.instructor }}</div>
                    <div class="text-xs">Section {{ header.course_info.section }}</div>
                </div>
                <div class="bg-muted/50 flex items-center gap-2 rounded-lg px-3 py-1.5">
                    <Users class="h-4 w-4" />
                    <span class="text-foreground font-bold">{{ header.responses_done }}</span>
                    <span>responses</span>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="container mx-auto mt-2 px-4">
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="mx-auto grid w-full max-w-md grid-cols-3">
                    <TabsTrigger value="summary">Summary</TabsTrigger>
                    <TabsTrigger value="question">Question</TabsTrigger>
                    <TabsTrigger value="responses">Responses</TabsTrigger>
                </TabsList>
            </Tabs>
        </div>
    </div>

    <div class="px-4 py-8">
        <!-- SUMMARY + QUESTION TABS: deferred (heavy chart data) -->
        <Deferred data="sections">
            <template #fallback>
                <div v-if="activeTab === 'summary' || activeTab === 'question'" class="space-y-4 animate-pulse">
                    <div class="bg-muted/40 h-32 rounded-xl" />
                    <div class="bg-muted/40 h-48 rounded-xl" />
                    <div class="bg-muted/40 h-48 rounded-xl" />
                </div>
            </template>

            <template #default="{ reloading }">
                <div :class="{ 'opacity-60': reloading }">

        <!-- SUMMARY TAB -->
        <div v-if="activeTab === 'summary'" class="space-y-8">
            <!-- Overall Rating KPI (Only if valid) -->
            <div v-if="overall.average > 0" class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card class="bg-primary text-primary-foreground border-none shadow-lg">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium tracking-wider uppercase opacity-90">Overall Rating</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-2">
                            <span class="text-6xl font-bold">{{ overall.average.toFixed(1) }}</span>
                            <span class="opacity-80">/ 5.0</span>
                        </div>
                    </CardContent>
                </Card>

                <Card class="md:col-span-2">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-muted-foreground text-sm font-medium tracking-wider uppercase">Sentiment Distribution</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-3 gap-4 pt-4">
                        <div class="space-y-2">
                            <div class="text-xs font-bold text-green-600 uppercase">Positive</div>
                            <div class="text-2xl font-bold">{{ overall.positive_percent }}%</div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-green-100">
                                <div class="h-full bg-green-600" :style="{ width: overall.positive_percent + '%' }"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="text-xs font-bold text-amber-500 uppercase">Neutral</div>
                            <div class="text-2xl font-bold">{{ overall.neutral_percent }}%</div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-amber-100">
                                <div class="h-full bg-amber-500" :style="{ width: overall.neutral_percent + '%' }"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="text-xs font-bold text-red-500 uppercase">Negative</div>
                            <div class="text-2xl font-bold">{{ overall.negative_percent }}%</div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-red-100">
                                <div class="h-full bg-red-500" :style="{ width: overall.negative_percent + '%' }"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Per-Section Rating Overview (only sections that have rating questions) -->
            <div v-if="(sections ?? []).some(s => s.stats)" class="space-y-3">
                <div class="flex items-center gap-4">
                    <h2 class="text-foreground text-sm font-bold tracking-wider uppercase">Section Ratings</h2>
                    <div class="bg-border h-px flex-1"></div>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Card v-for="section in (sections ?? []).filter(s => s.stats)" :key="section.id">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-semibold">{{ section.title }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <!-- Average score badge -->
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-3xl font-black" :class="getRatingColor(section.stats!.average)">
                                    {{ section.stats!.average.toFixed(1) }}
                                </span>
                                <span class="text-muted-foreground text-sm">/ 5.0</span>
                            </div>
                            <!-- Sentiment bars -->
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-16 text-xs font-medium text-green-600">Positive</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-green-100">
                                        <div class="h-full bg-green-500 transition-all" :style="{ width: section.stats!.positive_percent + '%' }"></div>
                                    </div>
                                    <span class="w-10 text-right text-xs tabular-nums text-green-600">{{ section.stats!.positive_percent }}%</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground w-16 text-xs font-medium">Neutral</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-amber-100">
                                        <div class="h-full bg-amber-400 transition-all"
                                            :style="{ width: (100 - section.stats!.positive_percent - section.stats!.negative_percent) + '%' }">
                                        </div>
                                    </div>
                                    <span class="text-muted-foreground w-10 text-right text-xs tabular-nums">
                                        {{ 100 - section.stats!.positive_percent - section.stats!.negative_percent }}%
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-16 text-xs font-medium text-red-500">Negative</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-red-100">
                                        <div class="h-full bg-red-500 transition-all" :style="{ width: section.stats!.negative_percent + '%' }"></div>
                                    </div>
                                    <span class="w-10 text-right text-xs tabular-nums text-red-500">{{ section.stats!.negative_percent }}%</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Sections Loop -->
            <div v-for="section in (sections ?? [])" :key="section.id" class="space-y-4">
                <div class="flex items-center gap-4 pt-4">
                    <h2 class="text-foreground text-lg font-bold tracking-wider uppercase">{{ section.title }}</h2>
                    <div class="bg-border h-px flex-1"></div>
                </div>

                <!-- Questions Loop -->
                <Card v-for="q in section.questions" :key="q.id" class="overflow-hidden">
                    <CardHeader class="bg-muted/30 pb-4">
                        <div class="flex items-start justify-between gap-4">
                            <CardTitle class="text-base leading-relaxed font-medium">{{ q.text }}</CardTitle>
                            <Badge variant="outline" class="shrink-0">{{ q.total_responses }} responses</Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-6">
                        <!-- RATING Visualization -->
                        <div v-if="q.type === 'rating'" class="space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="text-4xl font-bold" :class="getRatingColor(q.data.average)">{{ q.data.average.toFixed(1) }}</div>
                                    <div class="flex flex-col">
                                        <div class="flex">
                                            <Star v-for="i in 5" :key="i" class="h-4 w-4" :class="i <= Math.round(q.data.average) ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/30'" />
                                        </div>
                                        <span class="text-muted-foreground mt-1 text-xs">Average rating</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bar Chart with Percentages -->
                            <BarChart :data="getRatingBarChartData(q.data.distribution)" :options="ratingBarChartOptions" height="180px" />
                        </div>

                        <!-- SINGLE CHOICE / LIKERT Visualization (Pie Chart) -->
                        <div v-else-if="['single_choice', 'likert'].includes(q.type)" class="flex items-center justify-center">
                            <DoughnutChart
                                v-if="q.data.options?.length > 0"
                                :data="getChoiceChartData(q.data.options)"
                                :options="doughnutChartOptions"
                                :plugins="doughnutPlugins"
                                height="250px"
                                width="100%"
                            />
                            <div v-else class="text-muted-foreground py-4 text-center text-sm">No options data available</div>
                        </div>

                        <!-- MULTI CHOICE Visualization (Keep bar chart for multi-select) -->
                        <div v-else-if="q.type === 'multi_choice'" class="space-y-4">
                            <div v-for="(opt, idx) in q.data.options" :key="idx" class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-foreground font-medium">{{ opt.label }}</span>
                                    <span class="text-muted-foreground font-mono text-xs">{{ opt.count }} ({{ opt.percentage }}%)</span>
                                </div>
                                <div class="bg-muted h-2.5 w-full overflow-hidden rounded-full">
                                    <div class="h-full rounded-full transition-all" :class="chartColors[idx % chartColors.length]" :style="{ width: `${opt.percentage}%` }"></div>
                                </div>
                            </div>
                            <div v-if="q.data.options.length === 0" class="text-muted-foreground py-4 text-center text-sm">No options data available</div>
                        </div>

                        <!-- TEXT Visualization -->
                        <div v-else-if="['short_text', 'long_text'].includes(q.type)" class="space-y-3">
                            <div class="max-h-60 space-y-2 overflow-y-auto pr-2">
                                <div v-for="(resp, i) in q.data.responses" :key="i" class="bg-muted/40 rounded-lg p-3 text-sm">
                                    {{ resp }}
                                </div>
                                <div v-if="!q.data.responses || q.data.responses.length === 0" class="text-muted-foreground py-4 text-center italic">No text responses provided.</div>
                            </div>
                            <div v-if="q.data.responses?.length > 0" class="text-right">
                                <Button
                                    variant="link"
                                    size="sm"
                                    class="h-auto p-0"
                                    @click="
                                        activeTab = 'question';
                                        selectedQuestionId = String(q.id);
                                    "
                                >
                                    View all
                                </Button>
                            </div>
                        </div>

                        <!-- Fallback -->
                        <div v-else class="text-muted-foreground py-8 text-center">Visualization not implemented for {{ q.type }}</div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- QUESTION TAB -->
        <div v-else-if="activeTab === 'question'" class="space-y-6">
            <!-- Navigation Control -->
            <Card class="sticky top-20 z-10 shadow-md">
                <CardContent class="flex flex-col items-center justify-between gap-4 p-4 md:flex-row">
                    <div class="flex w-full items-center gap-2 md:w-auto">
                        <Button variant="outline" size="icon" :disabled="currentQuestionIndex <= 0" @click="navigateQuestion('prev')">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>

                        <Select v-model="selectedQuestionId">
                            <SelectTrigger class="w-full md:w-[400px]">
                                <SelectValue placeholder="Select a question" />
                            </SelectTrigger>
                            <SelectContent class="max-h-[300px]">
                                <SelectGroup v-for="section in (sections ?? [])" :key="section.id">
                                    <div class="text-muted-foreground bg-muted/50 px-2 py-1.5 text-xs font-semibold uppercase">
                                        {{ section.title }}
                                    </div>
                                    <SelectItem v-for="q in section.questions" :key="q.id" :value="String(q.id)">
                                        <span class="truncate">{{ q.text }}</span>
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Button variant="outline" size="icon" :disabled="currentQuestionIndex >= allQuestions.length - 1" @click="navigateQuestion('next')">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="text-muted-foreground text-sm whitespace-nowrap">{{ currentQuestionIndex + 1 }} of {{ allQuestions.length }}</div>
                </CardContent>
            </Card>

            <!-- Detailed Question View -->
            <div v-if="currentQuestion" class="animate-in fade-in duration-300">
                <Card class="border-t-primary border-t-4">
                    <CardHeader class="bg-muted/10">
                        <CardTitle class="text-xl">{{ currentQuestion.text }}</CardTitle>
                        <CardDescription>{{ currentQuestion.total_responses }} responses • {{ currentQuestion.type.replace('_', ' ') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-8">
                        <!-- Detailed Visualization Reuse -->

                        <!-- RATING -->
                        <div v-if="currentQuestion.type === 'rating'" class="mx-auto max-w-2xl space-y-8">
                            <div class="text-center">
                                <div class="text-primary text-6xl font-black">{{ currentQuestion.data.average.toFixed(2) }}</div>
                                <div class="text-muted-foreground mt-2">Average Rating</div>
                            </div>
                            <!-- Bar Chart with Percentages -->
                            <BarChart :data="getRatingBarChartData(currentQuestion.data.distribution)" :options="ratingBarChartOptions" height="220px" />
                        </div>

                        <!-- SINGLE CHOICE / LIKERT (Pie Chart) -->
                        <div v-else-if="['single_choice', 'likert'].includes(currentQuestion.type)" class="flex items-center justify-center">
                            <DoughnutChart
                                v-if="currentQuestion.data.options?.length > 0"
                                :data="getChoiceChartData(currentQuestion.data.options)"
                                :options="doughnutChartOptions"
                                :plugins="doughnutPlugins"
                                height="320px"
                                width="100%"
                            />
                            <div v-else class="text-muted-foreground py-12 text-center">No options data available</div>
                        </div>

                        <!-- MULTI CHOICE (Keep bar chart for multi-select) -->
                        <div v-else-if="currentQuestion.type === 'multi_choice'" class="space-y-6">
                            <div v-for="(opt, idx) in currentQuestion.data.options" :key="idx" class="flex items-center gap-3">
                                <div class="h-4 w-4 shrink-0 rounded-full" :class="chartColors[idx % chartColors.length]"></div>
                                <div class="flex-1">
                                    <div class="mb-1 flex justify-between">
                                        <span class="font-medium">{{ opt.label }}</span>
                                        <span class="text-muted-foreground text-sm">{{ opt.percentage }}% ({{ opt.count }})</span>
                                    </div>
                                    <div class="bg-muted h-2 overflow-hidden rounded-full">
                                        <div class="h-full" :class="chartColors[idx % chartColors.length]" :style="{ width: `${opt.percentage}%` }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TEXT -->
                        <div v-else-if="['short_text', 'long_text'].includes(currentQuestion.type)" class="space-y-4">
                            <div v-for="(resp, i) in currentQuestion.data.responses" :key="i" class="bg-muted/30 rounded-lg border p-4">
                                <p class="text-sm leading-relaxed">{{ resp }}</p>
                            </div>
                            <div v-if="!currentQuestion.data.responses?.length" class="text-muted-foreground py-12 text-center">No responses yet.</div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

                </div>
            </template>
        </Deferred>

        <!-- RESPONSES TAB (deferred) -->
        <div v-if="activeTab === 'responses'">
            <Deferred data="responses">
                <template #fallback>
                    <div class="space-y-3">
                        <div class="bg-muted/40 h-10 animate-pulse rounded-lg" />
                        <div class="bg-muted/40 h-48 animate-pulse rounded-lg" />
                    </div>
                </template>

                <template #default="{ reloading }">
                    <div class="space-y-4" :class="{ 'opacity-60 pointer-events-none': reloading }">
                        <!-- Filters -->
                        <div class="space-y-4 rounded-lg border bg-card p-4">
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="min-w-[250px] flex-1">
                                    <DebouncedInput
                                        placeholder="Search students..."
                                        :model-value="respFilters.search"
                                        @update:model-value="respHandleSearch"
                                    />
                                </div>
                                <Button v-if="respHasFilters" variant="ghost" size="sm" @click="respClearFilters">
                                    <X class="mr-2 h-4 w-4" />
                                    Clear Filters
                                </Button>
                            </div>

                            <div class="flex flex-wrap items-center gap-4">
                                <div class="flex flex-col gap-1">
                                    <Label class="text-muted-foreground text-xs font-semibold">Status</Label>
                                    <Select :model-value="respFilters.status" @update:model-value="(v) => respHandleSelect('status', v, 'all')">
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

                        <!-- Table -->
                        <DataTable
                            :data="responses?.data ?? []"
                            :columns="responseColumns"
                            enable-server-sorting
                            :initial-sort="respCurrentSort"
                            :initial-direction="respCurrentDirection"
                            @sort-change="respHandleSortChange"
                        />

                        <!-- Pagination -->
                        <DataPagination
                            v-if="responses"
                            :pagination-data="responses"
                            item-name="responses"
                            @navigate="respHandlePaginationNavigateWithReturn"
                            @page-size-change="respHandlePageSizeChange"
                        />
                    </div>
                </template>
            </Deferred>
        </div>
    </div>

    <!-- Response Detail Dialog -->
    <Dialog v-model:open="showResponseDialog">
        <DialogContent class="!max-w-5xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Survey Response Details</DialogTitle>
                <DialogDescription v-if="selectedAssignment">
                    <template v-if="selectedAssignment.response?.anonymized">Anonymous Response</template>
                    <template v-else>
                        {{ selectedAssignment.student?.full_name }} ({{ selectedAssignment.student?.student_id }})
                    </template>
                    <span class="text-muted-foreground ml-2 text-xs">
                        Completed: {{ selectedAssignment.completed_at ? format(new Date(selectedAssignment.completed_at), 'PPP p') : '---' }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedAssignment?.response && target.form_version">
                <FormResponseView
                    :title="target.form?.title || 'Survey Response'"
                    :description="target.form?.description"
                    :type="target.form?.type || 'survey'"
                    :sections="formSections"
                    :questions="formQuestions"
                    :answers="mappedAnswers"
                />
            </div>
            <div v-else class="text-muted-foreground bg-muted/20 rounded-lg border py-8 text-center">
                <p>No response data available</p>
            </div>
        </DialogContent>
    </Dialog>
</template>
