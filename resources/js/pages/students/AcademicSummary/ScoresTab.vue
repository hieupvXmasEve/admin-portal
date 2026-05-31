<script setup lang="ts">
import ModuleScoreCard from '@/components/student/ModuleScoreCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { CourseScores, ScoresData, SemesterGpaSnapshot } from '@/types/models';
import { BookOpen, Eye, ListChecks, Target } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    scores: ScoresData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), { loading: false });

const selectedScoreDetails = ref<CourseScores | null>(null);
const showScoreDialog = ref(false);

// ───────────────────────────── helpers ─────────────────────────────

const formatNumber = (value: number | null | undefined, fractionDigits = 2): string => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return Number(value).toFixed(fractionDigits);
};

const formatPercentage = (value: number | null | undefined): string => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
    return `${Number(value).toFixed(2)}%`;
};

const formatDate = (date: string | null | undefined): string => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const gpaToneClass = (gpa: number | null | undefined): string => {
    if (gpa === null || gpa === undefined) return 'text-muted-foreground';
    if (gpa >= 85) return 'text-emerald-700 dark:text-emerald-400';
    if (gpa >= 70) return 'text-sky-700 dark:text-sky-400';
    if (gpa >= 50) return 'text-amber-700 dark:text-amber-400';
    return 'text-rose-700 dark:text-rose-400';
};

const courseAverageBadgeClass = (pct: number | null | undefined): string => {
    if (pct === null || pct === undefined) return 'border-border bg-muted/50 text-muted-foreground';
    if (pct >= 85) return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (pct >= 70) return 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-300';
    if (pct >= 50) return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300';
    return 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300';
};

const assessmentStatusToneClass = (status: string): string => {
    switch (status) {
        case 'graded':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300';
        case 'submitted':
            return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-300';
        case 'pending':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300';
        case 'missing':
            return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300';
        default:
            return 'border-border bg-muted/50 text-muted-foreground';
    }
};

