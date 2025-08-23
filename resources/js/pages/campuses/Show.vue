<script setup lang="ts">
import type { PaginatedResponse } from '@/types';
import type { Building, Campus } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, Building2, Edit, MapPin, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, h, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirmDialogStore } from '@/stores/confirmDialog';

interface Props {
    campus: Campus & { buildings_count?: number };
    buildings: PaginatedResponse<Building>;
    filters?: {
        search?: string;
    };
}

const props = defineProps<Props>();
const confirmDialog = useConfirmDialogStore();

// Tab state management with URL parameters
const validTabs = ['overview', 'buildings'] as const;
type ValidTab = (typeof validTabs)[number];

// Get current tab from URL parameters
const getCurrentTabFromURL = (): ValidTab => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') as ValidTab;
    return validTabs.includes(tabParam) ? tabParam : 'overview';
};

// Reactive tab state
const currentTab = ref<ValidTab>(getCurrentTabFromURL());

// Update URL when tab changes
const updateTabInURL = (newTab: ValidTab) => {
    const url = new URL(window.location.href);

    if (newTab === 'overview') {
        // Remove tab parameter for default tab to keep URL clean
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', newTab);
    }

    // Update URL without page reload using Inertia.js
    router.visit(url.pathname + url.search, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [], // Don't reload any props
    });
};

// Handle tab change
const handleTabChange = (newTab: string | number) => {
    const tabValue = String(newTab) as ValidTab;
    // Validate the tab value before setting
    if (validTabs.includes(tabValue)) {
        currentTab.value = tabValue;
        updateTabInURL(tabValue);
    }
};

// Initialize tab state on mount and handle browser back/forward
onMounted(() => {
    // Handle browser back/forward navigation
    const handlePopState = () => {
        const newTab = getCurrentTabFromURL();
        currentTab.value = newTab;
    };

    window.addEventListener('popstate', handlePopState);

    // Cleanup listener when component is unmounted
    onUnmounted(() => {
        window.removeEventListener('popstate', handlePopState);
    });
});

// Building data and filters
const buildingData = computed(() => props.buildings.data);
const filters = ref({
    search: props.filters?.search || '',
});

const hasActiveFilters = computed(() => {
    return Object.values(filters.value).some((value) => value && value !== '');
});

// Building table columns
const buildingColumns: ColumnDef<Building>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.buildings.current_page;
            const perPage = props.buildings.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Building Code',
        accessorKey: 'code',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-mono font-medium' }, row.original.code),
    },
    {
        header: 'Building Name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.original.name),
    },
    {
        header: 'Description',
        accessorKey: 'description',
        enableSorting: false,
        cell: ({ row }) => {
            const description = row.original.description;
            return h(
                'div',
                {
                    class: 'max-w-xs truncate text-sm text-gray-600 dark:text-gray-300',
                },
                description || 'No description',
            );
        },
    },
    {
        header: 'Address',
        accessorKey: 'address',
        enableSorting: false,
        cell: ({ row }) => {
            const address = row.original.address;
            return h(
                'div',
                {
                    class: 'max-w-xs truncate text-sm text-gray-600 dark:text-gray-300',
                },
                address || 'No address',
            );
        },
    },
    {
        header: 'Created',
        accessorKey: 'created_at',
        enableSorting: true,
        cell: ({ row }) => {
            const date = new Date(row.original.created_at);
            return h('div', { class: 'text-sm text-gray-600 dark:text-gray-300' }, date.toLocaleDateString());
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: 'actions',
    },
];

// Event handlers
const goBack = () => {
    router.visit(systemRoutes.campuses.index());
};

const handleBuildingSearch = (value: string | number) => {
    filters.value.search = String(value);
    router.visit(route('campuses.show', props.campus.id), {
        data: filters.value,
        preserveState: true,
        preserveScroll: true,
        only: ['buildings'],
    });
};

const resetFilters = () => {
    filters.value = { search: '' };
    router.visit(route('campuses.show', props.campus.id), {
        preserveState: true,
        preserveScroll: true,
        only: ['buildings'],
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['buildings'],
    });
};

// Building management
const createBuilding = () => {
    router.visit(systemRoutes.campuses.buildings.create(props.campus.id));
};

