<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import {
    Card, CardHeader, CardTitle, CardContent
} from '@/components/ui/card';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Filter, Trophy, Award, TrendingUp } from 'lucide-vue-next';
import { studentRoutes } from '@/utils/routes';

interface StudentRanking {
    id: number;
    student_id: string;
    full_name: string;
    final_percentage: number;
    attendance_percentage: number | null;
}

interface CourseRanking {
    unit: {
        id: number;
        code: string;
        name: string;
    };
    students: StudentRanking[];
}

interface RankingData {
    semester_id: number;
    courses: CourseRanking[];
}

const props = defineProps<{
    ranking: RankingData | null;
    filters: {
        active: {
            semester_id: number | null;
        };
        options: {
            semesters: { id: number; name: string }[];
        };
    };
}>();

const {
    filters,
    handleSelectFilter,
} = useInertiaFilters({
    baseUrl: route('academic.course-ranking.index'),
    initialFilters: {
        semester_id: props.filters.active.semester_id,
    },
    defaultValues: {},
    only: ['ranking', 'filters'],
});

const courses = computed(() => props.ranking?.courses || []);

const getRankBadgeVariant = (rank: number) => {
    if (rank === 1) return 'default';
    if (rank <= 3) return 'secondary';
    return 'outline';
};

const getRankIcon = (rank: number) => {
    if (rank === 1) return Trophy;
    if (rank <= 3) return Award;
    return null;
};

const getScoreColor = (score: number) => {
    if (score >= 90) return 'text-green-600 bg-green-50';
    if (score >= 80) return 'text-blue-600 bg-blue-50';
    if (score >= 70) return 'text-indigo-600 bg-indigo-50';
    if (score >= 60) return 'text-amber-600 bg-amber-50';
    return 'text-rose-600 bg-rose-50';
};

const getAttendanceColor = (attendance: number | null) => {
    if (attendance === null) return 'text-slate-400 bg-slate-50';
    if (attendance >= 80) return 'text-green-600 bg-green-50';
    if (attendance >= 60) return 'text-amber-600 bg-amber-50';
    return 'text-rose-600 bg-rose-50';
};
</script>

<template>
    <Head title="Course Ranking" />

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Course Ranking</h1>
            <p class="text-slate-500 mt-1">Top 10 students per course ranked by total score and attendance.</p>
        </div>
    </div>

    <Card class="border-none overflow-hidden bg-white/80 backdrop-blur-sm">
        <CardHeader class="bg-slate-50/50 border-b border-slate-100">
            <CardTitle class="text-lg font-semibold flex items-center gap-2 text-slate-800">
                <Filter class="w-5 h-5 text-indigo-500" />
                Filters
            </CardTitle>
        </CardHeader>
        <CardContent class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Semester</label>
                    <Select :model-value="String(filters.semester_id || '')"
                        @update:model-value="v => handleSelectFilter('semester_id', v)">
                        <SelectTrigger class="bg-white border-slate-200 focus:ring-indigo-500">
                            <SelectValue placeholder="Select Semester" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in props.filters.options.semesters" :key="s.id" :value="String(s.id)">
                                {{ s.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </CardContent>
    </Card>

    <div v-if="courses.length > 0" class="space-y-6 mt-6">
        <Card v-for="course in courses" :key="course.unit.id" class="border-none overflow-hidden bg-white">
            <CardHeader class="bg-gradient-to-r from-indigo-50 to-blue-50 border-b border-slate-100">
                <CardTitle class="text-xl font-bold text-slate-800 flex items-center gap-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <TrendingUp class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div>
                        <div class="text-lg">{{ course.unit.name }}</div>
                        <div class="text-sm font-normal text-slate-600 mt-1">{{ course.unit.code }}</div>
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50/80">
                            <TableRow>
                                <TableHead class="w-16 text-center font-bold text-slate-700">Rank</TableHead>
                                <TableHead class="font-bold text-slate-700">Student Name</TableHead>
                                <TableHead class="font-bold text-slate-700">Student ID</TableHead>
                                <TableHead class="text-center font-bold text-slate-700">Total Score</TableHead>
                                <TableHead class="text-center font-bold text-slate-700">Attendance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(student, index) in course.students" :key="student.student_id"
                                class="hover:bg-slate-50/50 transition-colors cursor-pointer"
                                @click="router.visit(studentRoutes.hub.scores(student.id))">
                                <TableCell class="text-center">
                                    <div class="flex items-center justify-center">
                                        <Badge :variant="getRankBadgeVariant(index + 1)" class="font-bold">
                                            <component v-if="getRankIcon(index + 1)" :is="getRankIcon(index + 1)"
                                                class="w-3 h-3 mr-1" />
                                            {{ index + 1 }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="font-semibold text-slate-800">
                                    {{ student.full_name }}
                                </TableCell>
                                <TableCell class="text-slate-600">
                                    {{ student.student_id }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge :class="getScoreColor(student.final_percentage)" class="font-semibold">
                                        {{ student.final_percentage.toFixed(2) }}%
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge v-if="student.attendance_percentage !== null"
                                        :class="getAttendanceColor(student.attendance_percentage)" class="font-semibold">
                                        {{ student.attendance_percentage.toFixed(2) }}%
                                    </Badge>
                                    <span v-else class="text-slate-400 text-sm">-</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>

    <Card v-else class="border-none overflow-hidden bg-white">
        <CardContent class="p-12">
            <div class="flex flex-col items-center justify-center text-center">
                <div class="bg-indigo-50 p-6 rounded-full mb-4">
                    <Trophy class="w-10 h-10 text-indigo-300" />
                </div>
                <h3 class="text-lg font-semibold text-slate-800 mb-2">No ranking data available</h3>
                <p class="text-slate-500 max-w-xs mx-auto">
                    Please select a semester to view course rankings.
                </p>
            </div>
        </CardContent>
    </Card>
</template>

