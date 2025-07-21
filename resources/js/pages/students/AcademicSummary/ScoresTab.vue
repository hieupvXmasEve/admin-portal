<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { CourseScores, ScoresData } from '@/types/models';
import { BarChart3, BookOpen, ChevronDown, ChevronRight, Eye, Filter, Search, Target, TrendingUp, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    scores: ScoresData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

// Reactive filters
const searchQuery = ref('');
const selectedSemester = ref<string>('');
const selectedCourse = ref<string>('');
const expandedCourses = ref<Set<number>>(new Set());
const selectedScoreDetails = ref<CourseScores | null>(null);
const showScoreDialog = ref(false);

// Computed filtered data
const filteredScores = computed(() => {
    let filtered = props.scores.data;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(
            (course) =>
                course.course_name.toLowerCase().includes(query) ||
                course.course_code.toLowerCase().includes(query) ||
                course.semester.toLowerCase().includes(query),
        );
    }

    // Semester filter
    if (selectedSemester.value) {
        filtered = filtered.filter((course) => course.semester === selectedSemester.value);
    }

    // Course filter
    if (selectedCourse.value) {
        filtered = filtered.filter((course) => course.course_code === selectedCourse.value);
    }

    return filtered;
});

// Toggle course expansion
const toggleCourseExpansion = (courseId: number) => {
    if (expandedCourses.value.has(courseId)) {
        expandedCourses.value.delete(courseId);
    } else {
        expandedCourses.value.add(courseId);
    }
};

// Score badge styling
const getScoreBadgeVariant = (percentage: number) => {
    if (percentage >= 90) return 'default';
    if (percentage >= 80) return 'secondary';
    if (percentage >= 70) return 'outline';
    return 'destructive';
};

const getScoreColor = (percentage: number) => {
    if (percentage >= 90) return 'text-green-600';
    if (percentage >= 80) return 'text-blue-600';
    if (percentage >= 70) return 'text-yellow-600';
    return 'text-red-600';
};

const getStatusBadgeVariant = (status: string) => {
    const variants: Record<string, string> = {
        graded: 'default',
        submitted: 'secondary',
        pending: 'outline',
        missing: 'destructive',
    };
    return variants[status] || 'outline';
};

const formatDate = (date: string | undefined) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatPercentage = (value: number) => {
    if (!value) return 'N/A';
    return `${Number(value).toFixed(1)}%`;
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedSemester.value = '';
    selectedCourse.value = '';
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedSemester.value || selectedCourse.value;
});

// Get unique semesters for filter dropdown
const availableSemesters = computed(() => {
    const semesters = new Set(props.scores.data.map((course) => course.semester));
    return Array.from(semesters).sort();
});

// Get unique courses for filter dropdown
const availableCourses = computed(() => {
    const courses = new Set(props.scores.data.map((course) => course.course_code));
    return Array.from(courses).sort();
});

