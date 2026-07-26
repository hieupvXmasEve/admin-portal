<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import CanvasSyncPreviewDialog from '@/pages/CourseOfferings/components/CanvasSyncPreviewDialog.vue';
import RecalculatePreviewDialog from '@/pages/CourseOfferings/components/RecalculatePreviewDialog.vue';
import type { CourseOffering } from '@/types/models';
import type { OperationalState } from '@/types/operational-state';
import { formatSubmissionTypes } from '@/utils/canvasGradeFormatter';
import { describeMetropoliaScheme, type MetropoliaScheme } from '@/utils/metropoliaSchemeFormula';
import { Link, router } from '@inertiajs/vue3';
import { BarChart3, BookOpen, Calculator, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

// ---- Type definitions (matches GetCourseOfferingScoresQuery output) ----
interface AssessmentDetail {
    id: number;
    component_id: number;
    component_name: string;
    component_type: string;
    component_weight: number;
    detail_name: string;
    detail_weight: number;
    max_points: number | null;
    grading_type: 'points' | 'percent' | 'letter_grade' | 'gpa_scale' | 'pass_fail' | 'not_graded';
    submission_types: string[];
    canvas_assignment_id: string | null;
}

interface Score {
    component_detail_id: number;
    percentage_score: number | string | null;
    letter_grade: string | null;
    status: string;
    score_status: string;
    graded_at: string | null;
    is_late: boolean;
    score_excluded: boolean;
}

interface ComponentTotal {
    component_id: number;
    percentage_score: number | null;
    contribution_to_final: number | null;
    out_of_weight: number;
    is_attendance: boolean;
}

// Presenter-shaped output of GradeDisplayPresenter — stored-breakdown only,
// never recomputed (ADR 0014). Null until the offering is finalized.
interface GradeDisplayComponent {
    code: string;
    label: string;
    raw_percentage: number | string | null;
    converted_grade: number | string | null;
    requirement_status: string | null;
}

interface GradeDisplay {
    scheme_engine: string;
    scale: 'numeric_0_5' | 'pass_fail' | 'percentage';
    final_label: string;
    final_numeric: number | null;
    pass_status: 'passed' | 'failed';
    components: GradeDisplayComponent[];
}

interface StudentScore {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    scores: Score[];
    component_totals: ComponentTotal[];
    total_percentage: number | null;
    total_letter_grade: string | null;
    grade_status: string;
    completion_status: string;
    grade_display: GradeDisplay | null;
}

interface AssessmentComponent {
    id: number;
    code: string;
    name: string;
    type: string;
    weight: number;
    details: Array<{ id: number; name: string; weight: number; max_points: number | null }>;
}

interface Statistics {
    average_score: number;
}

export interface ScoresData {
    statistics: Statistics;
    assessment_components: AssessmentComponent[];
    assessment_details: AssessmentDetail[];
    scores_grid: StudentScore[];
    course_offering: {
        id: number;
        unit_id: number;
        min_grade_threshold: number;
        semester_id: number;
        scheme: MetropoliaScheme | null;
    };
}

interface Props {
    courseOffering: CourseOffering;
    scoresData?: ScoresData;
    operationalState?: OperationalState;
}

const props = defineProps<Props>();

const statusFilter = ref<string>('all');

// ---- Sync from Canvas (issue 10) ----
// operational_state.available_actions is the backend contract (ADR 0013) —
// the tab never infers "mapped + not completed + permission" itself.
const canSyncGrades = computed(() => (props.operationalState?.available_actions ?? []).some((action) => action.action === 'sync_grades'));
const selectedStudentIds = ref<number[]>([]);
const isSyncDialogOpen = ref(false);

// ---- Recalculate (issue 11) — preview-first, gated by the same backend
// contract. has_mapped_canvas_course is independent of sync_course_grades
// (unlike canSyncGrades above) so the embedded pull option doesn't
// silently disappear for staff who only hold recalculate_course_offering.
const canRecalculate = computed(() => (props.operationalState?.available_actions ?? []).some((action) => action.action === 'recalculate'));
const hasMappedCanvasCourse = computed(() => props.operationalState?.has_mapped_canvas_course ?? false);
const isRecalculateDialogOpen = ref(false);
const rosterStudents = computed(() => (props.scoresData?.scores_grid ?? []).map((s) => ({ id: s.id, student_id: s.student_id, full_name: s.full_name })));

const openRecalculateDialog = () => {
    isRecalculateDialogOpen.value = true;
};

const onRecalculateApplied = () => {
    toast.success('Course results recalculated successfully');
    router.reload({ only: ['scoresData'] });
};

const isAllSelected = computed(() => filteredScoresGrid.value.length > 0 && selectedStudentIds.value.length === filteredScoresGrid.value.length);
const isSomeSelected = computed(() => selectedStudentIds.value.length > 0 && !isAllSelected.value);

const toggleSelectAll = (checked: boolean | 'indeterminate') => {
    selectedStudentIds.value = checked === true ? filteredScoresGrid.value.map((s) => s.id) : [];
};

const toggleStudentSelection = (studentId: number, checked: boolean) => {
    selectedStudentIds.value = checked ? [...selectedStudentIds.value, studentId] : selectedStudentIds.value.filter((id) => id !== studentId);
};

const openSyncDialog = () => {
    if (selectedStudentIds.value.length === 0) return;
    isSyncDialogOpen.value = true;
};

const onSynced = () => {
    toast.success('Canvas grades synced successfully');
    selectedStudentIds.value = [];
    router.reload({ only: ['scoresData'] });
};

// Metropolia's authoritative pass/fail is the stored, gate-aware
// grade_display.pass_status — a gate failure can still map to a high raw
// percentage, so it must never be compared against the flat min_grade_threshold
// (see CourseCompletionService.php:216-222). Non-scheme/legacy offerings keep
// the original flat-threshold behavior unchanged.
const isMetropolia = computed(() => !!props.scoresData?.course_offering.scheme?.engine.startsWith('metropolia'));

const filteredScoresGrid = computed(() => {
    if (!props.scoresData) return [];
    if (statusFilter.value === 'all') return props.scoresData.scores_grid;

    if (isMetropolia.value) {
        if (statusFilter.value === 'pass') return props.scoresData.scores_grid.filter((s) => s.grade_display?.pass_status === 'passed');
        if (statusFilter.value === 'fail') return props.scoresData.scores_grid.filter((s) => s.grade_display?.pass_status === 'failed');
        if (statusFilter.value === 'not_finalized') return props.scoresData.scores_grid.filter((s) => s.grade_display === null);
        return props.scoresData.scores_grid;
    }

    const threshold = props.scoresData.course_offering.min_grade_threshold;
    if (statusFilter.value === 'pass') return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage >= threshold);
    if (statusFilter.value === 'fail') return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage < threshold);
    return props.scoresData.scores_grid;
});

