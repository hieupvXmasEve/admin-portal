<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn, valueUpdater } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';
import type { Role } from '@/types/Role';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ExpandedState, SortingState, VisibilityState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getExpandedRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';
import { Edit2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    roles: PaginatedResponse<Role>;
}>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'List Roles',
        href: '/roles',
    },
];

// Reactive data
// const data = ref<User[]>(props.roles.data);
const data = computed(() => props.roles.data);

// Dialog và form state
const isDialogOpen = ref(false);
const selectedRole = ref<Role | null>(null);
const editForm = ref({
    name: '',
});

// Dialog functions
const openEditDialog = (role: Role) => {
    selectedRole.value = role;
    editForm.value = {
        name: role.name,
    };
    isDialogOpen.value = true;
};

const handleSubmit = () => {
    if (selectedRole.value) {
        console.log('Updating user:', selectedRole.value.id, editForm.value);
        closeDialog();
    }
};

const closeDialog = () => {
    isDialogOpen.value = false;
    selectedRole.value = null;
    editForm.value = {
        name: '',
    };
};

// Column definitions - loại bỏ client-side filtering
const columns: ColumnDef<Role>[] = [
    {
        header: 'No',
        accessorKey: 'id',
        enableSorting: true,
    },
    {
        header: 'Name',
        accessorKey: 'name',
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
    goToPage(props.roles.prev_page_url);
};

const goToNextPage = () => {
    goToPage(props.roles.next_page_url);
};
</script>
<template>
    <Head title="List Users" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
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
                    Showing {{ props.roles.from || 0 }} to {{ props.roles.to || 0 }} of {{ props.roles.total }} users (Page
                    {{ props.roles.current_page }} of {{ props.roles.last_page }})
                </div>
                <div class="flex space-x-2">
                    <Button variant="outline" size="sm" :disabled="!props.roles.prev_page_url" @click="goToPreviousPage"> Previous </Button>
                    <div class="flex space-x-1">
                        <template v-for="link in props.roles.links" :key="link.label">
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
                    <Button variant="outline" size="sm" :disabled="!props.roles.next_page_url" @click="goToNextPage"> Next </Button>
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
