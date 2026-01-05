<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { AlertTriangle, Award, BookOpen, TrendingUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Doughnut, Line } from 'vue-chartjs';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend);

interface DashboardStats {
    overview: {
        avg_semester_gpa: number;
        avg_cumulative_gpa: number;
        total_students: number;
        at_risk_count: number;
        avg_completion_rate: number;
    };
    distribution: {
        name: string;
        value: number;
        color: string;
    }[];
    trend: {
        semester: string;
        gpa: number;
    }[];
    top_students: {
        id: number;
        name: string;
        student_id: string;
        gpa: string;
        program: string;
    }[];
    at_risk_list: {
        id: number;
        name: string;
        student_id: string;
        gpa: string;
        standing: string;
    }[];
}

interface Props {
    stats: DashboardStats;
    filters: {
        campus_id: number;
        semester_id: number;
    };
    options: {
        campuses: { id: number; name: string }[];
        semesters: { id: number; name: string }[];
    };
}

const props = defineProps<Props>();

const selectedCampus = ref(String(props.filters.campus_id));
const selectedSemester = ref(String(props.filters.semester_id));
const isLoading = ref(false);

const updateDashboard = () => {
    isLoading.value = true;
    router.get(
        route('academic.students.performance'),
        {
            campus_id: selectedCampus.value,
            semester_id: selectedSemester.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['stats', 'filters'],
            onFinish: () => (isLoading.value = false),
        },
    );
};

watch([selectedCampus, selectedSemester], () => {
    updateDashboard();
});

// Chart Data Configuration
const distributionData = {
    labels: props.stats.distribution.map((d) => d.name),
    datasets: [
        {
            backgroundColor: props.stats.distribution.map((d) => d.color),
            data: props.stats.distribution.map((d) => d.value),
        },
    ],
};

const distributionOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right' as const,
        },
    },
};

const trendData = {
    labels: props.stats.trend.map((t) => t.semester),
    datasets: [
        {
            label: 'Average GPA',
            backgroundColor: '#3b82f6',
            borderColor: '#3b82f6',
            data: props.stats.trend.map((t) => t.gpa),
            tension: 0.3,
        },
    ],
};

const trendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            min: 0,
            max: 4,
        },
    },
};
</script>

<template>
    <Head title="Academic Performance Dashboard" />

    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Academic Performance</h1>
                <p class="text-muted-foreground mt-1">Overview of student performance metrics and trends.</p>
            </div>
            <div class="flex gap-2">
                <Select v-model="selectedCampus">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Select Campus" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="campus in options.campuses" :key="campus.id" :value="String(campus.id)">
                            {{ campus.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="selectedSemester">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Select Semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="String(sem.id)">
                            {{ sem.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Overview Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Avg Semester GPA</CardTitle>
                    <TrendingUp class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.overview.avg_semester_gpa.toFixed(2) }}</div>
                    <p class="text-muted-foreground text-xs">Based on {{ stats.overview.total_students }} finalized records</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Avg Cumulative GPA</CardTitle>
                    <Award class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.overview.avg_cumulative_gpa.toFixed(2) }}</div>
                    <p class="text-muted-foreground text-xs">Overall performance</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">At-Risk Students</CardTitle>
                    <AlertTriangle class="text-destructive h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.overview.at_risk_count }}</div>
                    <p class="text-muted-foreground text-xs">Warning or Probation standing</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Completion Rate</CardTitle>
                    <BookOpen class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.overview.avg_completion_rate }}%</div>
                    <p class="text-muted-foreground text-xs">Avg credits earned / attempted</p>
                </CardContent>
            </Card>
        </div>

        <!-- Charts -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
            <Card class="col-span-4">
                <CardHeader>
                    <CardTitle>GPA Trend</CardTitle>
                    <CardDescription>Average Semester GPA over the last 5 semesters.</CardDescription>
                </CardHeader>
                <CardContent class="pl-2">
                    <div class="h-[300px]">
                        <Line :data="trendData" :options="trendOptions" />
                    </div>
                </CardContent>
            </Card>
            <Card class="col-span-3">
                <CardHeader>
                    <CardTitle>Academic Standing</CardTitle>
                    <CardDescription>Distribution of student standings.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[300px]">
                        <Doughnut :data="distributionData" :options="distributionOptions" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Lists -->
        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Top Performing Students</CardTitle>
                    <CardDescription>Highest Semester GPA.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Program</TableHead>
                                <TableHead class="text-right">GPA</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="student in stats.top_students" :key="student.id">
                                <TableCell>
                                    <div class="font-medium">{{ student.name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ student.student_id }}</div>
                                </TableCell>
                                <TableCell>{{ student.program }}</TableCell>
                                <TableCell class="text-right font-bold">{{ parseFloat(student.gpa).toFixed(2) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Students At Risk</CardTitle>
                    <CardDescription>Lowest Cumulative GPA or Warning Status.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Standing</TableHead>
                                <TableHead class="text-right">Cum GPA</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="student in stats.at_risk_list" :key="student.id">
                                <TableCell>
                                    <div class="font-medium">{{ student.name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ student.student_id }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="student.standing === 'probation' ? 'destructive' : 'warning'" class="capitalize">
                                        {{ student.standing }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right font-bold text-red-600">{{ parseFloat(student.gpa).toFixed(2) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div class="mt-4 flex justify-end">
                        <Button variant="link" as-child class="px-0">
                            <a :href="route('academic.gpa.history', { academic_standing: 'warning' })"> View all at-risk students </a>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
