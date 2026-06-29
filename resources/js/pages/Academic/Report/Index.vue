<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import BarChart from '@/components/ui/chart/BarChart.vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { FileSpreadsheet, FileText, Filter, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

interface FilterOptions {
    campuses: { id: number; name: string }[];
    semesters: { id: number; name: string }[];
    programs: { id: number; name: string }[];
}

interface AcademicReport {
    units: any[];
    data: any[];
    pagination: any;
    stats: {
        grade_distribution: Record<string, number>;
        total_grades: number;
    };
}

const props = defineProps<{
    report: AcademicReport | null;
    filters: {
        active: {
            campus_id: string | null;
            semester_id: number | null;
            program_id: string | null;
            keyword: string;
            per_page: number;
        };
        options: FilterOptions;
    };
}>();

const {
    filters,
    handleSelectFilter,
    handleSearch: handleSearchInput,
    handlePaginationNavigate,
    handlePageSizeChange,
} = useInertiaFilters({
    baseUrl: route('academic.report.index'),
    initialFilters: {
        semester_id: props.filters.active.semester_id,
        program_id: props.filters.active.program_id,
        keyword: props.filters.active.keyword,
        per_page: props.filters.active.per_page,
    },
    defaultValues: {
        per_page: 15,
    },
    only: ['report', 'filters'],
});

const reportData = computed(() => props.report?.data || []);
const units = computed(() => props.report?.units || []);
const pagination = computed(() => props.report?.pagination || null);
const stats = computed(() => props.report?.stats || null);
const isLoading = computed(() => false);