const passCount = computed(() => {
    if (!props.scoresData) return 0;
    if (isMetropolia.value) return props.scoresData.scores_grid.filter((s) => s.grade_display?.pass_status === 'passed').length;
    const threshold = props.scoresData.course_offering.min_grade_threshold;
    return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage >= threshold).length;
});

const failCount = computed(() => {
    if (!props.scoresData) return 0;
    if (isMetropolia.value) return props.scoresData.scores_grid.filter((s) => s.grade_display?.pass_status === 'failed').length;
    const threshold = props.scoresData.course_offering.min_grade_threshold;
    return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage < threshold).length;
});

const notFinalizedCount = computed(() => {
    if (!props.scoresData || !isMetropolia.value) return 0;
    return props.scoresData.scores_grid.filter((s) => s.grade_display === null).length;
});

const detailsByComponent = computed(() => {
    if (!props.scoresData) return {};
    const groups: Record<number, AssessmentDetail[]> = {};
    props.scoresData.assessment_details.forEach((detail) => {
        if (!groups[detail.component_id]) groups[detail.component_id] = [];
        groups[detail.component_id].push(detail);
    });
    return groups;
});

// ---- Score display helpers ----
const getScoreBadgeVariant = (score: Score): 'success' | 'destructive' | 'warning' | 'outline' | 'secondary' => {
    if (score.percentage_score === null || score.percentage_score === undefined) return 'outline';
    if (score.score_excluded) return 'secondary';
    const num = typeof score.percentage_score === 'number' ? score.percentage_score : parseFloat(score.percentage_score as string);
    if (isNaN(num)) return 'outline';
    if (num >= 80) return 'success';
    if (num >= 60) return 'warning';
    return 'destructive';
};

