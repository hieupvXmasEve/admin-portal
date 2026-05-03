<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import HeadlessToastWithProps from '@/components/ui/sonner/HeadlessToastWithProps.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import type { CourseOffering } from '@/types/models';
import { formatSubmissionTypes } from '@/utils/canvasGradeFormatter';
import { Link, router } from '@inertiajs/vue3';
import { BarChart3, BookOpen, Calculator, Calendar, User, Users } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
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
}

interface AssessmentComponent {
    id: number;
    name: string;
    type: string;
    weight: number;
    details: Array<{ id: number; name: string; weight: number; max_points: number | null }>;
}

interface Statistics {
    course_code: string;
    course_name: string;
    section_code: string;
    semester: string;
    instructor_name: string | null;
    total_students: number;
    total_components: number;
    total_details: number;
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
    };
}

interface Props {
    courseOffering: CourseOffering;
    scoresData?: ScoresData;
}

const props = defineProps<Props>();
const api = useApi();
const { showConfirmDialog } = useGlobalConfirmDialog();
const isRecalculating = ref(false);

const statusFilter = ref<string>('all');

const filteredScoresGrid = computed(() => {
    if (!props.scoresData) return [];
    if (statusFilter.value === 'all') return props.scoresData.scores_grid;
    const threshold = props.scoresData.course_offering.min_grade_threshold;
    if (statusFilter.value === 'pass') return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage >= threshold);
    if (statusFilter.value === 'fail') return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage < threshold);
    return props.scoresData.scores_grid;
});

const passCount = computed(() => {
    if (!props.scoresData) return 0;
    const threshold = props.scoresData.course_offering.min_grade_threshold;
    return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage >= threshold).length;
});

const failCount = computed(() => {
    if (!props.scoresData) return 0;
    const threshold = props.scoresData.course_offering.min_grade_threshold;
    return props.scoresData.scores_grid.filter((s) => s.total_percentage !== null && s.total_percentage < threshold).length;
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

// ---- Recalculate action ----
const recalculateCourseResult = () => {
    showConfirmDialog(
        {
            title: 'Recalculate Course Results',
            message: `Are you sure you want to recalculate results for "${props.courseOffering.course_code}"? This will update student grades and only send notifications to students whose pass/fail status has changed.`,
            confirmText: 'Recalculate',
        },
        {
            onConfirm: async () => {
                isRecalculating.value = true;
                try {
                    const { data: apiData } = await api.post(`/api/course-offerings/${props.courseOffering.id}/recalculate`, {});
                    if (apiData.value?.success) {
                        const message = apiData.value.data?.message || 'Course results recalculated successfully';
                        toast.success('Course results recalculated successfully', {
                            description: h(HeadlessToastWithProps, { message }),
                        });
                        router.reload({ only: ['scoresData'] });
                    } else {
                        const errorMessage = apiData.value?.message || 'Failed to recalculate course results';
                        toast.error('Failed to recalculate course results', {
                            description: h(HeadlessToastWithProps, { message: errorMessage }),
                        });
                    }
                } catch {
                    toast.error('Failed to recalculate course results');
                } finally {
                    isRecalculating.value = false;
                }
            },
        },
    );
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
            <!-- Recalculate button (completed courses only) -->
            <div v-if="courseOffering.course_status === 'completed'" class="flex justify-end">
                <Button variant="outline" :disabled="isRecalculating" @click="recalculateCourseResult">
                    <Calculator class="mr-2 h-4 w-4" />
                    {{ isRecalculating ? 'Recalculating...' : 'Recalculate Course Result' }}
                </Button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <Calendar class="h-8 w-8 text-blue-600" />
                            <div>
                                <p class="text-muted-foreground text-sm">Semester</p>
                                <p class="text-lg font-bold">{{ scoresData.statistics.semester }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <User class="h-8 w-8 text-purple-600" />
                            <div>
                                <p class="text-muted-foreground text-sm">Instructor</p>
                                <p class="text-lg font-bold">{{ scoresData.statistics.instructor_name || 'N/A' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <Users class="h-8 w-8 text-green-600" />
                            <div>
                                <p class="text-muted-foreground text-sm">Total Students</p>
                                <p class="text-lg font-bold">{{ scoresData.statistics.total_students }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <BookOpen class="h-8 w-8 text-orange-600" />
                            <div>
                                <p class="text-muted-foreground text-sm">Components</p>
                                <p class="text-lg font-bold">{{ scoresData.statistics.total_components }}</p>
                                <p class="text-muted-foreground text-xs">{{ scoresData.statistics.total_details }} details</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <BarChart3 class="h-8 w-8 text-indigo-600" />
                            <div>
                                <p class="text-muted-foreground text-sm">Average Score</p>
                                <p class="text-lg font-bold" :class="[scoresData.statistics.average_score >= 80 ? 'text-green-600' : scoresData.statistics.average_score >= 60 ? 'text-yellow-600' : 'text-red-600']">
                                    {{ scoresData.statistics.average_score.toFixed(1) }}%
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Legend -->
            <Card>
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

            <!-- Filters -->
            <Card>
                <CardHeader><CardTitle>Filters</CardTitle></CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="space-y-2">
                            <Label
                                >Student Status — <span class="text-sm text-yellow-500">Min Grade Threshold: {{ scoresData.course_offering.min_grade_threshold }}</span></Label
                            >
                            <Select v-model="statusFilter">
                                <SelectTrigger>
                                    <SelectValue placeholder="All students" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Students ({{ scoresData.scores_grid.length }})</SelectItem>
                                    <SelectItem value="pass">Pass ({{ passCount }})</SelectItem>
                                    <SelectItem value="fail">Fail ({{ failCount }})</SelectItem>
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
                                    <TableHead class="sticky left-0 z-10 min-w-[100px] bg-white dark:bg-gray-950">Student</TableHead>
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
                                    </template>
                                    <TableHead class="sticky right-[120px] z-10 min-w-[100px] bg-white text-center dark:bg-gray-950">Status</TableHead>
                                    <TableHead class="sticky right-0 z-10 min-w-[120px] bg-white text-center dark:bg-gray-950">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="student in filteredScoresGrid" :key="student.student_id">
                                    <TableCell class="sticky left-0 z-10 bg-white dark:bg-gray-950">
                                        <div class="space-y-1">
                                            <div class="font-medium">
                                                <Link :href="`/students/${student.id}/academic-summary/scores`" class="text-primary hover:underline">
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
                                    </template>
                                    <TableCell class="sticky right-[120px] z-10 bg-white text-center dark:bg-gray-950">
                                        <Badge :variant="getGradeStatusVariant(student.grade_status)" class="text-xs">{{ getGradeStatusLabel(student.grade_status) }}</Badge>
                                    </TableCell>
                                    <TableCell class="sticky right-0 z-10 bg-white text-center dark:bg-gray-950">
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
    </div>
</template>