const chartData = computed(() => {
    if (!stats.value) return { labels: [], datasets: [] };

    const dist = stats.value.grade_distribution;
    const labels = Object.keys(dist);
    const data = Object.values(dist);

    return {
        labels,
        datasets: [
            {
                label: 'Grade Distribution',
                backgroundColor: [
                    '#10b981', // A+
                    '#34d399', // A
                    '#6ee7b7', // A-
                    '#3b82f6', // B+
                    '#60a5fa', // B
                    '#93c5fd', // B-
                    '#f59e0b', // C+
                    '#fbbf24', // C
                    '#fcd34d', // C-
                    '#ef4444', // F
                ],
                data,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                stepSize: 1,
            },
        },
    },
};

const handleExport = (format: 'xlsx' | 'csv') => {
    if (!filters.semester_id) {
        toast.error('Please select a semester first');
        return;
    }

    const params = new URLSearchParams({
        semester_id: String(filters.semester_id),
        program_id: filters.program_id === 'all' || !filters.program_id ? '' : String(filters.program_id),
        keyword: filters.keyword,
        export: format,
    });

    window.open(`${route('api.admin.academic-reports.index')}?${params.toString()}`, '_blank');
};

const handleSearch = () => {
    handleSearchInput(filters.keyword);
};

const onClearFilters = () => {
    router.visit(route('academic.report.index'));
};

const barLabelPlugin = {
    id: 'barLabel',
    afterDatasetsDraw(chart: any) {
        const { ctx } = chart;

        chart.data.datasets.forEach((dataset: any, i: number) => {
            const meta = chart.getDatasetMeta(i);
            if (!meta.hidden) {
                meta.data.forEach((element: any, index: number) => {
                    const value = dataset.data[index];

                    if (value > 0) {
                        const { x, y, base } = element;
                        const barHeight = Math.abs(base - y); // Ensure positive height

                        const text = `${value.toLocaleString()}`;

                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.font = 'bold 11px sans-serif';

                        // If bar is tall enough (approx 20px), draw inside (white)
                        // Otherwise draw above (slate)
                        if (barHeight > 20) {
                            ctx.fillStyle = '#ffffff';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(text, x, y + barHeight / 2);
                        } else {
                            ctx.fillStyle = '#64748b'; // Slate-500
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(text, x, y - 5);
                        }

                        ctx.restore();
                    }
                });
            }
        });
    },
};

const barPlugins = [barLabelPlugin];
</script>

<template>
    <Head title="Academic Report" />

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Academic Report</h1>
            <p class="mt-1 text-slate-500">Cross-tabulated view of student performance per course.</p>
        </div>
        <div class="flex items-center gap-3">
            <Button variant="outline" class="border-slate-200 bg-white shadow-sm transition-all hover:bg-slate-50" @click="handleExport('csv')" :disabled="!filters.semester_id || isLoading">
                <FileText class="mr-2 h-4 w-4 text-slate-500" />
                Export CSV
            </Button>
            <Button class="bg-indigo-600 text-white shadow-md transition-all hover:bg-indigo-700" @click="handleExport('xlsx')" :disabled="!filters.semester_id || isLoading">
                <FileSpreadsheet class="mr-2 h-4 w-4" />
                Export Excel
            </Button>
        </div>
    </div>

    <Card class="overflow-hidden border-none bg-white/80 backdrop-blur-sm">
        <CardHeader class="border-b border-slate-100 bg-slate-50/50">
            <CardTitle class="flex items-center gap-2 text-lg font-semibold text-slate-800">
                <Filter class="h-5 w-5 text-indigo-500" />
                Filters
            </CardTitle>
        </CardHeader>
        <CardContent class="p-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-2">
                    <label class="text-xs font-bold tracking-wider text-slate-500 uppercase">Semester</label>
                    <Select :model-value="String(filters.semester_id)" @update:model-value="(v) => handleSelectFilter('semester_id', v)">
                        <SelectTrigger class="border-slate-200 bg-white focus:ring-indigo-500">
                            <SelectValue placeholder="Select Semester" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in props.filters.options.semesters" :key="s.id" :value="String(s.id)"> {{ s.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold tracking-wider text-slate-500 uppercase">Program</label>
                    <Select :model-value="String(filters.program_id || 'all')" @update:model-value="(v) => handleSelectFilter('program_id', v)">
                        <SelectTrigger class="border-slate-200 bg-white">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Programs</SelectItem>
                            <SelectItem v-for="p in props.filters.options.programs" :key="p.id" :value="String(p.id)">{{ p.name }} </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold tracking-wider text-slate-500 uppercase">Search</label>
                    <div class="relative">
                        <Search class="absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                        <Input v-model="filters.keyword" placeholder="ID / Name..." class="border-slate-200 bg-white pl-9" @keyup.enter="handleSearch" />
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <div v-if="stats && stats.total_grades > 0" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card class="border-none bg-white shadow-sm lg:col-span-2">
            <CardHeader class="border-b border-slate-50">
                <CardTitle class="text-sm font-bold tracking-wider text-slate-500 uppercase">Grade Distribution</CardTitle>
            </CardHeader>
            <CardContent class="p-6">
                <BarChart :data="chartData" :options="chartOptions" :plugins="barPlugins" height="250px" />
            </CardContent>
        </Card>

        <Card class="border-none bg-white shadow-sm">
            <CardHeader class="border-b border-slate-50">
                <CardTitle class="text-sm font-bold tracking-wider text-slate-500 uppercase">Grade Statistics</CardTitle>
            </CardHeader>
            <CardContent class="p-6">
                <div class="space-y-4">
                    <div v-for="(count, grade) in stats.grade_distribution" :key="grade" class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: chartData.datasets[0].backgroundColor[Object.keys(stats.grade_distribution).indexOf(grade)] }"></div>
                            <span class="text-sm font-medium text-slate-700">{{ grade }}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-xs font-semibold text-slate-500">{{ count }} students</span>
                            <span class="min-w-[45px] text-right text-sm font-bold text-indigo-600"> {{ ((count / stats.total_grades) * 100).toFixed(1) }}% </span>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex items-center justify-between border-t border-slate-50 pt-4 text-slate-400">
                    <span class="text-xs font-bold tracking-tight uppercase">Total Grades</span>
                    <span class="font-bold text-slate-900">{{ stats.total_grades }}</span>
                </div>
            </CardContent>
        </Card>
    </div>

    <Card class="overflow-hidden border-none bg-white p-0">
        <CardContent class="p-0">
            <div v-if="reportData.length > 0" class="group relative">
                <div class="custom-scrollbar overflow-x-auto">
                    <Table class="min-w-full border-collapse">
                        <TableHeader class="sticky top-0 z-10 bg-slate-50/80 backdrop-blur-md">
                            <TableRow>
                                <TableHead rowspan="2" class="sticky left-0 z-20 min-w-[200px] border-r border-slate-200 bg-slate-50 font-bold text-slate-800 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.1)]"> Full Name</TableHead>
                                <TableHead rowspan="2" class="sticky left-[200px] z-20 min-w-[120px] border-r border-slate-200 bg-slate-50 font-bold text-slate-800 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.1)]"> Student ID</TableHead>
                                <TableHead v-for="unit in units" :key="unit.id" colspan="2" class="border-r border-b border-slate-200 bg-indigo-50/30 px-4 py-3 text-center font-bold text-indigo-700">
                                    <div class="max-w-[180px] truncate" :title="unit.name">{{ unit.name }}</div>
                                </TableHead>
                                <TableHead rowspan="2" class="sticky right-[120px] z-20 min-w-[150px] border-r border-l border-slate-200 bg-slate-50 text-center font-bold text-slate-800 shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.1)]">
                                    Sum of % attendance</TableHead
                                >
                                <TableHead rowspan="2" class="sticky right-0 z-20 min-w-[120px] border-l border-slate-200 bg-slate-50 text-center font-bold text-slate-800 shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.1)]"> Sum of GPA</TableHead>
                            </TableRow>
                            <TableRow>
                                <template v-for="unit in units" :key="unit.id">
                                    <TableHead class="border-r border-b border-slate-200 bg-indigo-50/10 py-1 text-center text-[10px] font-bold text-slate-400 uppercase"> attendance</TableHead>
                                    <TableHead class="border-r border-b border-slate-200 bg-indigo-50/10 py-1 text-center text-[10px] font-bold text-slate-400 uppercase"> total score</TableHead>
                                </template>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="student in reportData" :key="student.student_id" class="group border-b border-slate-100 transition-all hover:bg-slate-50/80">
                                <TableCell class="sticky left-0 z-9 cursor-pointer border-r border-slate-100 bg-white font-semibold whitespace-nowrap text-blue-600 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] hover:underline" @click="router.visit(studentRoutes.hub.scores(student.id))"> {{ student.full_name }}</TableCell>
                                <TableCell class="sticky left-[200px] z-9 border-r border-slate-100 bg-white whitespace-nowrap text-slate-600 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">{{ student.student_id }} </TableCell>
                                <template v-for="unit in units" :key="unit.id">
                                    <TableCell class="border-r border-slate-100 px-2 py-4 text-center transition-colors">
                                        <span
                                            v-if="student.course_results[unit.id]?.attendance !== null"
                                            class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold"
                                            :class="student.course_results[unit.id].attendance < 80 ? 'bg-rose-50 text-rose-600' : 'bg-green-50 text-green-600'"
                                        >
                                            {{ student.course_results[unit.id].attendance }}%
                                        </span>
                                        <span v-else class="font-light text-slate-300">-</span>
                                    </TableCell>
                                    <TableCell class="border-r border-slate-100 px-2 py-4 text-center transition-colors">
                                        <div v-if="student.course_results[unit.id]?.score !== null" class="flex flex-col items-center gap-1">
                                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold" :class="student.course_results[unit.id].score < 50 ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'">
                                                {{ student.course_results[unit.id].score }}
                                            </span>
                                            <span
                                                v-if="student.course_results[unit.id].grade"
                                                class="text-[10px] font-bold"
                                                :class="{
                                                    'text-green-600': student.course_results[unit.id].grade.startsWith('A'),
                                                    'text-blue-600': student.course_results[unit.id].grade.startsWith('B'),
                                                    'text-amber-600': student.course_results[unit.id].grade.startsWith('C'),
                                                    'text-rose-600': student.course_results[unit.id].grade === 'F',
                                                }"
                                            >
                                                ({{ student.course_results[unit.id].grade }})
                                            </span>
                                        </div>
                                        <span v-else class="font-light text-slate-300">-</span>
                                    </TableCell>
                                </template>
                                <TableCell class="sticky right-[120px] z-9 border-r border-l border-slate-100 bg-white py-4 text-center shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                                    <div class="font-bold text-indigo-600">
                                        {{ student.avg_attendance !== null ? student.avg_attendance + '%' : '-' }}
                                    </div>
                                </TableCell>
                                <TableCell class="sticky right-0 z-10 border-l border-slate-100 bg-white py-4 text-center shadow-[-2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                                    <div
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full font-bold shadow-inner"
                                        :class="student.cumulative_gpa && student.cumulative_gpa < 2 ? 'bg-rose-50 text-rose-600' : 'bg-green-50 text-green-700'"
                                    >
                                        {{ student.cumulative_gpa !== null ? Number(student.cumulative_gpa).toFixed(2) : '-' }}
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="pagination" class="border-t border-slate-100 bg-slate-50/30 p-4">
                    <DataPagination :pagination-data="pagination" item-name="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-32 text-center">
                <div class="mb-4 rounded-full bg-indigo-50 p-6">
                    <Search class="h-10 w-10 text-indigo-300" />
                </div>
                <h3 class="text-lg font-semibold text-slate-800">No records found</h3>
                <p class="mx-auto max-w-xs text-slate-500">Try adjusting your filters or search term to find what you're looking for.</p>
                <Button variant="outline" class="mt-6 border-slate-200" @click="onClearFilters">Clear Filters</Button>
            </div>
        </CardContent>
    </Card>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Fix sticky header box-shadow in some browsers */
th.sticky {
    box-shadow: 0 1px 0 0 #e2e8f0;
}
</style>