const getScoreDisplay = (score: Score): string => {
    if (score.percentage_score === null || score.percentage_score === undefined) return '-';
    if (score.score_excluded) return 'X';
    const num = typeof score.percentage_score === 'number' ? score.percentage_score : parseFloat(score.percentage_score as string);
    if (isNaN(num)) return '-';
    return num.toFixed(1);
};

const getTotalBadgeVariant = (percentage: number | null): 'success' | 'destructive' | 'warning' | 'outline' => {
    if (percentage === null) return 'outline';
    if (percentage >= 80) return 'success';
    if (percentage >= 60) return 'warning';
    return 'destructive';
};

const getComponentTotalBadgeVariant = (total: ComponentTotal): 'success' | 'destructive' | 'warning' | 'outline' => {
    // Metropolia components pass/fail on their own gate (e.g. ≥40%), not the
    // 60/80% coloring used for the default scheme — a neutral badge here avoids
    // implying a result that the purple "Scheme Grade" cell already states.
    if (isMetropolia.value) return 'outline';
    if (total.percentage_score === null) return 'outline';
    if (total.percentage_score >= 80) return 'success';
    if (total.percentage_score >= 60) return 'warning';
    return 'destructive';
};

const getComponentTypeLabel = (type: string): string => {
    const labels: Record<string, string> = { quiz: 'Quiz', assignment: 'Assignment', project: 'Project', exam: 'Exam', online_activity: 'Activity', attendance: 'Attendance', other: 'Other' };
    return labels[type] || type;
};

const getGradingTypeLabel = (gradingType: string): string => {
    const labels: Record<string, string> = { points: 'manual', percent: 'percentage', letter_grade: 'letter grade', gpa_scale: 'GPA scale', pass_fail: 'pass/fail', not_graded: 'not graded' };
    return labels[gradingType] || gradingType;
};

const getAssignmentHeaderInfo = (detail: AssessmentDetail): string => {
    const maxPoints = detail.max_points ?? 0;
    return `out of ${maxPoints} (${getGradingTypeLabel(detail.grading_type)})`;
};

const getGradeStatusVariant = (status: string): 'success' | 'destructive' | 'warning' | 'outline' | 'secondary' => {
    if (status === 'final') return 'success';
    if (status === 'provisional') return 'warning';
    if (status === 'disputed' || status === 'under_review') return 'destructive';
    if (status === 'not_graded') return 'outline';
    return 'secondary';
};

const getGradeStatusLabel = (status: string): string => {
    const labels: Record<string, string> = { draft: 'Draft', provisional: 'Provisional', final: 'Final', disputed: 'Disputed', under_review: 'Under Review', not_graded: 'Not Graded' };
    return labels[status] || status;
};

// ---- Scheme display (stored breakdown only — no live recomputation, ADR 0014) ----
const hasScheme = computed(() => !!props.scoresData?.course_offering.scheme);

// The generic "Total" column is a flat percentage that's diagnostic-only for
// metropolia (a gate failure can still carry a high percentage) — showing it
// alongside the gate-aware "Scheme Grade" column is exactly the visual mixing
// this fixes, so it's dropped entirely for metropolia offerings.
const showTotalColumn = computed(() => !isMetropolia.value);

