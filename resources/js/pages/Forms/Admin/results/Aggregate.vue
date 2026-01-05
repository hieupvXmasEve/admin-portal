<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart } from '@/components/ui/chart';
import { DoughnutChart } from '@/components/ui/chart';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { FormTarget } from '@/types/forms';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, CheckCircle2, ChevronLeft, ChevronRight, Info, MessageSquare, Star, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AggregateData {
    header: {
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
    };
    overall: {
        average: number;
        positive_percent: number;
        neutral_percent: number;
        negative_percent: number;
    };
    sections: {
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
            data: any; // Dynamic based on type
        }[];
    }[];
}

const props = defineProps<{
    target: FormTarget;
    data: AggregateData;
}>();

// --- Navigation & State ---
const activeTab = ref('summary');
const selectedQuestionId = ref<string>('');

// Flatten questions for easier navigation in "Question" tab
const allQuestions = computed(() => {
    return props.data.sections.flatMap((section) =>
        section.questions.map((q) => ({
            ...q,
            section_title: section.title,
        })),
    );
});

// Initialize selected question
if (allQuestions.value.length > 0) {
    selectedQuestionId.value = String(allQuestions.value[0].id);
}

const currentQuestion = computed(() => {
    return allQuestions.value.find((q) => String(q.id) === selectedQuestionId.value);
});

const currentQuestionIndex = computed(() => {
    return allQuestions.value.findIndex((q) => String(q.id) === selectedQuestionId.value);
});

const navigateQuestion = (direction: 'prev' | 'next') => {
    const idx = currentQuestionIndex.value;
    if (idx === -1) return;

    let newIdx = direction === 'next' ? idx + 1 : idx - 1;
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
</script>

<template>
    <Head :title="`Results: ${data.header.course_info?.code || data.header.form_title}`" />

    <!-- Header -->
    <div class="bg-card sticky top-16 z-10 border-b">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div class="flex items-start gap-4">
                <Button variant="ghost" size="icon" @click="router.visit('/forms/admin/results')" class="-ml-2">
                    <ArrowLeft class="h-5 w-5" />
                </Button>
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <Badge variant="secondary" class="text-xs">{{ data.header.form_version }}</Badge>
                        <span class="text-muted-foreground text-xs font-bold">{{ data.header.semester }}</span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        <span v-if="data.header.course_info" class="text-primary mr-2">{{ data.header.course_info.code }}</span>
                        <span>{{ data.header.course_info?.name || data.header.form_title }}</span>
                    </h1>
                </div>
            </div>

            <div class="text-muted-foreground flex items-center gap-6 text-sm">
                <div class="hidden text-right md:block" v-if="data.header.course_info">
                    <div class="text-foreground font-medium">{{ data.header.course_info.instructor }}</div>
                    <div class="text-xs">Section {{ data.header.course_info.section }}</div>
                </div>
                <div class="bg-muted/50 flex items-center gap-2 rounded-lg px-3 py-1.5">
                    <Users class="h-4 w-4" />
                    <span class="text-foreground font-bold">{{ data.header.responses_done }}</span>
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
                    <TabsTrigger value="individual">Individual</TabsTrigger>
                </TabsList>
            </Tabs>
        </div>
    </div>

    <div class="px-4 py-8">
        <!-- SUMMARY TAB -->
        <div v-if="activeTab === 'summary'" class="space-y-8">
            <!-- Overall Rating KPI (Only if valid) -->
            <div v-if="data.overall.average > 0" class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card class="bg-primary text-primary-foreground border-none shadow-lg">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium tracking-wider uppercase opacity-90">Overall Rating</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-baseline gap-2">
                            <span class="text-6xl font-bold">{{ data.overall.average.toFixed(1) }}</span>
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
                            <div class="text-2xl font-bold">{{ data.overall.positive_percent }}%</div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-green-100">
                                <div class="h-full bg-green-600" :style="{ width: data.overall.positive_percent + '%' }"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="text-xs font-bold text-amber-500 uppercase">Neutral</div>
                            <div class="text-2xl font-bold">{{ data.overall.neutral_percent }}%</div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-amber-100">
                                <div class="h-full bg-amber-500" :style="{ width: data.overall.neutral_percent + '%' }"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="text-xs font-bold text-red-500 uppercase">Negative</div>
                            <div class="text-2xl font-bold">{{ data.overall.negative_percent }}%</div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-red-100">
                                <div class="h-full bg-red-500" :style="{ width: data.overall.negative_percent + '%' }"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sections Loop -->
            <div v-for="section in data.sections" :key="section.id" class="space-y-4">
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
                                <SelectGroup v-for="section in data.sections" :key="section.id">
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

        <!-- INDIVIDUAL TAB (Redirect/Info) -->
        <div v-else-if="activeTab === 'individual'" class="bg-card flex flex-col items-center justify-center space-y-6 rounded-xl border py-20">
            <div class="bg-primary/10 rounded-full p-6">
                <MessageSquare class="text-primary h-12 w-12" />
            </div>
            <div class="max-w-md space-y-2 text-center">
                <h3 class="text-xl font-bold">View Individual Responses</h3>
                <p class="text-muted-foreground">Detailed individual responses are available in the Raw Results view. You can review each submission separately.</p>
            </div>
            <Button size="lg" @click="router.visit(`/forms/admin/results/${target.id}/raw`)"> View Raw Results </Button>
        </div>
    </div>
</template>
