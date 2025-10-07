<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermission } from '@/composables/usePermission';
import { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { h, reactive } from 'vue';
import { route } from 'ziggy-js';
import { Edit, Eye } from 'lucide-vue-next';

interface CurriculumVersion {
    id: number;
    name: string;
    program: {
        id: number;
        name: string;
    };
    version_code: string;
}

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface TuitionPlan {
    id: number;
    curriculum_version_id: number;
    intake_semester_id: number;
    total_amount: number;
    currency: string;
    is_active: boolean;
    terms_count: number;
    curriculum_version: CurriculumVersion;
    intake_semester: Semester;
}

interface Props {
    tuitionPlans: PaginatedResponse<TuitionPlan>;
    curriculumVersions: CurriculumVersion[];
    semesters: Semester[];
    filters: {
        search?: string;
        curriculum_version_id?: number | null;
        intake_semester_id?: number | null;
        status?: string;
    };
}

const props = defineProps<Props>();
const { can } = usePermission();

const searchForm = reactive({
    search: props.filters.search || '',
    curriculum_version_id: props.filters.curriculum_version_id || null,
    intake_semester_id: props.filters.intake_semester_id || null,
    status: props.filters.status || 'all',
});

const applyFilters = () => {
    const params = new URLSearchParams();

    if (searchForm.search) params.set('search', searchForm.search);
    if (searchForm.curriculum_version_id) params.set('curriculum_version_id', searchForm.curriculum_version_id.toString());
    if (searchForm.intake_semester_id) params.set('intake_semester_id', searchForm.intake_semester_id.toString());
    if (searchForm.status && searchForm.status !== 'all') params.set('status', searchForm.status);

    const url = `/tuition-plans${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['tuitionPlans', 'filters'],
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['tuitionPlans', 'filters'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', pageSize.toString());
    params.delete('page'); // Reset to first page when changing page size

    const url = `/tuition-plans?${params.toString()}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['tuitionPlans', 'filters'],
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const formatCurrency = (amount: number, currency: string = 'VND') => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: currency,
    }).format(amount);
};

const columns: ColumnDef<TuitionPlan>[] = [
    {
        accessorKey: 'curriculum_version',
        header: 'Program',
        cell: ({ row }) => {
            const cv = row.original.curriculum_version;
            return `${cv.program.name}`;
        },
    },
    {
        accessorKey: 'intake_semester',
        header: 'Intake Semester',
        cell: ({ row }) => row.original.intake_semester.name,
    },
    {
        accessorKey: 'total_amount',
        header: 'Total Amount',
        cell: ({ row }) => formatCurrency(row.original.total_amount, row.original.currency),
    },
    {
        accessorKey: 'terms_count',
        header: 'Terms',
        cell: ({ row }) => `${row.original.terms_count} term(s)`,
    },
    {
        accessorKey: 'is_active',
        header: 'Status',
        cell: ({ row }) => {
            const isActive = row.original.is_active;
            return h(
                Badge,
                {
                    variant: isActive ? 'default' : 'secondary',
                },
                () => (isActive ? 'Active' : 'Inactive'),
            );
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            return h('div', { class: 'flex gap-2' }, [
                h(
                    Link,
                    {
                        href: route('tuition-plans.show', row.original.id),
                        class: 'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 hover:bg-accent hover:text-accent-foreground h-9 w-9',
                    },
                    () => h(Eye, { class: 'h-4 w-4' }),
                ),
                h(
                    Link,
                    {
                        href: route('tuition-plans.edit', row.original.id),
                        class: 'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 hover:bg-accent hover:text-accent-foreground h-9 w-9',
                    },
                    () => h(Edit, { class: 'h-4 w-4' }),
                ),
            ]);
        },
    },
];
</script>

<template>
    <Head title="Tuition Plans" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Tuition Plans</h1>
                <p class="text-muted-foreground">Manage tuition plans for different curriculum versions and intake periods</p>
            </div>
            <Link v-if="can('create_tuition_plan')" :href="route('tuition-plans.create')">
                <Button>Create Tuition Plan</Button>
            </Link>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-2">
                        <Label for="search">Search</Label>
                        <Input id="search" v-model="searchForm.search" placeholder="Search programs..." @input="debouncedSearch" />
                    </div>

                    <div class="space-y-2">
                        <Label for="curriculum">Curriculum Version</Label>
                        <Select v-model="searchForm.curriculum_version_id" @update:model-value="applyFilters">
                            <SelectTrigger id="curriculum">
                                <SelectValue placeholder="All Curricula" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">All Curricula</SelectItem>
                                <SelectItem v-for="cv in curriculumVersions" :key="cv.id" :value="cv.id"> {{ cv.program.name }} - {{ cv.version_code }} </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="semester">Intake Semester</Label>
                        <Select v-model="searchForm.intake_semester_id" @update:model-value="applyFilters">
                            <SelectTrigger id="semester">
                                <SelectValue placeholder="All Semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">All Semesters</SelectItem>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="searchForm.status" @update:model-value="applyFilters">
                            <SelectTrigger id="status">
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <DataTable :columns="columns" :data="tuitionPlans.data" />
                <div class="mt-4">
                    <DataPagination
                        :pagination-data="tuitionPlans"
                        @navigate="handlePaginationNavigate"
                        @page-size-change="handlePageSizeChange"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
