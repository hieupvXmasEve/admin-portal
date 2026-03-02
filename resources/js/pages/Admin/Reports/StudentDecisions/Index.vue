<script setup lang="ts">
import FileUpload from '@/components/FileUpload.vue';
import ServerDateRangeFilters from '@/components/filters/ServerDateRangeFilters.vue';
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import type { PaginatedResponse } from '@/types';
import type { UploadedFile } from '@/types/fileUpload';
import type { StudentDecision } from '@/types/student-decision';
import { studentRoutes } from '@/utils/routes';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ExternalLink, Eye, Pencil, Plus } from 'lucide-vue-next';
import { h, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface StudentDecisionFilters {
    search?: string;
    issued_from?: string;
    issued_to?: string;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    per_page?: number;
    page?: number;
}

interface Props {
    decisions: PaginatedResponse<StudentDecision>;
    filters: StudentDecisionFilters;
}

const props = defineProps<Props>();

const { filters, hasActiveFilters, clearFilters, apply, applySearch, handleSortChange, handlePageChange, handlePageSizeChange, currentSort, currentDirection } = useServerTableQuery<StudentDecisionFilters>({
    baseUrl: studentRoutes.studentDecisionsIndex(),
    initialFilters: {
        search: props.filters.search ?? '',
        issued_from: props.filters.issued_from ?? '',
        issued_to: props.filters.issued_to ?? '',
        sort: props.filters.sort ?? 'issued_at',
        direction: props.filters.direction ?? 'desc',
        per_page: props.filters.per_page ?? 15,
        page: props.filters.page ?? 1,
    },
    emptyFilters: {
        search: '',
        issued_from: '',
        issued_to: '',
        sort: 'issued_at',
        direction: 'desc',
        per_page: 15,
        page: 1,
    },
    defaultValues: {
        sort: 'issued_at',
        direction: 'desc',
        per_page: 15,
        page: 1,
    },
    only: ['decisions', 'filters'],
});

const filterForm = reactive({
    search: String(filters.search ?? ''),
    issued_from: String(filters.issued_from ?? ''),
    issued_to: String(filters.issued_to ?? ''),
});

const isDialogOpen = ref(false);
const editingDecision = ref<StudentDecision | null>(null);
const decisionUploadFiles = ref<UploadedFile[]>([]);

const form = useForm({
    decision_name: '',
    decision_number: '',
    decision_signer: '',
    issued_at: '',
    expires_at: '',
    upload_record_id: null as number | null,
});

watch(decisionUploadFiles, (files) => {
    if (files.length > 0) {
        form.upload_record_id = files[0].id;
        return;
    }

    if (editingDecision.value?.upload_record_id) {
        form.upload_record_id = editingDecision.value.upload_record_id;
        return;
    }

    form.upload_record_id = null;
});

watch(
    () => [filters.search, filters.issued_from, filters.issued_to] as const,
    ([search, issuedFrom, issuedTo]) => {
        filterForm.search = String(search ?? '');
        filterForm.issued_from = String(issuedFrom ?? '');
        filterForm.issued_to = String(issuedTo ?? '');
    },
);

const handleSearchInput = (value: string) => {
    filterForm.search = value;
    applySearch(value);
};

const updateFilterForm = (value: { search: string; issued_from: string; issued_to: string }) => {
    filterForm.search = value.search;
    filterForm.issued_from = value.issued_from;
    filterForm.issued_to = value.issued_to;
};

const handleDateChange = (value: { issued_from: string; issued_to: string }) => {
    filterForm.issued_from = value.issued_from;
    filterForm.issued_to = value.issued_to;
    apply({
        issued_from: value.issued_from || '',
        issued_to: value.issued_to || '',
        page: 1,
    });
};

const clearFilterForm = () => {
    filterForm.search = '';
    filterForm.issued_from = '';
    filterForm.issued_to = '';
    clearFilters();
};

const openCreate = () => {
    editingDecision.value = null;
    form.reset();
    decisionUploadFiles.value = [];
    isDialogOpen.value = true;
};

const openEdit = (decision: StudentDecision) => {
    editingDecision.value = decision;
    form.decision_name = decision.decision_name;
    form.decision_number = decision.decision_number;
    form.decision_signer = decision.decision_signer;
    form.issued_at = decision.issued_at ? decision.issued_at.slice(0, 10) : '';
    form.expires_at = decision.expires_at ? decision.expires_at.slice(0, 10) : '';
    form.upload_record_id = decision.upload_record_id;
    decisionUploadFiles.value = [];

    isDialogOpen.value = true;
};

const submitDecision = () => {
    const payload = {
        ...form.data(),
        expires_at: form.expires_at || null,
    };

    if (editingDecision.value) {
        form.transform(() => payload).put(studentRoutes.studentDecisionsUpdate(editingDecision.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Decision updated successfully');
                isDialogOpen.value = false;
            },
            onError: () => toast.error('Failed to update decision'),
        });

        return;
    }

    form.transform(() => payload).post(studentRoutes.studentDecisionsStore(), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Decision created successfully');
            isDialogOpen.value = false;
            form.reset();
            decisionUploadFiles.value = [];
        },
        onError: () => toast.error('Failed to create decision'),
    });
};

const formatDateOnly = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN');
};