// Sticky right-offset math: when Total is shown (non-metropolia), it always
// stays at the far right (right-0, 120px wide) so non-scheme offerings render
// byte-identical to today. "Scheme Grade" (160px) sits just left of it. For
// metropolia, Total is hidden so Scheme Grade becomes the rightmost column.
const schemeGradeRightPx = computed(() => (showTotalColumn.value ? 120 : 0));
const statusColRightPx = computed(() => {
    if (!hasScheme.value) return 120;
    return showTotalColumn.value ? 280 : 160;
});

const metropoliaFormulaLines = computed(() => {
    if (!isMetropolia.value || !props.scoresData?.course_offering.scheme) return [];
    return describeMetropoliaScheme(props.scoresData.course_offering.scheme);
});

const schemeAwaitingFinalization = computed(() => hasScheme.value && !(props.scoresData?.scores_grid ?? []).some((s) => s.grade_display !== null));

const scaleLabel = (scale: GradeDisplay['scale']): string => {
    const labels: Record<GradeDisplay['scale'], string> = { numeric_0_5: '0–5', pass_fail: 'Pass/Fail', percentage: 'Percentage' };
    return labels[scale] || scale;
};

const findComponentDisplay = (student: StudentScore, code: string): GradeDisplayComponent | null => {
    return student.grade_display?.components.find((c) => c.code === code) ?? null;
};
</script>

