<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import type { Lecture } from '@/types/models';
import { lecturerRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { Building2, Edit, Eye, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    lectures: PaginatedResponse<Lecture>;
    filters: {
        search?: string;
        campus_id?: string;
        employment_status?: string;
        employment_type?: string;
        available_for_assignment?: boolean;
    };
    employmentStatusOptions: Array<{ value: string; label: string }>;
    employmentTypeOptions: Array<{ value: string; label: string }>;
}

const props = defineProps<Props>();

const data = computed(() => props.lectures.data);

const deleteDialogOpen = ref(false);
const selectedLecture = ref<Lecture | null>(null);

// Form for filters
const filtersForm = useForm({
    search: props.filters.search || '',
    campus_id: props.filters.campus_id || 'all',
    employment_status: props.filters.employment_status || 'all',
    employment_type: props.filters.employment_type || 'all',
    available_for_assignment: props.filters.available_for_assignment,
});

const deleteForm = useForm({});

const openDeleteModal = (lecture: Lecture) => {
    selectedLecture.value = lecture;
    deleteDialogOpen.value = true;
};

const closeDeleteModal = () => {
    deleteDialogOpen.value = false;
    selectedLecture.value = null;
};

const submitDelete = () => {
    if (!selectedLecture.value) return;

    deleteForm.delete(route('lectures.destroy', selectedLecture.value.id), {
        onSuccess: () => {
            closeDeleteModal();
            toast.success('Lecturer deleted successfully');
        },
        onError: (err: any) => {
            closeDeleteModal();
            const errorMessage = err.message || Object.values(err)?.[0] || 'An unexpected error occurred.';
            toast.error('Failed to delete lecturer', {
                description: errorMessage,
            });
        },
    });
};

const goToCreatePage = () => {
    router.visit(lecturerRoutes.create());
};

const goToImportPage = () => {
    router.visit(lecturerRoutes.import());
};

const exportToExcel = () => {
    window.open('/lectures/export/excel', '_blank');
};

const exportFilteredToExcel = () => {
    // Build query parameters from current filters
    const params = new URLSearchParams();

    if (filtersForm.search) params.append('search', filtersForm.search);
    if (filtersForm.campus_id && filtersForm.campus_id !== 'all') params.append('campus_id', filtersForm.campus_id);
    if (filtersForm.employment_status && filtersForm.employment_status !== 'all') params.append('employment_status', filtersForm.employment_status);
    if (filtersForm.employment_type && filtersForm.employment_type !== 'all') params.append('employment_type', filtersForm.employment_type);
    if (filtersForm.available_for_assignment !== undefined) params.append('available_for_assignment', filtersForm.available_for_assignment.toString());

    const queryString = params.toString();
    const url = `/lectures/export/excel/filtered${queryString ? '?' + queryString : ''}`;
    window.open(url, '_blank');
};

const goToEditPage = (lecture: Lecture) => {
    router.visit(lecturerRoutes.edit(lecture.id));
};

const goToViewPage = (lecture: Lecture) => {
    router.visit(lecturerRoutes.show(lecture.id));
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['lectures'],
    });
};

// Debounced search function
const debouncedSearch = useDebounceFn(() => {
    filtersForm.get(route('lectures.index'), {
        preserveState: true,
        preserveScroll: true,
        only: ['lectures'],
    });
}, 300);

// Watch for filter changes
watch(
    () => filtersForm.search,
    () => {
        debouncedSearch();
    },
);

watch([() => filtersForm.campus_id, () => filtersForm.employment_status, () => filtersForm.employment_type, () => filtersForm.available_for_assignment], () => {
    filtersForm.get(route('lectures.index'), {
        preserveState: true,
        preserveScroll: true,
        only: ['lectures'],
    });
});

const getEmploymentStatusBadge = (status: string) => {
    const statusMap: Record<string, { variant: any; label: string }> = {
        active: { variant: 'default', label: 'Active' },
        on_leave: { variant: 'secondary', label: 'On Leave' },
        sabbatical: { variant: 'outline', label: 'Sabbatical' },
        retired: { variant: 'secondary', label: 'Retired' },
        terminated: { variant: 'destructive', label: 'Terminated' },
        suspended: { variant: 'destructive', label: 'Suspended' },
    };
    return statusMap[status] || { variant: 'secondary', label: 'Unknown' };
};

const getEmploymentTypeBadge = (type: string) => {
    const typeMap: Record<string, { variant: any; label: string }> = {
        full_time: { variant: 'default', label: 'Full Time' },
        part_time: { variant: 'secondary', label: 'Part Time' },
        contract: { variant: 'outline', label: 'Contract' },
        visiting: { variant: 'secondary', label: 'Visiting' },
        emeritus: { variant: 'outline', label: 'Emeritus' },
    };
    return typeMap[type] || { variant: 'secondary', label: 'Unknown' };
};

