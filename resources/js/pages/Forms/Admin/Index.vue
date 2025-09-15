<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import FormCloneModal from '@/components/forms/FormCloneModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Campus, Form } from '@/types/forms';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Archive, Copy, Edit, Eye, Filter, MoreHorizontal, Plus, Search, Settings, Trash2 } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    forms: Form[];
    filters: {
        type?: string;
        status?: string;
        search?: string;
        campus_id?: number;
    };
    campuses: Campus[];
}

const props = defineProps<Props>();

// Local state
const searchTerm = ref(props.filters.search || '');
const selectedType = ref(props.filters.type || '');
const selectedStatus = ref(props.filters.status || '');
const selectedCampus = ref(props.filters.campus_id?.toString() || '');
const showCloneModal = ref(false);
const selectedFormForClone = ref<Form | null>(null);

// Computed
const filteredForms = computed(() => {
    return props.forms.filter((form) => {
        const matchesSearch = !searchTerm.value || form.title.toLowerCase().includes(searchTerm.value.toLowerCase()) || form.code.toLowerCase().includes(searchTerm.value.toLowerCase());

        const matchesType = selectedType.value === 'all' || !selectedType.value || form.type === selectedType.value;
        const matchesStatus = selectedStatus.value === 'all' || !selectedStatus.value || form.status === selectedStatus.value;
        const matchesCampus = selectedCampus.value === 'all' || !selectedCampus.value || (form.active_targets_count && form.active_targets_count > 0);

        return matchesSearch && matchesType && matchesStatus && matchesCampus;
    });
});

const formTypeOptions = [
    { value: 'feedback', label: 'Feedback' },
    { value: 'survey', label: 'Survey' },
    { value: 'query', label: 'Query' },
];

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
];

// Methods
const applyFilters = () => {
    router.get(
        route('forms.admin.index'),
        {
            search: searchTerm.value || undefined,
            type: selectedType.value || undefined,
            status: selectedStatus.value || undefined,
            campus_id: selectedCampus.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    searchTerm.value = '';
    selectedType.value = 'all';
    selectedStatus.value = 'all';
    selectedCampus.value = 'all';
    applyFilters();
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'draft':
            return 'secondary';
        case 'archived':
            return 'outline';
        default:
            return 'secondary';
    }
};

const getTypeBadgeVariant = (type: string) => {
    switch (type) {
        case 'feedback':
            return 'default';
        case 'survey':
            return 'secondary';
        case 'query':
            return 'outline';
        default:
            return 'secondary';
    }
};

const handleAction = (action: string, form: Form) => {
    switch (action) {
        case 'view':
            router.visit(route('forms.admin.show', form.id));
            break;
        case 'edit':
            router.visit(route('forms.admin.edit', form.id));
            break;
        case 'clone':
            selectedFormForClone.value = form;
            showCloneModal.value = true;
            break;
        case 'archive':
            router.post(route('forms.admin.archive', form.id));
            break;
        case 'restore':
            router.post(route('forms.admin.restore', form.id));
            break;
        case 'delete':
            if (confirm('Are you sure you want to delete this form?')) {
                router.delete(route('forms.admin.destroy', form.id));
            }
            break;
    }
};

// Table columns
const columns: ColumnDef<Form>[] = [
    {
        accessorKey: 'title',
        header: 'Form Title',
        enableSorting: false,
        cell: ({ row }) => {
            return h('div', { class: 'font-medium' }, row.original.title);
        },
    },
    {
        accessorKey: 'code',
        header: 'Code',
        enableSorting: false,
        cell: ({ row }) => {
            return h('code', { class: 'text-sm bg-muted px-2 py-1 rounded' }, row.original.code);
        },
    },
    {
        accessorKey: 'type',
        header: 'Type',
        enableSorting: false,
        cell: ({ row }) => {
            return h(Badge, { variant: getTypeBadgeVariant(row.original.type) }, () => row.original.type);
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => {
            return h(Badge, { variant: getStatusBadgeVariant(row.original.status) }, () => row.original.status);
        },
    },
    {
        accessorKey: 'creator.name',
        header: 'Created By',
        enableSorting: false,
        cell: ({ row }) => {
            return h('span', row.original.creator?.name || 'Unknown');
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        enableSorting: false,
        cell: ({ row }) => {
            return h('span', new Date(row.original.created_at).toLocaleDateString());
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
    <Head title="Forms Management" />

    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Forms Management</h1>
                <p class="text-muted-foreground">Create and manage feedback forms, surveys, and query forms</p>
            </div>
            <Link :href="route('forms.admin.create')">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Create Form
                </Button>
            </Link>
        </div>

        <!-- Filters -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle class="flex items-center">
                    <Filter class="mr-2 h-4 w-4" />
                    Filters
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Search -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Search</label>
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                            <Input v-model="searchTerm" placeholder="Search forms..." class="pl-8" @keyup.enter="applyFilters" />
                        </div>
                    </div>

                    <!-- Type Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Type</label>
                        <Select v-model:model-value="selectedType">
                            <SelectTrigger>
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem v-for="option in formTypeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Status Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Status</label>
                        <Select v-model:model-value="selectedStatus">
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Campus Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Campus</label>
                        <Select v-model:model-value="selectedCampus">
                            <SelectTrigger>
                                <SelectValue placeholder="All campuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All campuses</SelectItem>
                                <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <Button @click="applyFilters">Apply Filters</Button>
                    <Button variant="outline" @click="clearFilters">Clear</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Forms Table -->
        <Card>
            <CardHeader>
                <CardTitle>Forms ({{ filteredForms.length }})</CardTitle>
                <CardDescription> Manage your forms, versions, and targeting </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :columns="columns" :data="filteredForms">
                    <template #cell-actions="{ row }">
                        <div class="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" class="h-8 w-8 p-0">
                                        <MoreHorizontal class="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="() => handleAction('view', row.original)">
                                        <Eye class="mr-2 h-4 w-4" />
                                        View
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="() => handleAction('edit', row.original)">
                                        <Edit class="mr-2 h-4 w-4" />
                                        Edit
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="() => handleAction('clone', row.original)">
                                        <Copy class="mr-2 h-4 w-4" />
                                        Clone
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem v-if="row.original.status !== 'archived'" @click="() => handleAction('archive', row.original)">
                                        <Archive class="mr-2 h-4 w-4" />
                                        Archive
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-else @click="() => handleAction('restore', row.original)">
                                        <Settings class="mr-2 h-4 w-4" />
                                        Restore
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="text-destructive" @click="() => handleAction('delete', row.original)">
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <!-- Clone Modal -->
        <FormCloneModal v-if="selectedFormForClone" v-model:open="showCloneModal" :form="selectedFormForClone" />
    </div>
</template>
