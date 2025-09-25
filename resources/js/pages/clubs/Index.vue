<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import type { Campus, Club } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Building, Calendar, Edit, Eye, Plus, Trash2, Users, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    clubs: PaginatedResponse<Club>;
    campuses: Campus[];
    filters?: {
        search?: string;
        campus_id?: number;
        status?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const data = computed(() => props.clubs.data);

const deleteDialogOpen = ref(false);
const selectedClub = ref<Club | null>(null);

const deleteForm = useForm({});

// Filter state
const filters = ref({
    search: props.filters?.search || '',
    campus_id: props.filters?.campus_id || '',
    status: props.filters?.status || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

const hasActiveFilters = computed(() => {
    return Object.entries(filters.value).some(([key, value]) => {
        if (key === 'per_page' || key === 'sort' || key === 'direction') return false;
        return value && value !== '';
    });
});

const openDeleteModal = (club: Club) => {
    selectedClub.value = club;
    deleteDialogOpen.value = true;
};

const closeDeleteModal = () => {
    deleteDialogOpen.value = false;
    selectedClub.value = null;
};

const submitDelete = () => {
    if (!selectedClub.value) return;

    deleteForm.delete(route('clubs.destroy', selectedClub.value.id), {
        onSuccess: () => {
            closeDeleteModal();
            toast.success('Club deleted successfully');
        },
        onError: (err: any) => {
            closeDeleteModal();
            toast.error('Failed to delete club', {
                description: err.message || 'An unexpected error occurred.',
            });
        },
    });
};

const goToCreatePage = () => {
    router.visit(systemRoutes.clubs.create());
};

const goToEditPage = (club: Club) => {
    router.visit(systemRoutes.clubs.edit(club.id));
};

const goToViewPage = (club: Club) => {
    router.visit(systemRoutes.clubs.show(club.id));
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['clubs'],
    });
};

// Filter handlers
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters();
};

const handleCampusFilter = (value: string) => {
    filters.value.campus_id = value;
    applyFilters();
};

const handleStatusFilter = (value: string) => {
    filters.value.status = value;
    applyFilters();
};

const handlePerPageChange = (value: string) => {
    filters.value.per_page = parseInt(value);
    applyFilters();
};