const columns: ColumnDef<StudentDecision>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.decisions.current_page;
            const perPage = props.decisions.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Decision',
        accessorKey: 'decision_number',
        enableSorting: true,
        cell: ({ row }) => {
            const decision = row.original;
            return h('div', { class: 'space-y-1' }, [h('div', { class: 'font-medium' }, decision.decision_number), h('div', { class: 'text-muted-foreground' }, decision.decision_name)]);
        },
    },
    {
        header: 'Signer',
        accessorKey: 'decision_signer',
        enableSorting: true,
    },
    {
        header: 'Issued',
        accessorKey: 'issued_at',
        enableSorting: true,
        cell: ({ row }) => formatDateOnly(row.original.issued_at),
    },
    {
        header: 'Expires',
        accessorKey: 'expires_at',
        enableSorting: true,
        cell: ({ row }) => formatDateOnly(row.original.expires_at),
    },
    {
        header: 'Linked Actions',
        accessorKey: 'linked_actions_count',
        enableSorting: false,
        cell: ({ row }) => h('span', { class: 'inline-flex rounded border px-2 py-1 text-xs font-medium' }, String(row.original.linked_actions_count ?? 0)),
    },
    {
        header: 'Linked Students',
        accessorKey: 'linked_students_count',
        enableSorting: false,
        cell: ({ row }) => h('span', { class: 'inline-flex rounded border px-2 py-1 text-xs font-medium' }, String(row.original.linked_students_count ?? 0)),
    },
    {
        header: 'File',
        id: 'file',
        enableSorting: false,
        cell: 'file',
    },
    {
        header: 'Actions',
        id: 'actions',
        enableSorting: false,
        enableHiding: false,
        cell: 'actions',
    },
];
</script>

<template>
    <Head title="Student Decisions" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Decisions</h1>
                <p class="text-muted-foreground mt-1">Decision registry linked manually to student actions.</p>
            </div>
            <Dialog v-model:open="isDialogOpen">
                <DialogTrigger as-child>
                    <Button @click="openCreate">
                        <Plus class="mr-2 h-4 w-4" />
                        New Decision
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ editingDecision ? 'Edit Decision' : 'Create Decision' }}</DialogTitle>
                        <DialogDescription>Manage decision metadata and optional file attachment.</DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="submitDecision">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Decision Name *</Label>
                                <Input v-model="form.decision_name" placeholder="Decision name" />
                                <p v-if="form.errors.decision_name" class="text-sm text-red-500">{{ form.errors.decision_name }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label>Decision Number *</Label>
                                <Input v-model="form.decision_number" placeholder="QD-2026-001" />
                                <p v-if="form.errors.decision_number" class="text-sm text-red-500">{{ form.errors.decision_number }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Signer *</Label>
                                <Input v-model="form.decision_signer" placeholder="Signer name" />
                                <p v-if="form.errors.decision_signer" class="text-sm text-red-500">{{ form.errors.decision_signer }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label>Issued Date *</Label>
                                <DatePicker v-model="form.issued_at" placeholder="Select issued date" />
                                <p v-if="form.errors.issued_at" class="text-sm text-red-500">{{ form.errors.issued_at }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Expiry Date (Optional)</Label>
                                <DatePicker v-model="form.expires_at" placeholder="Select expiry date" />
                                <p v-if="form.errors.expires_at" class="text-sm text-red-500">{{ form.errors.expires_at }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label>Decision File (Optional)</Label>
                                <div v-if="editingDecision?.upload_record?.url && decisionUploadFiles.length === 0" class="rounded-md border border-dashed p-3 text-sm">
                                    <a :href="editingDecision.upload_record.url" target="_blank" rel="noopener noreferrer" class="text-primary inline-flex items-center gap-1 hover:underline">
                                        <ExternalLink class="h-3 w-3" />
                                        {{ editingDecision.upload_record.original_name || 'Current file' }}
                                    </a>
                                    <p class="text-muted-foreground mt-1">Upload a new file to replace the current one.</p>
                                </div>
                                <FileUpload
                                    context="action_attachment"
                                    :multiple="false"
                                    :upload-immediately="true"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    :allowed-types="['application/pdf', 'image/jpeg', 'image/png']"
                                    v-model="decisionUploadFiles"
                                    label="Upload decision file"
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isDialogOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ editingDecision ? 'Update' : 'Create' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filter</CardTitle>
                <CardDescription>Search by name, number, signer or issued date range.</CardDescription>
            </CardHeader>
            <CardContent>
                <ServerDateRangeFilters :model-value="filterForm" :has-active-filters="hasActiveFilters" @update:model-value="updateFilterForm" @search="handleSearchInput" @date-change="handleDateChange" @clear="clearFilterForm" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Decision List</CardTitle>
            </CardHeader>
            <CardContent>
                <ServerPaginatedDataTable
                    :data="decisions.data"
                    :columns="columns"
                    :pagination-data="decisions"
                    :initial-sort="currentSort"
                    :initial-direction="currentDirection"
                    item-name="decisions"
                    @sort-change="handleSortChange"
                    @page-change="handlePageChange"
                    @page-size-change="handlePageSizeChange"
                >
                    <template #cell-file="{ row }">
                        <a v-if="row.original.upload_record?.url" :href="row.original.upload_record.url" target="_blank" rel="noopener noreferrer">
                            <Button size="sm" variant="outline">
                                <ExternalLink class="h-3 w-3" />
                            </Button>
                        </a>
                        <span v-else class="text-muted-foreground">-</span>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center gap-2">
                            <Link :href="studentRoutes.studentDecisionsShow(row.original.id)">
                                <Button size="sm" variant="outline">
                                    <Eye class="mr-1 h-3 w-3" />
                                    View
                                </Button>
                            </Link>
                            <Button size="sm" variant="outline" @click="openEdit(row.original)">
                                <Pencil class="mr-1 h-3 w-3" />
                                Edit
                            </Button>
                        </div>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>
    </div>
</template>