const editBuilding = (building: Building) => {
    router.visit(systemRoutes.campuses.buildings.edit(props.campus.id, building.id));
};

const confirmDeleteBuilding = (building: Building) => {
    confirmDialog.showDialog(
        {
            title: 'Delete Building',
            message: `Are you sure you want to delete "${building.name}"? This action cannot be undone.`,
        },
        {
            onConfirm: () => deleteBuilding(building.id),
        },
    );
};

const deleteBuilding = (buildingId: number) => {
    router.delete(route('campuses.buildings.destroy', [props.campus.id, buildingId]), {
        preserveScroll: true,
        onSuccess: () => {
            confirmDialog.hideDialog();
            toast.success('Building deleted successfully!');
        },
        onError: () => {
            toast.error('Failed to delete building');
        },
    });
};
</script>

<template>
    <Head :title="`${campus.name} - Campus Details`" />

    <div class="flex flex-col gap-6">
        <!-- Header with back button -->
        <div class="flex items-center gap-4">
            <Button variant="ghost" size="sm" @click="goBack">
                <ArrowLeft class="h-4 w-4" />
                Back to Campuses
            </Button>
            <div class="flex-1">
                <h1 class="text-2xl font-semibold">{{ campus.name }}</h1>
                <p class="text-muted-foreground text-sm">Campus details and building management</p>
            </div>
            <Button @click="router.visit(systemRoutes.campuses.edit(campus.id))" variant="outline" class="gap-2">
                <Edit class="h-4 w-4" />
                Edit Campus
            </Button>
        </div>

        <Tabs :model-value="currentTab" @update:model-value="handleTabChange" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="overview">Campus Overview</TabsTrigger>
                <TabsTrigger value="buildings">Buildings Management</TabsTrigger>
            </TabsList>

            <!-- Campus Overview Tab -->
            <TabsContent value="overview" class="space-y-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Campus Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <MapPin class="h-5 w-5" />
                                Campus Information
                            </CardTitle>
                            <CardDescription> Basic information about this campus </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Campus Code</label>
                                <p class="font-mono font-medium">{{ campus.code }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Campus Name</label>
                                <p class="font-medium">{{ campus.name }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                                <p class="text-sm">{{ campus.address || 'No address provided' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</label>
                                <p class="text-sm">{{ new Date(campus.created_at).toLocaleDateString() }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Campus Statistics -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Building2 class="h-5 w-5" />
                                Campus Statistics
                            </CardTitle>
                            <CardDescription> Overview of campus facilities and resources </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Total Buildings</span>
                                <span class="text-lg font-bold">{{ campus.buildings_count || 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Total Users</span>
                                <span class="text-lg font-bold">{{ campus.users_count || 0 }}</span>
                            </div>
                            <!-- Add more statistics as needed -->
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <!-- Buildings Management Tab -->
            <TabsContent value="buildings" class="space-y-6">
                <!-- Buildings Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold">Buildings Management</h3>
                        <p class="text-muted-foreground text-sm">Manage buildings for {{ campus.name }}</p>
                    </div>
                    <Button @click="createBuilding" class="gap-2">
                        <Plus class="h-4 w-4" />
                        Add Building
                    </Button>
                </div>

                <!-- Building Filters -->
                <div class="flex items-center gap-4 rounded-lg border p-4">
                    <div class="flex-1">
                        <DebouncedInput v-model="filters.search" @debounced="handleBuildingSearch" placeholder="Search buildings by name, code, or description..." class="min-w-[300px]" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" @click="resetFilters" :disabled="!hasActiveFilters">
                            <X class="h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </div>

                <!-- Buildings Table -->
                <DataTable :data="buildingData" :columns="buildingColumns">
                    <template #cell-actions="{ row }">
                        <div class="flex items-center gap-1">
                            <Button variant="ghost" size="sm" @click="editBuilding(row.original)">
                                <Edit class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-700" @click="confirmDeleteBuilding(row.original)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </template>
                </DataTable>

                <!-- Buildings Pagination -->
                <DataPagination :pagination-data="buildings" @navigate="handlePaginationNavigate" />
            </TabsContent>
        </Tabs>
    </div>
</template>