const resetFilters = () => {
    filters.value = {
        search: '',
        campus_id: '',
        status: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    applyFilters();
};

const applyFilters = () => {
    const cleanFilters = Object.fromEntries(
        Object.entries(filters.value).filter(([_, value]) => value !== '' && value !== 'all' && value !== null)
    );

    router.visit(systemRoutes.clubs.index(), {
        data: cleanFilters,
        preserveState: true,
        preserveScroll: true,
        only: ['clubs'],
    });
};

// Column definitions
const columns: ColumnDef<Club>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.clubs.current_page;
            const perPage = props.clubs.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Club Name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => {
            const club = row.original;
            return h('div', { class: 'flex items-center gap-3' }, [
                club.avatar_url
                    ? h('img', {
                        src: club.avatar_url,
                        alt: club.name,
                        class: 'h-8 w-8 rounded-full object-cover'
                    })
                    : h('div', {
                        class: 'h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center'
                    }, [
                        h('span', { class: 'text-xs font-medium' }, club.name.charAt(0).toUpperCase())
                    ]),
                h('div', [
                    h('div', { class: 'font-medium' }, club.name),
                    club.description && h('div', {
                        class: 'text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs'
                    }, club.description)
                ])
            ]);
        },
    },
    {
        header: 'Campus',
        accessorKey: 'campus.name',
        enableSorting: true,
        cell: ({ row }) => {
            const campus = row.original.campus;
            return h('div', { class: 'flex items-center gap-2' }, [
                h(Building, { class: 'h-4 w-4 text-gray-500' }),
                h('span', campus?.name || 'No campus')
            ]);
        },
    },
    {
        header: 'President',
        id: 'president',
        enableSorting: false,
        cell: ({ row }) => {
            const president = row.original.president;
            if (!president?.student) {
                return h('span', { class: 'text-gray-500 text-sm' }, 'No president');
            }
            return h('div', { class: 'text-sm' }, [
                h('div', { class: 'font-medium' }, president.student.full_name),
                h('div', { class: 'text-gray-500' }, president.student.student_id)
            ]);
        },
    },
    {
        header: 'Members',
        id: 'members_count',
        enableSorting: false,
        cell: ({ row }) => {
            const club = row.original;
            const activeCount = club.active_members_count || 0;
            const pendingCount = club.pending_applications_count || 0;

            return h('div', { class: 'flex items-center gap-2' }, [
                h(Users, { class: 'h-4 w-4 text-gray-500' }),
                h('div', { class: 'text-sm' }, [
                    h('div', { class: 'font-medium' }, `${activeCount} active`),
                    pendingCount > 0 && h('div', { class: 'text-gray-500' }, `${pendingCount} pending`)
                ])
            ]);
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: true,
        cell: ({ row }) => {
            const status = row.original.status;
            const variant = status === 'active' ? 'success' : 'secondary';
            return h(Badge, { variant }, () => status.charAt(0).toUpperCase() + status.slice(1));
        },
    },
    {
        header: 'Founded',
        accessorKey: 'founded_date',
        enableSorting: true,
        cell: ({ row }) => {
            const date = row.original.founded_date;
            if (!date) return h('span', { class: 'text-gray-500 text-sm' }, 'Not set');

            return h('div', { class: 'flex items-center gap-2 text-sm' }, [
                h(Calendar, { class: 'h-4 w-4 text-gray-500' }),
                new Date(date).toLocaleDateString()
            ]);
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
    <Head title="Clubs" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Clubs</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage student clubs and organizations.</p>
        </div>
        <Button size="sm" @click="goToCreatePage">
            <Plus class="mr-2 h-4 w-4" />
            Add Club
        </Button>
    </div>

    <!-- Filters -->
    <div class="mt-6 space-y-4">
        <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
            <!-- Search -->
            <div class="flex-1 min-w-[300px]">
                <DebouncedInput
                    v-model="filters.search"
                    @debounced="handleSearch"
                    placeholder="Search clubs by name or description..."
                />
            </div>

            <!-- Campus Filter -->
            <div class="min-w-[200px]">
                <Select :model-value="filters.campus_id" @update:model-value="handleCampusFilter">
                    <SelectTrigger>
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

            <!-- Status Filter -->
            <div class="min-w-[150px]">
                <Select :model-value="filters.status" @update:model-value="handleStatusFilter">
                    <SelectTrigger>
                        <SelectValue placeholder="All Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="inactive">Inactive</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Per Page -->
            <div class="min-w-[100px]">
                <Select :model-value="filters.per_page.toString()" @update:model-value="handlePerPageChange">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="10">10</SelectItem>
                        <SelectItem value="15">15</SelectItem>
                        <SelectItem value="25">25</SelectItem>
                        <SelectItem value="50">50</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Clear Filters -->
            <Button variant="outline" size="sm" @click="resetFilters" :disabled="!hasActiveFilters">
                <X class="h-4 w-4" />
                Clear
            </Button>
        </div>
    </div>

    <!-- Data Table -->
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
                                <p>View Club</p>
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
                                <p>Edit Club</p>
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
                                <p>Delete Club</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <!-- Pagination -->
    <DataPagination :pagination-data="clubs" @navigate="handlePaginationNavigate" />

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Club</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete the club
                    <strong>{{ selectedClub?.name }}</strong>?
                    This will also remove all memberships and cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeDeleteModal">Cancel</AlertDialogCancel>
                <AlertDialogAction
                    @click="submitDelete"
                    :disabled="deleteForm.processing"
                    class="bg-red-600 hover:bg-red-700"
                >
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete Club' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