<template>
    <div class="space-y-6">
        <!-- No syllabus template empty state -->
        <div v-if="!courseOffering.syllabus_template" class="py-12 text-center">
            <BookOpen class="text-muted-foreground mx-auto h-12 w-12" />
            <h3 class="mt-4 text-lg font-semibold">No syllabus template assigned</h3>
            <p class="text-muted-foreground mt-2 text-sm">Assessment scores require a syllabus template with assessment components configured.</p>
        </div>

        <!-- Loading skeleton if scoresData not yet loaded -->
        <div v-else-if="!scoresData" class="space-y-4">
            <Skeleton class="h-32 w-full" />
            <Skeleton class="h-64 w-full" />
        </div>

        <!-- Scores content -->
        <template v-else>
            <!-- Header row: average score (the one stat not shown elsewhere on the cockpit page) + actions -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <BarChart3 class="text-muted-foreground h-4 w-4" />
                    <span class="text-muted-foreground text-sm">Average score:</span>
                    <span class="text-lg font-bold" :class="[scoresData.statistics.average_score >= 80 ? 'text-green-600' : scoresData.statistics.average_score >= 60 ? 'text-yellow-600' : 'text-red-600']">
                        {{ scoresData.statistics.average_score.toFixed(1) }}%
                    </span>
                    <Badge v-if="scoresData.course_offering.scheme" variant="outline" class="gap-1 border-purple-300 text-purple-700 dark:text-purple-400">
                        {{ scoresData.course_offering.scheme.engine }} · {{ scaleLabel(scoresData.course_offering.scheme.scale) }}
                    </Badge>
                </div>

                <div class="flex items-center gap-2">
                    <Button v-if="canRecalculate" variant="outline" @click="openRecalculateDialog">
                        <Calculator class="mr-2 h-4 w-4" />
                        Recalculate Course Result
                    </Button>

                    <Button v-if="canSyncGrades" variant="outline" :disabled="selectedStudentIds.length === 0" @click="openSyncDialog">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Sync from Canvas{{ selectedStudentIds.length > 0 ? ` (${selectedStudentIds.length})` : '' }}
                    </Button>
                </div>
            </div>

            <!-- Scheme empty state: badge shown pre-finalization, grades appear after -->
            <p v-if="schemeAwaitingFinalization" class="text-muted-foreground text-sm">Scheme grades appear after finalization.</p>

            <!-- Legend: the generic percentage-color legend only applies to the
            default scheme's letter-grade display. Metropolia pass/fail comes
            from gates, not this coloring, so it's replaced by the actual
            grading formula instead. -->
            <Card v-if="!isMetropolia">
                <CardHeader>
                    <CardTitle class="text-sm">Legend</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center gap-2"><Badge variant="success">90–100</Badge><span>4.0 (A+)</span></div>
                        <div class="flex items-center gap-2"><Badge variant="success">80–89</Badge><span>A−/A</span></div>
                        <div class="flex items-center gap-2"><Badge variant="success">60–79</Badge><span>B−/B/B+</span></div>
                        <div class="flex items-center gap-2"><Badge variant="destructive">0–59</Badge><span>F/D</span></div>
                        <div class="flex items-center gap-2"><Badge variant="outline">-</Badge><span>Not Graded</span></div>
                        <div class="flex items-center gap-2"><Badge variant="secondary">X</Badge><span>Excluded</span></div>
                    </div>
                </CardContent>
            </Card>

            <Card v-else>
                <CardHeader>
                    <CardTitle class="text-sm">Grading Formula ({{ scoresData.course_offering.scheme?.engine }})</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="list-disc space-y-1 pl-5 text-sm">
                        <li v-for="(line, index) in metropoliaFormulaLines" :key="index">{{ line }}</li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Filters -->
            <Card>
                <CardHeader><CardTitle>Filters</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="space-y-2">
                            <Label v-if="!isMetropolia"
                                >Student Status — <span class="text-sm text-yellow-500">Min Grade Threshold: {{ scoresData.course_offering.min_grade_threshold }}</span></Label
                            >
                            <Label v-else>Student Status</Label>
                            <Select v-model="statusFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All students" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Students ({{ scoresData.scores_grid.length }})</SelectItem>
                                    <SelectItem value="pass">Pass ({{ passCount }})</SelectItem>
                                    <SelectItem value="fail">Fail ({{ failCount }})</SelectItem>
                                    <SelectItem v-if="isMetropolia" value="not_finalized">Not Finalized ({{ notFinalizedCount }})</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assessment Scores Grid -->
            <Card>
                <CardHeader>
                    <CardTitle>
                        Assessment Scores Grid
                        <span class="text-muted-foreground ml-2 text-sm font-normal"> (Showing {{ filteredScoresGrid.length }} of {{ scoresData.scores_grid.length }} students, {{ scoresData.assessment_details.length }} assessment columns) </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="scoresData.assessment_details.length === 0" class="text-muted-foreground p-8 text-center">
                        <p class="text-lg font-semibold">⚠️ No Assessment Components Found</p>
                        <p class="mt-2">This course offering does not have a syllabus template with assessment components configured.</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead v-if="canSyncGrades" class="sticky left-0 z-10 w-10 bg-white dark:bg-gray-950">
                                        <Checkbox :model-value="isAllSelected" :indeterminate="isSomeSelected" @update:model-value="toggleSelectAll" />
                                    </TableHead>
                                    <TableHead class="sticky z-10 min-w-[100px] bg-white dark:bg-gray-950" :class="canSyncGrades ? 'left-10' : 'left-0'">Student</TableHead>
                                    <template v-for="component in scoresData.assessment_components" :key="`component-${component.id}`">
                                        <template v-if="component.type !== 'attendance'">
                                            <TableHead v-for="detail in detailsByComponent[component.id] || []" :key="detail.id" class="min-w-[140px] text-center">
                                                <div class="space-y-1.5 text-xs">
                                                    <div class="font-bold">{{ detail.detail_name }}</div>
                                                    <div class="text-muted-foreground text-[11px]">{{ getAssignmentHeaderInfo(detail) }}</div>
                                                    <div v-if="detail.submission_types && detail.submission_types.length > 0" class="text-muted-foreground text-[10px]">
                                                        {{ formatSubmissionTypes(detail.submission_types) }}
                                                    </div>
                                                    <div v-if="detail.canvas_assignment_id" class="flex items-center justify-center gap-1">
                                                        <Badge variant="secondary" class="px-1 py-0 text-[9px]">Canvas</Badge>
                                                    </div>
                                                </div>
                                            </TableHead>
                                        </template>
                                        <TableHead class="min-w-[160px] bg-blue-50 text-center dark:bg-blue-950/20">
                                            <div class="space-y-1.5 text-xs">
                                                <div class="font-bold text-blue-700 dark:text-blue-400">{{ component.name }} Total</div>
                                                <div class="flex items-center justify-center gap-1">
                                                    <Badge variant="outline" class="px-1 py-0 text-[10px]">{{ getComponentTypeLabel(component.type) }}</Badge>
                                                </div>
                                                <div class="text-muted-foreground text-[11px]">out of {{ component.weight }}%</div>
                                            </div>
                                        </TableHead>
                                        <TableHead v-if="hasScheme" class="min-w-[140px] bg-purple-50 text-center dark:bg-purple-950/20">
                                            <div class="space-y-1.5 text-xs">
                                                <div class="font-bold text-purple-700 dark:text-purple-400">{{ component.name }} Grade</div>
                                                <div class="text-muted-foreground text-[11px]">converted / requirement</div>
                                            </div>
                                        </TableHead>
                                    </template>
                                    <TableHead class="sticky z-10 min-w-[100px] bg-white text-center dark:bg-gray-950" :style="{ right: statusColRightPx + 'px' }"> Status </TableHead>
                                    <TableHead v-if="hasScheme" class="sticky z-10 min-w-[160px] bg-white text-center dark:bg-gray-950" :style="{ right: schemeGradeRightPx + 'px' }"> Scheme Grade </TableHead>
                                    <TableHead v-if="showTotalColumn" class="sticky right-0 z-10 min-w-[120px] bg-white text-center dark:bg-gray-950">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="student in filteredScoresGrid" :key="student.student_id">
                                    <TableCell v-if="canSyncGrades" class="sticky left-0 z-10 bg-white dark:bg-gray-950">
                                        <Checkbox :model-value="selectedStudentIds.includes(student.id)" @update:model-value="(checked) => toggleStudentSelection(student.id, checked === true)" />
                                    </TableCell>
                                    <TableCell class="sticky z-10 bg-white dark:bg-gray-950" :class="canSyncGrades ? 'left-10' : 'left-0'">
                                        <div class="space-y-1">
                                            <div class="font-medium">
                                                <Link :href="route('students.academic-summary.scores', { student: student.id })" class="text-primary hover:underline">
                                                    {{ student.full_name }}
                                                </Link>
                                            </div>
                                            <div class="text-muted-foreground text-xs">{{ student.student_id }}</div>
                                        </div>
                                    </TableCell>
                                    <template v-for="component in scoresData.assessment_components" :key="`student-${student.student_id}-component-${component.id}`">
                                        <template v-if="component.type !== 'attendance'">
                                            <TableCell v-for="detail in detailsByComponent[component.id] || []" :key="`score-${student.student_id}-${detail.id}`" class="text-center">
                                                <div class="flex flex-col items-center gap-1">
                                                    <Badge :variant="getScoreBadgeVariant(student.scores.find((s) => s.component_detail_id === detail.id)!)">
                                                        {{ getScoreDisplay(student.scores.find((s) => s.component_detail_id === detail.id)!) }}
                                                    </Badge>
                                                </div>
                                            </TableCell>
                                        </template>
                                        <TableCell class="bg-blue-50 text-center dark:bg-blue-950/20">
                                            <Badge
                                                v-if="student.component_totals.find((ct) => ct.component_id === component.id)?.percentage_score !== null"
                                                :variant="getComponentTotalBadgeVariant(student.component_totals.find((ct) => ct.component_id === component.id)!)"
                                                class="text-base font-bold"
                                            >
                                                {{ student.component_totals.find((ct) => ct.component_id === component.id)!.percentage_score!.toFixed(1) }}
                                            </Badge>
                                            <Badge v-else variant="outline" class="text-base">-</Badge>
                                        </TableCell>
                                        <TableCell v-if="hasScheme" class="bg-purple-50 text-center dark:bg-purple-950/20">
                                            <template v-for="(cd, cdIdx) in [findComponentDisplay(student, component.code)]" :key="cdIdx">
                                                <div v-if="cd" class="space-y-1 text-xs">
                                                    <div class="font-semibold">
                                                        {{ cd.converted_grade ?? '-' }} <span class="text-muted-foreground font-normal">({{ cd.raw_percentage ?? '-' }}%)</span>
                                                    </div>
                                                    <Badge v-if="cd.requirement_status" :variant="cd.requirement_status === 'passed' ? 'success' : 'destructive'" class="text-[10px]">
                                                        {{ cd.requirement_status === 'passed' ? 'Met' : 'Not met' }}
                                                    </Badge>
                                                </div>
                                                <span v-else class="text-muted-foreground text-xs">-</span>
                                            </template>
                                        </TableCell>
                                    </template>
                                    <TableCell class="sticky z-10 bg-white text-center dark:bg-gray-950" :style="{ right: statusColRightPx + 'px' }">
                                        <Badge :variant="getGradeStatusVariant(student.grade_status)" class="text-xs">{{ getGradeStatusLabel(student.grade_status) }}</Badge>
                                    </TableCell>
                                    <TableCell v-if="hasScheme" class="sticky z-10 bg-white text-center dark:bg-gray-950" :style="{ right: schemeGradeRightPx + 'px' }">
                                        <div v-if="student.grade_display" class="space-y-1">
                                            <Badge :variant="student.grade_display.pass_status === 'passed' ? 'success' : 'destructive'" class="text-base font-bold">
                                                {{ student.grade_display.final_label || student.grade_display.final_numeric }}
                                            </Badge>
                                            <div class="text-muted-foreground text-[11px] capitalize">{{ student.grade_display.pass_status }}</div>
                                        </div>
                                        <span v-else class="text-muted-foreground text-xs">Not finalized</span>
                                    </TableCell>
                                    <TableCell v-if="showTotalColumn" class="sticky right-0 z-10 bg-white text-center dark:bg-gray-950">
                                        <div class="space-y-1">
                                            <Badge v-if="student.total_percentage !== null" :variant="getTotalBadgeVariant(student.total_percentage)" class="text-base font-bold">
                                                {{ Number(student.total_percentage).toFixed(1) }}
                                            </Badge>
                                            <Badge v-else variant="outline" class="text-base">Not Graded</Badge>
                                            <div v-if="student.total_letter_grade" class="text-sm font-semibold">({{ student.total_letter_grade }})</div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Assessment Components Summary -->
            <Card>
                <CardHeader><CardTitle>Assessment Components</CardTitle></CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="component in scoresData.assessment_components" :key="component.id" class="rounded-lg border p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold">{{ component.name }}</h3>
                                    <Badge variant="outline">{{ getComponentTypeLabel(component.type) }}</Badge>
                                </div>
                                <div class="text-primary text-sm font-semibold">Weight: {{ component.weight }}%</div>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div v-for="detail in component.details" :key="detail.id" class="text-muted-foreground flex items-center justify-between">
                                    <span>• {{ detail.name }}</span>
                                    <span>{{ detail.weight }}% ({{ detail.max_points || 'N/A' }} pts)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </template>

        <CanvasSyncPreviewDialog v-if="canSyncGrades" v-model:open="isSyncDialogOpen" :course-offering-id="courseOffering.id" :student-ids="selectedStudentIds" @synced="onSynced" />

        <RecalculatePreviewDialog v-if="canRecalculate" v-model:open="isRecalculateDialogOpen" :course-offering-id="courseOffering.id" :has-mapped-canvas-course="hasMappedCanvasCourse" :students="rosterStudents" @applied="onRecalculateApplied" />
    </div>
</template>
