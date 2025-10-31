<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { PaginatedResponse } from '@/types';
import type { Campus } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Eye, Plus, Trash2 } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Module {
    id: number;
    campus_id: number;
    campus: {
        id: number;
        name: string;
        code: string;
    };
    code: string;
    name: string;
    description: string | null;
    grading_type: 'grade' | 'pass_fail';
    total_credits: number;
    prerequisite_module_id: number | null;
    prerequisite_module: {
        id: number;
        code: string;
        name: string;
    } | null;
    units_count: number;
    created_at: string;
    updated_at: string;
}

interface ModuleFilters {
    search: string;
    campus_id: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
}

interface Props {
    modules: PaginatedResponse<Module>;
    filters: ModuleFilters;
    campuses: Campus[];
}

const props = defineProps<Props>();

const data = computed(() => props.modules.data);

const deleteDialogOpen = ref(false);
const selectedModule = ref<Module | null>(null);
const deleteForm = useForm({});

const { filters, applyFilters } = useInertiaFilters<ModuleFilters>({
    initialFilters: props.filters,
    baseUrl: '/admin/modules',
    only: ['modules', 'filters'],
    debounce: 400,
});

const openDeleteModal = (module: Module) => {
    selectedModule.value = module;
    deleteDialogOpen.value = true;
};

const closeDeleteModal = () => {
    deleteDialogOpen.value = false;
    selectedModule.value = null;
};

const submitDelete = () => {
    if (!selectedModule.value) return;

    deleteForm.delete(route('admin.modules.destroy', selectedModule.value.id), {
        onSuccess: () => {
            closeDeleteModal();
            toast.success('Module deleted successfully');
        },
        onError: (err: any) => {
            closeDeleteModal();
            toast.error('Failed to delete module', {
                description: err.message || 'An unexpected error occurred.',
            });
        },
    });
};

const goToCreatePage = () => {
    router.visit(route('admin.modules.create'));
};

const goToEditPage = (module: Module) => {
    router.visit(route('admin.modules.edit', module.id));
};

const goToViewPage = (module: Module) => {
    router.visit(route('admin.modules.show', module.id));
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['modules'],
    });
};

const columns: ColumnDef<Module>[] = [
    {
        accessorKey: 'code',
        header: 'Module Code',
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.original.code),
    },
    {
        accessorKey: 'name',
        header: 'Module Name',
        cell: ({ row }) => h('div', { class: 'max-w-md truncate' }, row.original.name),
    },
    {
        accessorKey: 'campus',
        header: 'Campus',
        cell: ({ row }) => h('div', { class: 'text-sm' }, row.original.campus?.name || 'N/A'),
    },
    {
        accessorKey: 'total_credits',
        header: 'Credits',
        cell: ({ row }) => h('div', { class: 'text-sm' }, row.original.total_credits.toString()),
    },
    {
        accessorKey: 'grading_type',
        header: 'Grading Type',
        cell: ({ row }) => h(Badge, { variant: 'outline' }, () => (row.original.grading_type === 'grade' ? 'Graded' : 'Pass/Fail')),
    },
    {
        accessorKey: 'units_count',
        header: 'Units',
        cell: ({ row }) => h('div', { class: 'text-sm text-center' }, row.original.units_count?.toString() || '0'),
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) =>
            h('div', { class: 'flex items-center gap-2' }, [
                h(TooltipProvider, {}, () =>
                    h(
                        Tooltip,
                        {},
                        {
                            default: () => [
                                h(TooltipTrigger, { asChild: true }, () =>
                                    h(
                                        Button,
                                        {
                                            variant: 'ghost',
                                            size: 'sm',
                                            onClick: () => goToViewPage(row.original),
                                        },
                                        () => h(Eye, { class: 'h-4 w-4' }),
                                    ),
                                ),
                                h(TooltipContent, {}, () => 'View'),
                            ],
                        },
                    ),
                ),
                h(TooltipProvider, {}, () =>
                    h(
                        Tooltip,
                        {},
                        {
                            default: () => [
                                h(TooltipTrigger, { asChild: true }, () =>
                                    h(
                                        Button,
                                        {
                                            variant: 'ghost',
                                            size: 'sm',
                                            onClick: () => goToEditPage(row.original),
                                        },
                                        () => h(Edit, { class: 'h-4 w-4' }),
                                    ),
                                ),
                                h(TooltipContent, {}, () => 'Edit'),
                            ],
                        },
                    ),
                ),
                h(TooltipProvider, {}, () =>
                    h(
                        Tooltip,
                        {},
                        {
                            default: () => [
                                h(TooltipTrigger, { asChild: true }, () =>
                                    h(
                                        Button,
                                        {
                                            variant: 'ghost',
                                            size: 'sm',
                                            onClick: () => openDeleteModal(row.original),
                                        },
                                        () => h(Trash2, { class: 'h-4 w-4 text-destructive' }),
                                    ),
                                ),
                                h(TooltipContent, {}, () => 'Delete'),
                            ],
                        },
                    ),
                ),
            ]),
    },
];
</script>

<template>
    <Head title="Modules" />

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Modules</h1>
                <p class="text-muted-foreground mt-1">Manage academic modules and their structure</p>
            </div>

            <Button @click="goToCreatePage">
                <Plus class="mr-2 h-4 w-4" />
                Create Module
            </Button>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <DebouncedInput :model-value="filters.search" placeholder="Search modules..." />
            </div>

            <Select :model-value="filters.campus_id || 'all'">
                <SelectTrigger class="w-[200px]">
                    <SelectValue placeholder="All Campuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Campuses</SelectItem>
                    <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                        {{ campus.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Data Table -->
        <DataTable
            :data="data"
            :columns="columns"
            :initial-sort="filters.sort || undefined"
            :initial-direction="filters.direction || undefined"
            @sort-change="
                (sort, direction) => {
                    applyFilters({ ...filters, sort, direction, page: 1 });
                }
            "
        />

        <!-- Pagination -->
        <DataPagination :pagination-data="modules" item-name="modules" @navigate="handlePaginationNavigate" />

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="deleteDialogOpen" @update:open="closeDeleteModal">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Module</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete the module <strong>{{ selectedModule?.code }}</strong> - <strong>{{ selectedModule?.name }}</strong
                        >? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="submitDelete" class="bg-destructive text-destructive-foreground"> Delete </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
