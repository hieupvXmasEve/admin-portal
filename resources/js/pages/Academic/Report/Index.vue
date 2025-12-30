<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import {
    Card, CardHeader, CardTitle, CardDescription, CardContent
} from '@/components/ui/card';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow
} from '@/components/ui/table';
import { Loader2, Download, Search, Filter, FileSpreadsheet, FileText } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import DataPagination from '@/components/DataPagination.vue';

interface FilterOptions {
    campuses: { id: number; name: string }[];
    semesters: { id: number; name: string }[];
    programs: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
}

interface AcademicReport {
    units: any[];
    data: any[];
    pagination: any;
}

const props = defineProps<{
    report: AcademicReport | null;
    filters: {
        active: {
            campus_id: number | null;
            semester_id: number | null;
            program_id: number | null;
            status: string;
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
    handlePageSizeChange
} = useInertiaFilters({
    baseUrl: route('academic.report.index'),
    initialFilters: {
        campus_id: props.filters.active.campus_id,
        semester_id: props.filters.active.semester_id,
        program_id: props.filters.active.program_id,
        status: props.filters.active.status,
        keyword: props.filters.active.keyword,
        per_page: props.filters.active.per_page,
    },
    defaultValues: {
        status: 'all',
        per_page: 15,
    },
    only: ['report', 'filters'],
});

const reportData = computed(() => props.report?.data || []);
const units = computed(() => props.report?.units || []);
const pagination = computed(() => props.report?.pagination || null);
const isLoading = computed(() => false);

const handleExport = (format: 'xlsx' | 'csv') => {
    if (!filters.semester_id) {
        toast.error('Please select a semester first');
        return;
    }

    const params = new URLSearchParams({
        campus_id: filters.campus_id === 'all' || !filters.campus_id ? '' : String(filters.campus_id),
        semester_id: String(filters.semester_id),
        program_id: filters.program_id === 'all' || !filters.program_id ? '' : String(filters.program_id),
        status: filters.status === 'all' ? '' : filters.status,
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
</script>

<template>

    <Head title="Academic Report" />

    <div class="space-y-6 container mx-auto py-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Academic Report</h1>
                <p class="text-slate-500 mt-1">Cross-tabulated view of student performance per course.</p>
            </div>
            <div class="flex items-center gap-3">
                <Button variant="outline" class="bg-white border-slate-200 hover:bg-slate-50 transition-all shadow-sm"
                    @click="handleExport('csv')" :disabled="!filters.semester_id || isLoading">
                    <FileText class="w-4 h-4 mr-2 text-slate-500" />
                    Export CSV
                </Button>
                <Button class="bg-indigo-600 hover:bg-indigo-700 transition-all shadow-md text-white"
                    @click="handleExport('xlsx')" :disabled="!filters.semester_id || isLoading">
                    <FileSpreadsheet class="w-4 h-4 mr-2" />
                    Export Excel
                </Button>
            </div>
        </div>

        <Card class="border-none shadow-xl overflow-hidden bg-white/80 backdrop-blur-sm">
            <CardHeader class="bg-slate-50/50 border-b border-slate-100">
                <CardTitle class="text-lg font-semibold flex items-center gap-2 text-slate-800">
                    <Filter class="w-5 h-5 text-indigo-500" />
                    Filters
                </CardTitle>
            </CardHeader>
            <CardContent class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Semester</label>
                        <Select :model-value="String(filters.semester_id)"
                            @update:model-value="v => handleSelectFilter('semester_id', v)">
                            <SelectTrigger class="bg-white border-slate-200 focus:ring-indigo-500">
                                <SelectValue placeholder="Select Semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in props.filters.options.semesters" :key="s.id"
                                    :value="String(s.id)">{{ s.name
                                    }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Campus</label>
                        <Select :model-value="String(filters.campus_id || 'all')"
                            @update:model-value="v => handleSelectFilter('campus_id', v)">
                            <SelectTrigger class="bg-white border-slate-200">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Campuses</SelectItem>
                                <SelectItem v-for="c in props.filters.options.campuses" :key="c.id"
                                    :value="String(c.id)">{{ c.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Program</label>
                        <Select :model-value="String(filters.program_id || 'all')"
                            @update:model-value="v => handleSelectFilter('program_id', v)">
                            <SelectTrigger class="bg-white border-slate-200">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Programs</SelectItem>
                                <SelectItem v-for="p in props.filters.options.programs" :key="p.id"
                                    :value="String(p.id)">{{ p.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status</label>
                        <Select :model-value="filters.status"
                            @update:model-value="v => handleSelectFilter('status', v)">
                            <SelectTrigger class="bg-white border-slate-200">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem v-for="s in props.filters.options.statuses" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Search</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <Input v-model="filters.keyword" placeholder="ID / Name..."
                                class="pl-9 bg-white border-slate-200" @keyup.enter="handleSearch" />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="border-none shadow-xl overflow-hidden bg-white">
            <CardContent class="p-0">
                <div v-if="reportData.length > 0" class="relative group">
                    <div class="overflow-x-auto custom-scrollbar">
                        <Table class="min-w-full border-collapse">
                            <TableHeader class="bg-slate-50/80 sticky top-0 z-10 backdrop-blur-md">
                                <TableRow>
                                    <TableHead rowspan="2"
                                        class="border-r border-slate-200 min-w-[200px] font-bold text-slate-800 bg-slate-50/90 shadow-[1px_0_0_0_#e2e8f0]">
                                        Full Name</TableHead>
                                    <TableHead rowspan="2"
                                        class="border-r border-slate-200 min-w-[120px] font-bold text-slate-800 bg-slate-50/90 shadow-[1px_0_0_0_#e2e8f0]">
                                        Student ID</TableHead>
                                    <TableHead v-for="unit in units" :key="unit.id" colspan="2"
                                        class="border-r border-b border-slate-200 text-center font-bold text-indigo-700 px-4 py-3 bg-indigo-50/30">
                                        <div class="truncate max-w-[180px]" :title="unit.name">{{ unit.name }}</div>
                                    </TableHead>
                                    <TableHead rowspan="2"
                                        class="border-r border-slate-200 text-center font-bold text-slate-800 min-w-[150px]">
                                        Sum of % attendance</TableHead>
                                    <TableHead rowspan="2" class="text-center font-bold text-slate-800 min-w-[120px]">
                                        Sum of GPA</TableHead>
                                </TableRow>
                                <TableRow>
                                    <template v-for="unit in units" :key="unit.id">
                                        <TableHead
                                            class="border-r border-b border-slate-200 text-center text-[10px] uppercase font-bold text-slate-400 py-1 bg-indigo-50/10">
                                            attendance</TableHead>
                                        <TableHead
                                            class="border-r border-b border-slate-200 text-center text-[10px] uppercase font-bold text-slate-400 py-1 bg-indigo-50/10">
                                            total score</TableHead>
                                    </template>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="student in reportData" :key="student.student_id"
                                    class="hover:bg-slate-50/80 transition-all border-b border-slate-100 group">
                                    <TableCell
                                        class="border-r border-slate-100 font-semibold text-slate-700 whitespace-nowrap bg-white/50 group-hover:bg-indigo-50/10">
                                        {{ student.full_name }}</TableCell>
                                    <TableCell class="border-r border-slate-100 text-slate-600 whitespace-nowrap">{{
                                        student.student_id }}
                                    </TableCell>
                                    <template v-for="unit in units" :key="unit.id">
                                        <TableCell
                                            class="border-r border-slate-100 text-center transition-colors px-2 py-4">
                                            <span v-if="student.course_results[unit.id]?.attendance !== null"
                                                class="inline-flex items-center px-2 py-1 rounded-md text-xs font-semibold"
                                                :class="student.course_results[unit.id].attendance < 80 ? 'bg-rose-50 text-rose-600' : 'bg-green-50 text-green-600'">
                                                {{ student.course_results[unit.id].attendance }}%
                                            </span>
                                            <span v-else class="text-slate-300 font-light">-</span>
                                        </TableCell>
                                        <TableCell
                                            class="border-r border-slate-100 text-center transition-colors px-2 py-4">
                                            <span v-if="student.course_results[unit.id]?.score !== null"
                                                class="inline-flex items-center px-2 py-1 rounded-md text-xs font-semibold"
                                                :class="student.course_results[unit.id].score < 50 ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'">
                                                {{ student.course_results[unit.id].score }}%
                                            </span>
                                            <span v-else class="text-slate-300 font-light">-</span>
                                        </TableCell>
                                    </template>
                                    <TableCell class="border-r border-slate-100 text-center py-4">
                                        <div class="font-bold text-indigo-600">
                                            {{ student.avg_attendance !== null ? student.avg_attendance + '%' : '-' }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center py-4">
                                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-full font-bold shadow-inner"
                                            :class="student.cumulative_gpa && student.cumulative_gpa < 2 ? 'bg-rose-50 text-rose-600' : 'bg-green-50 text-green-700'">
                                            {{ student.cumulative_gpa !== null ?
                                                Number(student.cumulative_gpa).toFixed(2) : '-' }}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div v-if="pagination" class="p-4 border-t border-slate-100 bg-slate-50/30">
                        <DataPagination :pagination-data="pagination" item-name="students"
                            @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-32 text-center">
                    <div class="bg-indigo-50 p-6 rounded-full mb-4">
                        <Search class="w-10 h-10 text-indigo-300" />
                    </div>
                    <h3 class="text-lg font-semibold text-slate-800">No records found</h3>
                    <p class="text-slate-500 max-w-xs mx-auto">Try adjusting your filters or search term to find what
                        you're looking
                        for.</p>
                    <Button variant="outline" class="mt-6 border-slate-200" @click="onClearFilters">Clear
                        Filters</Button>
                </div>
            </CardContent>
        </Card>
    </div>
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