const getAcademicRankBadge = (rank: string) => {
    const rankMap: Record<string, { variant: any; label: string }> = {
        lecturer: { variant: 'default', label: 'Lecturer' },
        senior_lecturer: { variant: 'default', label: 'Senior Lecturer' },
        associate_professor: { variant: 'secondary', label: 'Associate Professor' },
        professor: { variant: 'secondary', label: 'Professor' },
        emeritus_professor: { variant: 'outline', label: 'Emeritus Professor' },
        visiting_lecturer: { variant: 'outline', label: 'Visiting Lecturer' },
        adjunct_professor: { variant: 'outline', label: 'Adjunct Professor' },
    };
    return rankMap[rank] || { variant: 'secondary', label: 'Unknown' };
};

// Column definitions
const columns: ColumnDef<Lecture>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.lectures.current_page;
            const perPage = props.lectures.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Employee ID',
        accessorKey: 'employee_id',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-mono font-medium text-sm' }, row.original.employee_id),
    },
    {
        header: 'Name',
        accessorKey: 'full_name',
        enableSorting: true,
        cell: ({ row }) => {
            const lecture = row.original;
            return h('div', { class: 'min-w-0' }, [h('div', { class: 'font-medium text-sm truncate' }, lecture.display_name), h('div', { class: 'text-xs text-gray-500 dark:text-gray-400 truncate' }, lecture.email)]);
        },
    },
    {
        header: 'Academic Rank',
        accessorKey: 'academic_rank',
        enableSorting: true,
        cell: ({ row }) => {
            const rankInfo = getAcademicRankBadge(row.original.academic_rank);
            return h(Badge, { variant: rankInfo.variant, class: 'text-xs' }, () => rankInfo.label);
        },
    },
    {
        header: 'Campus',
        accessorKey: 'campus.name',
        enableSorting: false,
        cell: ({ row }) => {
            const campus = row.original.campus;
            return h('div', { class: 'flex items-center gap-1 text-sm' }, campus ? [h(Building2, { class: 'h-3 w-3' }), campus.name] : 'No Campus');
        },
    },

    {
        header: 'Status',
        accessorKey: 'employment_status',
        enableSorting: true,
        cell: ({ row }) => {
            const statusInfo = getEmploymentStatusBadge(row.original.employment_status);
            return h(Badge, { variant: statusInfo.variant, class: 'text-xs' }, () => statusInfo.label);
        },
    },
    {
        header: 'Type',
        accessorKey: 'employment_type',
        enableSorting: true,
        cell: ({ row }) => {
            const typeInfo = getEmploymentTypeBadge(row.original.employment_type);
            return h(Badge, { variant: typeInfo.variant, class: 'text-xs' }, () => typeInfo.label);
        },
    },
    {
        header: 'Available',
        accessorKey: 'is_available_for_assignment',
        enableSorting: true,
        cell: ({ row }) => {
            const available = row.original.is_available_for_assignment;
            return h(Badge, { variant: available ? 'default' : 'secondary', class: 'text-xs' }, () => (available ? 'Yes' : 'No'));
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: 'actions',
    },
];
</script>

<template>
    <Head title="Lecturers" />
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Lecturers</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage all lecturer information and assignments.</p>
        </div>
        <div class="flex items-center gap-2">
            <!-- Import/Export Dropdown -->
            <div class="relative">
                <select
                    class="bg-background border-input ring-offset-background focus:ring-ring appearance-none rounded-md border px-3 py-2 text-sm focus:ring-2 focus:ring-offset-2"
                    @change="
                        (e) => {
                            const value = (e.target as HTMLSelectElement).value;
                            if (value === 'import') goToImportPage();
                            else if (value === 'export-all') exportToExcel();
                            else if (value === 'export-filtered') exportFilteredToExcel();
                            (e.target as HTMLSelectElement).value = '';
                        }
                    "
                >
                    <option value="">Import/Export</option>
                    <option value="import">📤 Import Lecturers</option>
                    <option value="export-all">📋 Export All</option>
                    <option value="export-filtered">🔍 Export Filtered</option>
                </select>
            </div>

            <Button size="sm" @click="goToCreatePage">
                <Plus class="mr-2 h-4 w-4" />
                Add Lecturer
            </Button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mt-6 space-y-4">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-2">
                <Label for="search">Search</Label>
                <div class="relative">
                    <Search class="absolute top-2.5 left-2 h-4 w-4 text-gray-500" />
                    <Input id="search" v-model="filtersForm.search" placeholder="Search by name, email, employee ID..." class="pl-8" />
                </div>
            </div>

            <div class="space-y-2">
                <Label for="status">Employment Status</Label>
                <Select v-model="filtersForm.employment_status">
                    <SelectTrigger>
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem v-for="status in employmentStatusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-2">
                <Label for="type">Employment Type</Label>
                <Select v-model="filtersForm.employment_type">
                    <SelectTrigger>
                        <SelectValue placeholder="All Types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <SelectItem v-for="type in employmentTypeOptions" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <DataTable :data="data" :columns="columns">
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="goToViewPage(row.original)">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View Lecturer</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="goToEditPage(row.original)">
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit Lecturer</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="openDeleteModal(row.original)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Delete Lecturer</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="lectures" @navigate="handlePaginationNavigate" />

    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Lecturer</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete the lecturer
                    <strong>{{ selectedLecture?.display_name }}</strong>
                    ? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeDeleteModal">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="submitDelete" :disabled="deleteForm.processing" class="bg-red-600 hover:bg-red-700">
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete Lecturer' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
