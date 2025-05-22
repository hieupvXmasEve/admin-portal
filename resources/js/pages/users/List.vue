<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn, valueUpdater } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';
import type { User } from '@/types/User';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ExpandedState, SortingState, VisibilityState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getExpandedRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { ChevronDown, Edit2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    users: PaginatedResponse<User>;
    filters?: {
        name?: string;
        email?: string;
        search?: string;
    };
}>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'List Users',
        href: '/users',
    },
];

// Reactive data
// const data = ref<User[]>(props.users.data);
const data = computed(() => props.users.data);

// Filter state - khởi tạo từ props
const filters = ref({
    name: props.filters?.name || '',
    email: props.filters?.email || '',
    search: props.filters?.search || '',
});

// Dialog và form state
const isDialogOpen = ref(false);
const selectedUser = ref<User | null>(null);
const editForm = ref({
    name: '',
    email: '',
});

// Dialog functions
const openEditDialog = (user: User) => {
    selectedUser.value = user;
    editForm.value = {
        name: user.name,
        email: user.email,
    };
    isDialogOpen.value = true;
};

const handleSubmit = () => {
    if (selectedUser.value) {
        console.log('Updating user:', selectedUser.value.id, editForm.value);
        closeDialog();
    }
};

const closeDialog = () => {
    isDialogOpen.value = false;
    selectedUser.value = null;
    editForm.value = {
        name: '',
        email: '',
    };
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    // Add filters to URL params
    if (newFilters.name) params.set('filter[name]', newFilters.name);
    if (newFilters.email) params.set('filter[email]', newFilters.email);
    if (newFilters.search) params.set('search', newFilters.search);

    const url = `/users${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters'], // Chỉ reload data cần thiết
    });
};

// Debounced filter functions
const debouncedApplyFilters = useDebounceFn((newFilters) => {
    applyFilters(newFilters);
}, 500);

const updateNameFilter = (value: string) => {
    filters.value.name = value;
    debouncedApplyFilters(filters.value);
};

const updateEmailFilter = (value: string) => {
    filters.value.email = value;
    debouncedApplyFilters(filters.value);
};

const updateSearchFilter = (value: string) => {
    filters.value.search = value;
    debouncedApplyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        name: '',
        email: '',
        search: '',
    };
    router.visit('/users', {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.name || filters.value.email || filters.value.search;
});

// Column definitions - loại bỏ client-side filtering
const columns: ColumnDef<User>[] = [
    {
        header: 'Name',
        accessorKey: 'name',
        enableSorting: true,
    },
    {
        accessorKey: 'email',
        header: 'Email',
        enableSorting: true,
    },
    {
        id: 'actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions',
    },
];

// Table state - loại bỏ columnFilters và globalFilter
const sorting = ref<SortingState>([]);
const columnVisibility = ref<VisibilityState>({});
const rowSelection = ref({});
const expanded = ref<ExpandedState>({});

// Table instance - loại bỏ filtering models
const table = useVueTable({
    get data() {
        return data.value; // Sử dụng computed value
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getExpandedRowModel: getExpandedRowModel(),
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
    onColumnVisibilityChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnVisibility),
    onRowSelectionChange: (updaterOrValue) => valueUpdater(updaterOrValue, rowSelection),
    onExpandedChange: (updaterOrValue) => valueUpdater(updaterOrValue, expanded),
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnVisibility() {
            return columnVisibility.value;
        },
        get rowSelection() {
            return rowSelection.value;
        },
        get expanded() {
            return expanded.value;
        },
    },
    manualFiltering: true, // Báo cho table biết là server-side filtering
    manualPagination: true, // Server-side pagination
});

// Pagination functions
const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['users'],
        });
    }
};

const goToPreviousPage = () => {
    goToPage(props.users.prev_page_url);
};

const goToNextPage = () => {
    goToPage(props.users.next_page_url);
};
</script>
<template>
    <Head title="List Users" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Filters Section -->
            <div class="space-y-4">
                <!-- Global Search -->
                <div class="flex items-center gap-2">
                    <Input
                        :model-value="filters.search"
                        @update:model-value="updateSearchFilter"
                        placeholder="Search all columns..."
                        class="max-w-sm"
                    />

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="ml-auto">
                                Columns
                                <ChevronDown class="ml-2 h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuCheckboxItem
                                v-for="column in table.getAllColumns().filter((column) => column.getCanHide())"
                                :key="column.id"
                                class="capitalize"
                                :model-value="column.getIsVisible()"
                                @update:model-value="
                                    (value) => {
                                        column.toggleVisibility(!!value);
                                    }
                                "
                            >
                                {{ column.id }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <!-- Column Filters -->
                <div class="flex items-center gap-2">
                    <div class="flex flex-col gap-1">
                        <Label class="text-muted-foreground text-xs">Name</Label>
                        <Input :model-value="filters.name" @update:model-value="updateNameFilter" placeholder="Filter by name..." class="w-48" />
                    </div>

                    <div class="flex flex-col gap-1">
                        <Label class="text-muted-foreground text-xs">Email</Label>
                        <Input :model-value="filters.email" @update:model-value="updateEmailFilter" placeholder="Filter by email..." class="w-48" />
                    </div>

                    <!-- Clear Filters Button -->
                    <div class="flex flex-col gap-1">
                        <Label class="text-xs text-transparent">Clear</Label>
                        <Button variant="outline" size="default" @click="clearFilters" :disabled="!hasActiveFilters" class="flex items-center gap-2">
                            <X class="h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </div>

                <!-- Active Filters Display -->
                <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2">
                    <span class="text-muted-foreground text-sm">Active filters:</span>

                    <div v-if="filters.search" class="bg-secondary flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                        <span>Search: "{{ filters.search }}"</span>
                        <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="updateSearchFilter('')">
                            <X class="h-3 w-3" />
                        </Button>
                    </div>

                    <div v-if="filters.name" class="bg-secondary flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                        <span>Name: "{{ filters.name }}"</span>
                        <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="updateNameFilter('')">
                            <X class="h-3 w-3" />
                        </Button>
                    </div>

                    <div v-if="filters.email" class="bg-secondary flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                        <span>Email: "{{ filters.email }}"</span>
                        <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="updateEmailFilter('')">
                            <X class="h-3 w-3" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                            <TableHead
                                v-for="header in headerGroup.headers"
                                :key="header.id"
                                :data-pinned="header.column.getIsPinned()"
                                :class="
                                    cn(
                                        { 'bg-background/95 sticky': header.column.getIsPinned() },
                                        header.column.getIsPinned() === 'left' ? 'left-0' : 'right-0',
                                    )
                                "
                            >
                                <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="table.getRowModel().rows?.length">
                            <template v-for="row in table.getRowModel().rows" :key="row.id">
                                <TableRow :data-state="row.getIsSelected() && 'selected'">
                                    <TableCell
                                        v-for="cell in row.getVisibleCells()"
                                        :key="cell.id"
                                        :data-pinned="cell.column.getIsPinned()"
                                        :class="
                                            cn(
                                                { 'bg-background/95 sticky': cell.column.getIsPinned() },
                                                cell.column.getIsPinned() === 'left' ? 'left-0' : 'right-0',
                                            )
                                        "
                                    >
                                        <template v-if="cell.column.id === 'actions'">
                                            <div class="flex items-center gap-2">
                                                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                                                    <Tooltip>
                                                        <TooltipTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                @click="openEditDialog(row.original)"
                                                                class="cursor-pointer"
                                                            >
                                                                <Edit2 class="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>Edit</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                        </template>
                                        <FlexRender v-else :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                    </TableCell>
                                </TableRow>
                            </template>
                        </template>

                        <TableRow v-else>
                            <TableCell :colspan="columns.length" class="h-24 text-center"> No results found.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between space-x-2 py-4">
                <div class="text-muted-foreground text-sm">
                    Showing {{ props.users.from || 0 }} to {{ props.users.to || 0 }} of {{ props.users.total }} users (Page
                    {{ props.users.current_page }} of {{ props.users.last_page }})
                </div>
                <div class="flex space-x-2">
                    <Button variant="outline" size="sm" :disabled="!props.users.prev_page_url" @click="goToPreviousPage"> Previous </Button>
                    <div class="flex space-x-1">
                        <template v-for="link in props.users.links" :key="link.label">
                            <Button
                                v-if="link.url && !link.label.includes('Previous') && !link.label.includes('Next')"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                @click="goToPage(link.url)"
                                class="min-w-[2.5rem]"
                            >
                                {{ link.label }}
                            </Button>
                        </template>
                    </div>
                    <Button variant="outline" size="sm" :disabled="!props.users.next_page_url" @click="goToNextPage"> Next </Button>
                </div>
            </div>
        </div>

        <!-- Edit User Dialog -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit User</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="editForm.name" placeholder="Enter user name" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="editForm.email" type="email" placeholder="Enter user email" required />
                    </div>
                    <div class="flex justify-end space-x-2">
                        <Button type="button" variant="outline" @click="closeDialog"> Cancel</Button>
                        <Button type="submit"> Save Changes</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