const openScoreDetails = (course: CourseScores) => {
    console.log('ok', course);

    selectedScoreDetails.value = course;
    showScoreDialog.value = true;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-4">
            <div class="animate-pulse">
                <div class="mb-4 h-8 w-1/3 rounded bg-gray-200"></div>
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div v-for="i in 4" :key="i" class="h-24 rounded bg-gray-200"></div>
                </div>
                <div class="space-y-4">
                    <div v-for="i in 3" :key="i" class="h-32 rounded bg-gray-200"></div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Summary Cards -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Total Courses</p>
                                <p class="text-2xl font-bold">{{ scores.summary.total_courses }}</p>
                            </div>
                            <BookOpen class="h-8 w-8 text-blue-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Total Assessments</p>
                                <p class="text-2xl font-bold">{{ scores.summary.total_assessments }}</p>
                            </div>
                            <Target class="h-8 w-8 text-purple-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Completed</p>
                                <p class="text-2xl font-bold text-green-600">{{ scores.summary.completed_assessments }}</p>
                            </div>
                            <TrendingUp class="h-8 w-8 text-green-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Overall Average</p>
                                <p class="text-2xl font-bold" :class="getScoreColor(scores.summary.overall_average)">
                                    {{ formatPercentage(scores.summary.overall_average) }}
                                </p>
                            </div>
                            <BarChart3 class="h-8 w-8 text-orange-600" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Filters
                        </span>
                        <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearFilters" class="flex items-center gap-2">
                            <X class="h-4 w-4" />
                            Clear
                        </Button>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <!-- Search -->
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                            <Input v-model="searchQuery" placeholder="Search courses..." class="pl-10" />
                        </div>

                        <!-- Semester Filter -->
                        <Select v-model="selectedSemester">
                            <SelectTrigger>
                                <SelectValue placeholder="All Semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Semesters</SelectItem>
                                <SelectItem v-for="semester in availableSemesters" :key="semester" :value="semester">
                                    {{ semester }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Course Filter -->
                        <Select v-model="selectedCourse">
                            <SelectTrigger>
                                <SelectValue placeholder="All Courses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Courses</SelectItem>
                                <SelectItem v-for="course in availableCourses" :key="course" :value="course">
                                    {{ course }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Results Count -->
                        <div class="text-muted-foreground flex items-center text-sm">
                            {{ filteredScores.length }} of {{ scores.data.length }} courses
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Scores List -->
            <div class="space-y-4">
                <div v-if="filteredScores.length === 0" class="py-12 text-center">
                    <Target class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                    <h3 class="mb-2 text-lg font-semibold">No Scores Found</h3>
                    <p class="text-muted-foreground">No assessment scores match your current filters.</p>
                </div>

                <Card v-for="course in filteredScores" :key="course.course_offering_id">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <CardTitle class="flex items-center gap-3">
                                    <BookOpen class="h-5 w-5" />
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ course.course_name }}</h3>
                                        <p class="text-muted-foreground text-sm font-normal">{{ course.course_code }} • {{ course.semester }}</p>
                                    </div>
                                </CardTitle>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <p class="text-muted-foreground text-sm">Course Average</p>
                                    <p class="text-xl font-bold" :class="getScoreColor(course.course_average)">
                                        {{ formatPercentage(course.course_average) }}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="toggleCourseExpansion(course.course_offering_id)"
                                    class="flex items-center gap-2"
                                >
                                    <ChevronDown v-if="expandedCourses.has(course.course_offering_id)" class="h-4 w-4" />
                                    <ChevronRight v-else class="h-4 w-4" />
                                    {{ expandedCourses.has(course.course_offering_id) ? 'Collapse' : 'Expand' }}
                                </Button>
                                <Button variant="outline" size="sm" @click="openScoreDetails(course)" class="flex items-center gap-2">
                                    <Eye class="h-4 w-4" />
                                    Details
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <Collapsible :open="expandedCourses.has(course.course_offering_id)">
                        <CollapsibleContent>
                            <CardContent>
                                <div class="space-y-4">
                                    <!-- Course Statistics -->
                                    <div class="bg-muted grid grid-cols-1 gap-4 rounded-lg p-4 md:grid-cols-3">
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Total Assessments</p>
                                            <p class="text-xl font-bold">{{ course.total_assessments }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Completed</p>
                                            <p class="text-xl font-bold text-green-600">{{ course.completed_assessments }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Completion Rate</p>
                                            <p class="text-xl font-bold">
                                                {{ Math.round((course.completed_assessments / course.total_assessments) * 100) }}%
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div>
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-sm font-medium">Assessment Progress</span>
                                            <span class="text-muted-foreground text-sm">
                                                {{ course.completed_assessments }}/{{ course.total_assessments }}
                                            </span>
                                        </div>
                                        <Progress :value="(course.completed_assessments / course.total_assessments) * 100" class="h-2" />
                                    </div>

                                    <!-- Recent Assessments -->
                                    <div>
                                        <h4 class="mb-3 font-semibold">Recent Assessments</h4>
                                        <div class="space-y-2">
                                            <div
                                                v-for="score in course.scores.slice(0, 5)"
                                                :key="score.id"
                                                class="flex items-center justify-between rounded-lg border p-3"
                                            >
                                                <div class="flex-1">
                                                    <p class="font-medium">{{ score.assessment_name }}</p>
                                                    <p class="text-muted-foreground text-sm">
                                                        {{ score.assessment_type }}
                                                        <span v-if="score.is_late" class="ml-2 text-red-600">(Late)</span>
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <div class="text-right">
                                                        <p class="font-medium">{{ score.points_earned }}/{{ score.max_points }}</p>
                                                        <p class="text-sm" :class="getScoreColor(score.percentage_score)">
                                                            {{ formatPercentage(score.percentage_score) }}
                                                        </p>
                                                    </div>
                                                    <Badge :variant="getStatusBadgeVariant(score.status) as any">
                                                        {{ score.status.toUpperCase() }}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="course.scores.length > 5" class="mt-3 text-center">
                                            <Button variant="outline" size="sm" @click="openScoreDetails(course)" class="flex items-center gap-2">
                                                <Eye class="h-4 w-4" />
                                                View All {{ course.scores.length }} Assessments
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>
            </div>
        </div>

        <!-- Score Details Dialog -->
        <Dialog v-model:open="showScoreDialog">
            <DialogContent class="max-h-[80vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle v-if="selectedScoreDetails">{{ selectedScoreDetails.course_name }} - Detailed Scores</DialogTitle>
                </DialogHeader>
                <div v-if="selectedScoreDetails" class="space-y-4">
                    <!-- Course Info -->
                    <div class="bg-muted grid grid-cols-2 gap-4 rounded-lg p-4">
                        <div>
                            <p class="text-muted-foreground text-sm">Course Code</p>
                            <p class="font-medium">{{ selectedScoreDetails.course_code }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Semester</p>
                            <p class="font-medium">{{ selectedScoreDetails.semester }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Total Assessments</p>
                            <p class="font-medium">{{ selectedScoreDetails.total_assessments }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Course Average</p>
                            <p class="text-lg font-medium" :class="getScoreColor(selectedScoreDetails.course_average)">
                                {{ formatPercentage(selectedScoreDetails.course_average) }}
                            </p>
                        </div>
                    </div>

                    <!-- Assessment Scores -->
                    <div class="space-y-3">
                        <h4 class="font-semibold">Assessment Scores</h4>
                        <div class="space-y-2">
                            <div
                                v-for="score in selectedScoreDetails.scores"
                                :key="score.id"
                                class="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">{{ score.assessment_name }}</p>
                                    <p class="text-muted-foreground text-sm">
                                        {{ score.assessment_type }} • Due: {{ formatDate(score.due_date) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <p class="font-medium">{{ score.points_earned }}/{{ score.max_points }}</p>
                                        <p class="text-sm" :class="getScoreColor(score.percentage_score)">
                                            {{ formatPercentage(score.percentage_score) }}
                                        </p>
                                    </div>
                                    <Badge :variant="getStatusBadgeVariant(score.status) as any">
                                        {{ score.status.toUpperCase() }}
                                    </Badge>
                                    <Badge
                                        v-if="score.letter_grade"
                                        :variant="getScoreBadgeVariant(score.percentage_score) as any"
                                    >
                                        {{ score.letter_grade }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
