<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, Users, Star, TrendingUp, AlertCircle, CheckCircle2, Info, MessageSquare } from 'lucide-vue-next';
import type { FormTarget } from '@/types/forms';

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
        title: string;
        questions_count: number;
        average: number;
        positive_percent: number;
        negative_percent: number;
        distribution: number[];
    }[];
    questions: {
        text: string;
        average: number;
        distribution: number[];
    }[];
}

const props = defineProps<{
    target: FormTarget;
    data: AggregateData;
}>();

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

</script>

<template>
    <Head :title="`Aggregate: ${data.header.course_info?.code || data.header.form_title}`" />

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 border-b pb-6 mt-4">
        <div class="flex items-start gap-4">
            <Button variant="outline" size="icon" @click="router.visit('/forms/admin/results')" class="mt-1 shrink-0 h-10 w-10">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <Badge variant="secondary" class="text-xs font-semibold px-2 py-0.5">{{ data.header.form_version }}</Badge>
                    <span class="text-xs text-muted-foreground uppercase tracking-wider font-bold">{{ data.header.semester }}</span>
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground">
                    <span v-if="data.header.course_info" class="text-primary mr-3">{{ data.header.course_info.code }}</span>
                    <span>{{ data.header.course_info?.name || data.header.form_title }}</span>
                </h1>
                <p v-if="data.header.course_info" class="text-base text-muted-foreground mt-2 flex items-center gap-3">
                    <span class="font-semibold text-foreground">Section {{ data.header.course_info.section }}</span>
                    <span class="text-muted-foreground/30">•</span>
                    <span>Instructor: <span class="font-semibold text-foreground">{{ data.header.course_info.instructor }}</span></span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-6 bg-card border rounded-xl p-5 shadow-sm min-w-[280px]">
            <div class="bg-primary/5 p-3 rounded-xl">
                <Users class="text-primary h-6 w-6" />
            </div>
            <div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-3xl font-extrabold">{{ data.header.responses_done }}</span>
                    <span class="text-muted-foreground text-sm font-medium">/ {{ data.header.responses_total }}</span>
                </div>
                <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs uppercase font-bold text-muted-foreground">Responses</span>
                    <Badge variant="outline" class="text-xs px-2 py-0 h-5 border-primary/20 text-primary font-bold">{{ data.header.responses_percent }}%</Badge>
                </div>
            </div>
        </div>
    </div>

    <div v-if="data.sections.length === 0" class="text-center py-24 bg-card border rounded-2xl">
            <BarChart3 class="mx-auto h-16 w-16 text-muted-foreground mb-6 opacity-20" />
            <h3 class="text-xl font-bold text-foreground">No rating data available</h3>
            <p class="text-base text-muted-foreground mt-2">This dashboard only aggregates rating-based questions.</p>
    </div>

    <template v-else>
        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Overall Score -->
            <Card class="relative lg:col-span-1 bg-primary text-primary-foreground border-none overflow-hidden shadow-xl shadow-primary/20 p-2">
                <div class="absolute -top-4 -right-4 p-4 opacity-5">
                    <Star class="h-40 w-40" />
                </div>
                <CardHeader class="pb-1">
                    <CardTitle class="text-xs font-bold uppercase tracking-widest opacity-80">Overall Rating</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-3">
                        <span class="text-7xl font-extrabold tracking-tighter">{{ data.overall.average.toFixed(1) }}</span>
                        <Star class="h-8 w-8 fill-current" />
                    </div>
                    <div class="mt-6 flex items-center gap-2.5 text-sm font-semibold opacity-90">
                        <TrendingUp class="h-4 w-4" />
                        Aggregated average score
                    </div>
                </CardContent>
            </Card>

            <!-- Sentiment Distribution -->
            <Card class="lg:col-span-2 border border-border shadow-sm p-2">
                <CardHeader class="pb-1">
                    <CardTitle class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Sentiment Distribution</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-3 gap-8 pt-8">
                    <div class="space-y-3">
                        <div class="text-xs font-bold uppercase text-green-600">Positive (4-5)</div>
                        <div class="text-4xl font-extrabold">{{ data.overall.positive_percent }}%</div>
                        <div class="w-full h-2 bg-green-100 rounded-full overflow-hidden">
                            <div class="h-full bg-green-600" :style="{ width: data.overall.positive_percent + '%' }"></div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="text-xs font-bold uppercase text-amber-500">Neutral (3)</div>
                        <div class="text-4xl font-extrabold">{{ data.overall.neutral_percent }}%</div>
                        <div class="w-full h-2 bg-amber-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-500" :style="{ width: data.overall.neutral_percent + '%' }"></div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="text-xs font-bold uppercase text-red-500">Negative (1-2)</div>
                        <div class="text-4xl font-extrabold">{{ data.overall.negative_percent }}%</div>
                        <div class="w-full h-2 bg-red-100 rounded-full overflow-hidden">
                            <div class="h-full bg-red-500" :style="{ width: data.overall.negative_percent + '%' }"></div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Section-level Aggregation -->
        <div class="space-y-6 pt-4">
            <div class="flex items-center gap-4">
                <h2 class="text-base font-bold uppercase tracking-wider text-muted-foreground italic">Section Performance</h2>
                <div class="h-[1px] flex-1 bg-border"></div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card v-for="section in data.sections" :key="section.title" 
                        class="bg-card hover:shadow-lg transition-all duration-300 border-t-4" 
                        :class="section.average >= 4 ? 'border-t-green-600' : section.average >= 3 ? 'border-t-amber-500' : 'border-t-red-500'">
                    <CardHeader class="p-6 pb-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-xs font-bold text-muted-foreground uppercase opacity-70 mb-1">{{ section.questions_count }} Qs</div>
                                <CardTitle class="text-lg font-bold line-clamp-2 leading-tight h-[3.5rem]">{{ section.title }}</CardTitle>
                            </div>
                            <div class="p-2 rounded-lg shrink-0" :class="getRatingBg(section.average)">
                                <component :is="getStatusIcon(section.average)" class="h-5 w-5" :class="getRatingColor(section.average)" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-6 pt-0 space-y-5">
                        <div class="flex items-baseline justify-between mt-3">
                            <div class="text-4xl font-black shrink-0" :class="getRatingColor(section.average)">
                                    {{ section.average.toFixed(1) }} <span class="text-xs font-medium text-muted-foreground font-sans">avg</span>
                            </div>
                            <div class="text-right space-y-1">
                                <div class="text-xs font-bold text-green-600">Pos {{ section.positive_percent }}%</div>
                                <div class="text-xs font-bold text-red-500">Neg {{ section.negative_percent }}%</div>
                            </div>
                        </div>

                        <!-- Distribution Visual -->
                        <div class="flex items-end gap-1.5 h-10 pt-2">
                            <div v-for="(count, idx) in section.distribution" :key="idx" 
                                    class="flex-1 rounded-sm transition-colors"
                                    :class="idx >= 3 ? 'bg-green-600/40' : idx === 2 ? 'bg-amber-500/40' : 'bg-red-500/40'"
                                    :style="{ height: `${Math.max((count / Math.max(...section.distribution, 1)) * 100, 10)}%` }" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Question-level Details -->
        <div class="space-y-6 pt-6">
            <div class="flex items-center gap-4">
                <h2 class="text-base font-bold uppercase tracking-wider text-muted-foreground italic text-left">Question Ranking</h2>
                <div class="h-[1px] flex-1 bg-border"></div>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <div v-for="q in data.questions" :key="q.text" 
                        class="group flex flex-col md:flex-row md:items-center gap-6 p-6 bg-card border border-border rounded-xl hover:bg-muted/40 transition-all">
                    <div class="flex-1 flex items-start gap-4">
                        <div class="text-base font-bold leading-snug text-foreground">{{ q.text }}</div>
                    </div>

                    <div class="flex items-center gap-8 justify-between md:justify-end">
                        <!-- Distribution dots -->
                        <div class="flex items-center gap-1.5">
                                <div v-for="(count, i) in q.distribution" :key="i" 
                                    class="w-4 rounded-full" 
                                    :class="[
                                        i >= 3 ? 'bg-green-600' : i === 2 ? 'bg-amber-500' : 'bg-red-500',
                                        count === 0 ? 'opacity-10' : 'opacity-100'
                                    ]"
                                    :style="{ height: `${3 + (count / Math.max(...q.distribution, 1)) * 12}px` }"
                                />
                        </div>

                        <div class="flex items-center gap-2 min-w-[60px] justify-end">
                            <span class="text-xl font-black" :class="getRatingColor(q.average)">{{ q.average.toFixed(1) }}</span>
                            <Star class="h-4 w-4 fill-current" :class="getRatingColor(q.average)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-center pt-12">
                <Button variant="outline" size="lg" class="text-muted-foreground text-xs font-semibold gap-3 rounded-full px-8 shadow-sm" @click="router.visit(`/forms/admin/results/${target.id}/raw`)">
                    <MessageSquare class="h-4 w-4" />
                    Read student comments in the Raw Results page
                </Button>
        </div>
    </template>
</template>