const passFailBadge = (course: CourseScores): { label: string; class: string } => {
    if (course.grade_status && course.grade_status !== 'final') {
        return { label: 'In progress', class: 'border-border bg-muted/50 text-muted-foreground' };
    }
    if (course.is_passed === true) {
        return { label: 'Passed', class: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300' };
    }
    if (course.is_passed === false) {
        return { label: 'Failed', class: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300' };
    }
    return { label: '—', class: 'border-border bg-muted/50 text-muted-foreground' };
};

const standingTone = (standing: string | null | undefined): { label: string; class: string } => {
    const value = (standing ?? 'unknown').toLowerCase();
    if (value === 'normal') {
        return { label: 'Normal', class: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300' };
    }
    if (value === 'warning') {
        return { label: 'Warning', class: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300' };
    }
    if (value === 'probation') {
        return { label: 'Probation', class: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300' };
    }
    return { label: standing ?? 'Unknown', class: 'border-border bg-muted/50 text-muted-foreground' };
};

// ───────────────────────────── grouping ─────────────────────────────

interface SemesterGroup {
    semester_name: string;
    semester_gpa: number | null;
    credit_points_attempted: number | null;
    credit_points_earned: number | null;
    academic_standing: string | null;
    is_finalized: boolean;
    courses: CourseScores[];
}

const courseGroups = computed<SemesterGroup[]>(() => {
    const byName = new Map<string, CourseScores[]>();
    for (const course of props.scores.standalone_units.data) {
        const arr = byName.get(course.semester) ?? [];
        arr.push(course);
        byName.set(course.semester, arr);
    }

    const semesterIndex = new Map<string, SemesterGpaSnapshot>();
    for (const sem of props.scores.semesters ?? []) {
        semesterIndex.set(sem.semester_name, sem);
    }

    const names = Array.from(byName.keys());
    names.sort((a, b) => {
        const sa = semesterIndex.get(a)?.start_date ?? null;
        const sb = semesterIndex.get(b)?.start_date ?? null;
        if (sa && sb) return sb.localeCompare(sa); // most-recent first
        if (sa) return -1;
        if (sb) return 1;
        return a.localeCompare(b);
    });

    return names.map((name) => {
        const snapshot = semesterIndex.get(name);
        return {
            semester_name: name,
            semester_gpa: snapshot ? snapshot.semester_gpa : null,
            credit_points_attempted: snapshot ? snapshot.credit_points_attempted : null,
            credit_points_earned: snapshot ? snapshot.credit_points_earned : null,
            academic_standing: snapshot?.academic_standing ?? null,
            is_finalized: snapshot?.is_finalized ?? false,
            courses: (byName.get(name) ?? []).slice().sort((a, b) => a.course_code.localeCompare(b.course_code)),
        };
    });
});

const cumulative = computed(() => props.scores.cumulative ?? null);

// ───────────────────────────── actions ─────────────────────────────

const openScoreDetails = (course: CourseScores): void => {
    selectedScoreDetails.value = course;
    showScoreDialog.value = true;
};
</script>

<template>
    <div class="space-y-8">
        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="i in 4" :key="i" class="bg-muted/40 h-24 animate-pulse rounded-lg"></div>
            </div>
            <div class="bg-muted/30 h-96 animate-pulse rounded-lg"></div>
        </div>

        <template v-else>
            <!-- ────────── Cumulative GPA panel ────────── -->
            <section aria-labelledby="cumulative-heading" class="border-border from-card via-card to-muted/40 dark:to-muted/20 relative overflow-hidden rounded-xl border bg-gradient-to-br">
                <!-- decorative grid -->
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 opacity-[0.04] dark:opacity-[0.06]"
                    style="background-image: linear-gradient(to right, currentColor 1px, transparent 1px), linear-gradient(to bottom, currentColor 1px, transparent 1px); background-size: 32px 32px"
                ></div>

                <div class="relative p-6 sm:p-8">
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.18em] uppercase">Academic transcript</p>
                            <h2 id="cumulative-heading" class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">Cumulative GPA</h2>
                        </div>
                        <div v-if="cumulative?.last_finalized_at" class="text-muted-foreground text-right text-xs">
                            <span class="block">Last finalized</span>
                            <span class="text-foreground mt-0.5 block font-medium">{{ formatDate(cumulative.last_finalized_at) }}</span>
                        </div>
                    </div>

                    <div v-if="cumulative" class="border-border bg-border grid grid-cols-2 gap-px overflow-hidden rounded-lg border lg:grid-cols-4">
                        <div class="bg-card p-5">
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">Cumulative</p>
                            <p class="mt-2 font-mono text-3xl font-semibold tabular-nums sm:text-4xl" :class="gpaToneClass(cumulative.gpa)">
                                {{ formatNumber(cumulative.gpa, 3) }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">Over {{ cumulative.semesters_count }} semester{{ cumulative.semesters_count === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="bg-card p-5">
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">Credits attempted</p>
                            <p class="mt-2 font-mono text-3xl font-semibold tabular-nums sm:text-4xl">{{ formatNumber(cumulative.credit_points_attempted, 1) }}</p>
                            <p class="text-muted-foreground mt-1 text-xs">All final attempts</p>
                        </div>
                        <div class="bg-card p-5">
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">Credits earned</p>
                            <p class="mt-2 font-mono text-3xl font-semibold text-emerald-700 tabular-nums sm:text-4xl dark:text-emerald-400">
                                {{ formatNumber(cumulative.credit_points_earned, 1) }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">Passed units only</p>
                        </div>
                        <div class="bg-card p-5">
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">Academic standing</p>
                            <div class="mt-3 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium" :class="standingTone(cumulative.academic_standing).class">
                                <span class="size-1.5 rounded-full bg-current"></span>
                                {{ standingTone(cumulative.academic_standing).label }}
                            </div>
                            <p class="text-muted-foreground mt-2 text-xs">Threshold ≥ 50.00</p>
                        </div>
                    </div>

                    <div v-else class="border-border bg-card rounded-lg border border-dashed p-6 text-center">
                        <p class="text-muted-foreground text-sm">No GPA has been finalized yet for this student.</p>
                    </div>
                </div>
            </section>

            <!-- ────────── Summary chips ────────── -->
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="border-border bg-card rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">Course units</p>
                            <p class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ scores.summary.total_courses }}</p>
                        </div>
                        <BookOpen class="size-7 text-sky-600 dark:text-sky-400" />
                    </div>
                </div>

                <div v-if="scores.modules" class="border-border bg-card rounded-lg border p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">Modules</p>
                            <p class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ scores.summary.total_modules }}</p>
                        </div>
                        <ListChecks class="size-7 text-indigo-600 dark:text-indigo-400" />
                    </div>
                </div>
            </section>

            <!-- ────────── Modules (unchanged) ────────── -->
            <section v-if="scores.modules && scores.modules.data.length > 0" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold tracking-tight">Modules</h2>
                    <Badge variant="secondary">{{ scores.modules.data.length }} module(s)</Badge>
                </div>
                <ModuleScoreCard v-for="moduleItem in scores.modules.data" :key="moduleItem.module_id" :module="moduleItem" />
            </section>

            <!-- ────────── Semester tables ────────── -->
            <section v-if="courseGroups.length > 0" class="space-y-8">
                <div v-for="group in courseGroups" :key="group.semester_name" class="border-border bg-card overflow-hidden rounded-xl border">
                    <!-- Semester header -->
                    <header class="border-border bg-muted/30 flex flex-wrap items-center justify-between gap-4 border-b px-5 py-4">
                        <div class="flex items-baseline gap-3">
                            <p class="text-muted-foreground text-[11px] font-medium tracking-[0.18em] uppercase">Semester</p>
                            <h3 class="text-lg font-semibold tracking-tight">{{ group.semester_name }}</h3>
                            <Badge v-if="!group.is_finalized && group.semester_gpa !== null" variant="outline" class="text-[10px] tracking-wider uppercase">Pending</Badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                            <div class="flex flex-col">
                                <span class="text-muted-foreground text-[10px] font-medium tracking-[0.16em] uppercase">Semester GPA</span>
                                <span class="font-mono text-lg font-semibold tabular-nums" :class="gpaToneClass(group.semester_gpa)">
                                    {{ group.semester_gpa !== null ? formatNumber(group.semester_gpa, 3) : '—' }}
                                </span>
                            </div>
                            <div class="bg-border hidden h-8 w-px sm:block"></div>
                            <div class="flex flex-col">
                                <span class="text-muted-foreground text-[10px] font-medium tracking-[0.16em] uppercase">Credits</span>
                                <span class="font-mono text-sm font-medium tabular-nums">
                                    <span class="text-emerald-700 dark:text-emerald-400">{{ formatNumber(group.credit_points_earned, 1) }}</span>
                                    <span class="text-muted-foreground"> / </span>
                                    <span>{{ formatNumber(group.credit_points_attempted, 1) }}</span>
                                </span>
                            </div>
                            <div class="bg-border hidden h-8 w-px sm:block"></div>
                            <div class="flex flex-col">
                                <span class="text-muted-foreground text-[10px] font-medium tracking-[0.16em] uppercase">Standing</span>
                                <Badge v-if="group.academic_standing" variant="outline" class="mt-0.5 w-fit border" :class="standingTone(group.academic_standing).class">
                                    {{ standingTone(group.academic_standing).label }}
                                </Badge>
                                <span v-else class="text-muted-foreground text-sm">—</span>
                            </div>
                        </div>
                    </header>

                    <!-- Course table -->
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-transparent">
                                <TableHead class="text-muted-foreground w-[120px] text-[11px] tracking-wider uppercase">Code</TableHead>
                                <TableHead class="text-muted-foreground text-[11px] tracking-wider uppercase">Course</TableHead>
                                <TableHead class="text-muted-foreground w-[80px] text-right text-[11px] tracking-wider uppercase">Credit</TableHead>
                                <TableHead class="text-muted-foreground w-[120px] text-right text-[11px] tracking-wider uppercase">Final</TableHead>
                                <TableHead class="text-muted-foreground w-[70px] text-right text-[11px] tracking-wider uppercase">Letter</TableHead>
                                <TableHead class="text-muted-foreground w-[110px] text-right text-[11px] tracking-wider uppercase">Result</TableHead>
                                <TableHead class="text-muted-foreground w-[110px] text-right text-[11px] tracking-wider uppercase">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="course in group.courses" :key="course.course_offering_id" class="group/row hover:bg-muted/40 transition-colors">
                                <TableCell class="font-mono text-sm font-medium tabular-nums">{{ course.course_code }}</TableCell>
                                <TableCell>
                                    <div class="max-w-[420px]">
                                        <p class="truncate font-medium">{{ course.course_name }}</p>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span v-if="course.credit_points !== undefined && course.credit_points > 0" class="font-mono text-sm font-medium tabular-nums">
                                        {{ formatNumber(course.credit_points, 1) }}
                                    </span>
                                    <span v-else class="text-muted-foreground text-sm">—</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span class="inline-flex min-w-[72px] justify-end rounded-md border px-2.5 py-1 font-mono text-sm font-semibold tabular-nums" :class="courseAverageBadgeClass(course.course_average)">
                                        {{ formatNumber(course.course_average, 2) }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span v-if="course.final_letter_grade" class="font-mono text-sm font-semibold tabular-nums" :class="gpaToneClass(course.course_average)">
                                        {{ course.final_letter_grade }}
                                    </span>
                                    <span v-else class="text-muted-foreground text-sm">—</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium tracking-wider uppercase" :class="passFailBadge(course).class">
                                        <span class="size-1.5 rounded-full bg-current"></span>
                                        {{ passFailBadge(course).label }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button variant="ghost" size="sm" class="opacity-70 transition-opacity group-hover/row:opacity-100" @click="openScoreDetails(course)">
                                        <Eye class="mr-1.5 size-3.5" />
                                        Details
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </section>

            <!-- Empty state -->
            <section v-else class="border-border bg-card rounded-xl border border-dashed py-14 text-center">
                <Target class="text-muted-foreground mx-auto mb-3 size-10" />
                <h3 class="mb-1 text-lg font-semibold tracking-tight">No course scores yet</h3>
                <p class="text-muted-foreground text-sm">There are no graded courses recorded for this student.</p>
            </section>
        </template>

        <!-- ────────── Score detail dialog ────────── -->
        <Dialog v-model:open="showScoreDialog">
            <DialogContent class="max-h-[80vh] !max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle v-if="selectedScoreDetails" class="flex items-center gap-2">
                        <span class="text-muted-foreground font-mono text-sm font-medium">{{ selectedScoreDetails.course_code }}</span>
                        <span class="text-base font-semibold">{{ selectedScoreDetails.course_name }}</span>
                    </DialogTitle>
                </DialogHeader>

                <div v-if="selectedScoreDetails" class="space-y-5">
                    <!-- Course header strip -->
                    <div class="border-border bg-border grid grid-cols-2 gap-px overflow-hidden rounded-lg border md:grid-cols-4">
                        <div class="bg-card p-3">
                            <p class="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Semester</p>
                            <p class="mt-1 truncate font-medium">{{ selectedScoreDetails.semester }}</p>
                        </div>
                        <div class="bg-card p-3">
                            <p class="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Course average</p>
                            <p class="mt-1 font-mono font-semibold tabular-nums" :class="gpaToneClass(selectedScoreDetails.course_average)">
                                {{ formatPercentage(selectedScoreDetails.course_average) }}
                            </p>
                        </div>
                        <div class="bg-card p-3">
                            <p class="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Assessments</p>
                            <p class="mt-1 font-mono font-medium tabular-nums">{{ selectedScoreDetails.total_assessments }}</p>
                        </div>
                        <div class="bg-card p-3">
                            <p class="text-muted-foreground text-[10px] font-medium tracking-wider uppercase">Completed</p>
                            <p class="mt-1 font-mono font-medium text-emerald-700 tabular-nums dark:text-emerald-400">
                                {{ selectedScoreDetails.completed_assessments }}
                            </p>
                        </div>
                    </div>

                    <!-- Assessment table -->
                    <div class="border-border overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="text-muted-foreground text-[11px] tracking-wider uppercase">Assessment</TableHead>
                                    <TableHead class="text-muted-foreground text-[11px] tracking-wider uppercase">Type</TableHead>
                                    <TableHead class="text-muted-foreground text-[11px] tracking-wider uppercase">Due</TableHead>
                                    <TableHead class="text-muted-foreground text-right text-[11px] tracking-wider uppercase">Score</TableHead>
                                    <TableHead class="text-muted-foreground text-right text-[11px] tracking-wider uppercase">%</TableHead>
                                    <TableHead class="text-muted-foreground text-right text-[11px] tracking-wider uppercase">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="score in selectedScoreDetails.scores" :key="score.id">
                                    <TableCell>
                                        <p class="font-medium">{{ score.assessment_name }}</p>
                                        <p v-if="score.is_late" class="text-xs text-rose-600">Late submission</p>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">{{ score.assessment_type }}</TableCell>
                                    <TableCell class="text-muted-foreground text-sm">{{ formatDate(score.due_date) }}</TableCell>
                                    <TableCell class="text-right font-mono tabular-nums"> {{ score.points_earned }} / {{ score.max_points }} </TableCell>
                                    <TableCell class="text-right font-mono font-medium tabular-nums" :class="gpaToneClass(score.percentage_score)">
                                        {{ formatPercentage(score.percentage_score) }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <span class="inline-flex rounded-md border px-2 py-0.5 text-[11px] font-medium tracking-wider uppercase" :class="assessmentStatusToneClass(score.status)">
                                            {{ score.status }}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
