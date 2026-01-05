<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { Head } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Download, Loader2, Search, X } from 'lucide-vue-next';
import { h, ref } from 'vue';

interface GpaRecord {
    id: number;
    semester_gpa: string;
    cumulative_gpa: string;
    academic_standing: string;
    cumulative_credit_points_earned: string;
    student: {
        id: number;
        full_name: string;
        student_id: string;
    };
    program: {
        id: number;
        name: string;
        code: string;
    };
    semester: {
        id: number;
        name: string;
        code: string;
    };
}

interface Props {
    gpaRecords: {
        data: GpaRecord[];
        meta: any;
        links: any;
    };
    filters: {
        campus_id: number | null;
        semester_id: number | null;
        program_id: number | null;
        academic_standing: string | null;
        search: string | null;
        per_page: number;
    };
    options: {
        campuses: { id: number; name: string }[];
        semesters: { id: number; name: string }[];
        programs: { id: number; name: string }[];
        standings: { value: string; label: string }[];
    };
}

const props = defineProps<Props>();

const { filters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, clearFilters } = useInertiaFilters({
    baseUrl: route('academic.gpa.history'),
    initialFilters: props.filters,
    defaultValues: {
        campus_id: props.filters.campus_id, // Keep current campus
        semester_id: null,
        program_id: null,
        academic_standing: null,
        search: '',
        per_page: 15,
    },
    only: ['gpaRecords', 'filters'],
});

const isExporting = ref(false);

const handleExport = () => {
    isExporting.value = true;
    const url = route('academic.gpa.history.export', {
        ...filters,
        campus_id: filters.campus_id, // Ensure campus is included
    });
    window.location.href = url;
    // Reset loading state after a short delay since we can't detect when download starts precisely
    setTimeout(() => {
        isExporting.value = false;
    }, 2000);
};

const columns: ColumnDef<GpaRecord>[] = [
    {
        accessorKey: 'student.student_id',
        header: 'Student Id',
    },
    {
        accessorKey: 'student.full_name',
        header: 'Name',
    },
    {
        accessorKey: 'program.name',
        header: 'Program',
        cell: ({ row }) => h('span', { class: 'truncate max-w-[200px] block' }, row.original.program?.name),
    },
    {
        accessorKey: 'semester.name',
        header: 'Semester',
    },
    {
        accessorKey: 'semester_gpa',
        header: 'Sem GPA',
        cell: ({ row }) => h('span', { class: 'font-medium' }, parseFloat(row.original.semester_gpa).toFixed(2)),
    },
    {
        accessorKey: 'cumulative_gpa',
        header: 'Cum GPA',
        cell: ({ row }) => h('span', { class: 'font-bold' }, parseFloat(row.original.cumulative_gpa).toFixed(2)),
    },
    {
        accessorKey: 'academic_standing',
        header: 'Standing',
        cell: ({ row }) => {
            const standing = row.original.academic_standing;
            // const variant = standing === 'normal' ? 'secondary' : standing === 'warning' ? 'warning' : 'destructive';
            // Determine badge variant mapping or use standard
            const badgeVariant = standing === 'normal' ? 'secondary' : 'destructive';

            return h(Badge, { variant: badgeVariant, class: 'capitalize' }, () => standing);
        },
    },
    {
        accessorKey: 'cumulative_credit_points_earned',
        header: 'Credits Earned',
        cell: ({ row }) => parseFloat(row.original.cumulative_credit_points_earned).toFixed(1),
    },
];
</script>

<template>
    <Head title="GPA History" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">GPA History</h1>
                <p class="text-muted-foreground mt-1">View and export finalized GPA records for all semesters.</p>
            </div>
            <Button variant="outline" @click="handleExport" :disabled="isExporting">
                <Loader2 v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
                <Download v-else class="mr-2 h-4 w-4" />
                Export CSV
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Records</CardTitle>
                <CardDescription> List of all finalized GPA records. Use filters to narrow down the results. </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- Filters -->
                <div class="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                        <Input placeholder="Search student..." class="pl-8" :model-value="filters.search" @update:model-value="handleSearch" />
                    </div>

                    <Select :model-value="filters.semester_id ? String(filters.semester_id) : 'all'" @update:model-value="(v) => (filters.semester_id = v === 'all' ? null : Number(v))">
                        <SelectTrigger>
                            <SelectValue placeholder="Semester" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="String(sem.id)">
                                {{ sem.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="filters.program_id ? String(filters.program_id) : 'all'" @update:model-value="(v) => (filters.program_id = v === 'all' ? null : Number(v))">
                        <SelectTrigger>
                            <SelectValue placeholder="Program" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Programs</SelectItem>
                            <SelectItem v-for="prog in options.programs" :key="prog.id" :value="String(prog.id)">
                                {{ prog.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="filters.academic_standing ?? 'all'" @update:model-value="(v) => (filters.academic_standing = v === 'all' ? null : v)">
                        <SelectTrigger>
                            <SelectValue placeholder="Academic Standing" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Standings</SelectItem>
                            <SelectItem v-for="standing in options.standings" :key="standing.value" :value="standing.value">
                                {{ standing.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="filters.semester_id || filters.program_id || filters.academic_standing || filters.search" class="mb-4 flex items-center gap-2">
                    <Button variant="ghost" size="sm" class="h-8 px-2 lg:px-3" @click="clearFilters">
                        Reset Filters
                        <X class="ml-2 h-4 w-4" />
                    </Button>
                </div>

                <DataTable :data="gpaRecords.data" :columns="columns" :loading="false" empty-message="No GPA records found matching your filters." />

                <div class="mt-4">
                    <DataPagination :pagination-data="gpaRecords" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
