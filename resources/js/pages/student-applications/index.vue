<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useDataTable } from '@/composables/useDataTable';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { CheckCircle2, Download, ExternalLink, Eye, FileText, MoreHorizontal, Pencil, Plus, XCircle } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface LinkedStudent {
    id: number;
    student_id: string;
    full_name: string;
}

interface DocRef {
    id: number;
    link: string;
    original_name: string | null;
    page_index: number;
}

interface ApplicationRow {
    id: number;
    full_name: string;
    student_code: string;
    email: string;
    national_id: string | null;
    phone: string | null;
    intended_program: string | null;
    intake: string | null;
    status: 'pending' | 'enrolled' | 'rejected';
    documents_by_type: Record<string, DocRef[]>;
    created_at: string;
    student?: LinkedStudent | null;
}

interface DocumentType {
    code: string;
    name: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ApplicationsPaginator {
    data: ApplicationRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
}

interface Campus {
    code: string;
    name: string;
}

interface StatusOption {
    value: string;
    label: string;
}

interface Filters {
    search: string;
    status: string;
    intake: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
}

interface Props {
    applications: ApplicationsPaginator;
    filters: Partial<Filters>;
    currentCampus: Campus | null;
    documentTypes: DocumentType[];
    intakes: string[];
    statusOptions: StatusOption[];
}

const props = defineProps<Props>();

// Shared filter/sort/pagination plumbing (same composable as the other list pages).
const { filters, setFilter, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection, isLoading } =
    useDataTable<Filters>({
        baseUrl: route('student-applications.index'),
        initialFilters: {
            search: props.filters.search ?? '',
            status: props.filters.status ?? 'all',
            intake: props.filters.intake ?? 'all',
            sort: props.filters.sort ?? 'created_at',
            direction: props.filters.direction ?? 'desc',
            per_page: props.filters.per_page ?? 15,
            page: props.applications.current_page ?? 1,
        },
        defaultValues: {
            search: '',
            status: 'all',
            intake: 'all',
            sort: 'created_at',
            direction: 'desc',
            per_page: 15,
            page: 1,
        },
        only: ['applications', 'filters'],
        immediateFields: ['status', 'intake'],
    });

const data = computed(() => props.applications.data);

// One column per active document type (from the catalog), plus the fixed columns.
const columns = computed<ColumnDef<ApplicationRow>[]>(() => [
    { accessorKey: 'full_name', header: 'Applicant', enableSorting: true },
    { accessorKey: 'student_code', header: 'Code', enableSorting: true },
    { accessorKey: 'national_id', header: 'National ID', enableSorting: false },
    { accessorKey: 'phone', header: 'Phone', enableSorting: false },
    { accessorKey: 'intended_program', header: 'Program', enableSorting: true },
    { accessorKey: 'intake', header: 'Intake', enableSorting: true },
    { accessorKey: 'status', header: 'Status', enableSorting: true },
    { accessorKey: 'created_at', header: 'Created', enableSorting: true },
    ...props.documentTypes.map(
        (type): ColumnDef<ApplicationRow> => ({
            id: `doc_${type.code}`,
            header: () => h('span', { class: 'block max-w-[160px] truncate', title: type.name }, type.name),
            enableSorting: false,
        }),
    ),
    { id: 'actions', header: '', enableSorting: false, enableHiding: false },
]);

const statusBadgeClass = (status: ApplicationRow['status']): string => {
    switch (status) {
        case 'enrolled':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'rejected':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
};

const formatDate = (value: string): string => new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

// Documents of a given catalog type for a row (empty when none submitted).
const docsForType = (row: ApplicationRow, code: string): DocRef[] => row.documents_by_type?.[code] ?? [];

const goShow = (id: number) => router.visit(route('student-applications.show', id));
const goEdit = (id: number) => router.visit(route('student-applications.edit', id));
const goCreate = () => router.visit(route('student-applications.create'));

// Export the current (campus-scoped) view to Excel for the CRM to complete:
// missing fields come out blank. `scope=filtered` honours the active filters,
// so with no filters set it is effectively the whole campus.
const exportExcel = () => {
    window.location.href = route('student-applications.export', {
        format: 'xlsx',
        scope: 'filtered',
        search: filters.search || undefined,
        status: filters.status,
        intake: filters.intake,
        sort: filters.sort ?? undefined,
        direction: filters.direction ?? undefined,
    });
};

// Open the focused documents view in a new browser tab.
const openDocuments = (id: number) => window.open(route('student-applications.documents', id), '_blank', 'noopener');

// Lifecycle actions reload the list in place (the controller redirects back) —
// they never navigate away from this page.
const reloadOptions = (onSuccess: () => void, onError: () => void) => ({
    preserveScroll: true,
    preserveState: true,
    onSuccess,
    onError,
});

const approve = (row: ApplicationRow) => {
    router.post(
        route('student-applications.approve', row.id),
        {},
        reloadOptions(
            () => toast.success(`${row.full_name} approved and enrolled.`),
            () => toast.error('Failed to approve application.'),
        ),
    );
};

// Reject dialog state
const showRejectDialog = ref(false);
const rejectTarget = ref<ApplicationRow | null>(null);
const rejectReason = ref('');
const isSubmitting = ref(false);

const openReject = (row: ApplicationRow) => {
    rejectTarget.value = row;
    rejectReason.value = '';
    showRejectDialog.value = true;
};

const submitReject = () => {
    if (!rejectTarget.value || !rejectReason.value.trim()) {
        toast.error('A rejection reason is required.');
        return;
    }
    isSubmitting.value = true;
    router.post(route('student-applications.reject', rejectTarget.value.id), { rejected_reason: rejectReason.value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success('Application rejected.');
            showRejectDialog.value = false;
        },
        onError: () => toast.error('Failed to reject application.'),
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Student Applications" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Applications</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Review applications, then approve to enroll or reject with a reason.
                    <span v-if="currentCampus" class="text-foreground font-medium">· {{ currentCampus.name }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" @click="exportExcel">
                    <Download class="mr-2 h-4 w-4" />
                    Export Excel
                </Button>
                <Button @click="goCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    New application
                </Button>
            </div>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 py-4 md:grid-cols-3">
                <DebouncedInput :model-value="filters.search" placeholder="Search name, email, code…" @update:model-value="(v: string) => handleSearch(v)" />

                <Select :model-value="filters.status" @update:model-value="(v) => setFilter('status', v as string)">
                    <SelectTrigger><SelectValue placeholder="Status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All statuses</SelectItem>
                        <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="filters.intake" @update:model-value="(v) => setFilter('intake', v as string)">
                    <SelectTrigger><SelectValue placeholder="Intake" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All intakes</SelectItem>
                        <SelectItem v-for="intake in intakes" :key="intake" :value="intake">{{ intake }}</SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <!-- Table (shared DataTable + named cell slots) -->
        <DataTable
            :columns="columns"
            :data="data"
            :loading="isLoading"
            :show-column-toggle="false"
            empty-message="No applications match these filters."
            enable-server-sorting
            :initial-sort="currentSort ?? undefined"
            :initial-direction="currentDirection ?? undefined"
            @sort-change="handleSortChange"
        >
            <template #cell-full_name="{ row }">
                <button class="font-medium hover:underline" @click="goShow(row.original.id)">{{ row.original.full_name }}</button>
                <p class="text-muted-foreground text-xs">{{ row.original.email }}</p>
            </template>

            <template #cell-national_id="{ row }">{{ row.original.national_id ?? '—' }}</template>
            <template #cell-phone="{ row }">{{ row.original.phone ?? '—' }}</template>
            <template #cell-intended_program="{ row }">{{ row.original.intended_program ?? '—' }}</template>
            <template #cell-intake="{ row }">{{ row.original.intake ?? '—' }}</template>

            <template #cell-status="{ row }">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusBadgeClass(row.original.status)">
                    {{ row.original.status }}
                </span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-muted-foreground whitespace-nowrap">{{ formatDate(row.original.created_at) }}</span>
            </template>

            <!-- One slot per document-type column: links to the file(s), or "Không có". -->
            <template v-for="type in documentTypes" :key="type.code" #[`cell-doc_${type.code}`]="{ row }">
                <div v-if="docsForType(row.original, type.code).length > 0" class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <a
                        v-for="doc in docsForType(row.original, type.code)"
                        :key="doc.id"
                        :href="doc.link"
                        target="_blank"
                        rel="noopener noreferrer"
                        :title="doc.original_name ?? 'Open document'"
                        class="text-primary inline-flex items-center gap-1 font-medium hover:underline"
                    >
                        <ExternalLink class="h-3.5 w-3.5" />
                        {{ docsForType(row.original, type.code).length > 1 ? `Trang ${doc.page_index + 1}` : 'Xem' }}
                    </a>
                </div>
                <span v-else class="text-muted-foreground text-xs">Không có</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button
                        v-if="row.original.status === 'pending'"
                        size="sm"
                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                        @click="approve(row.original)"
                    >
                        <CheckCircle2 class="mr-1 h-4 w-4" />
                        Approve
                    </Button>
                    <Button v-if="row.original.status === 'pending'" size="sm" variant="destructive" @click="openReject(row.original)">
                        <XCircle class="mr-1 h-4 w-4" />
                        Reject
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button size="sm" variant="ghost"><MoreHorizontal class="h-4 w-4" /></Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="goShow(row.original.id)">
                                <Eye class="mr-2 h-4 w-4" />
                                View
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="openDocuments(row.original.id)">
                                <FileText class="mr-2 h-4 w-4" />
                                Documents (new tab)
                            </DropdownMenuItem>
                            <template v-if="row.original.status === 'pending'">
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="goEdit(row.original.id)">
                                    <Pencil class="mr-2 h-4 w-4" />
                                    Edit
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
        </DataTable>

        <!-- Pagination (shared component) -->
        <DataPagination :pagination-data="applications" item-name="applications" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>

    <!-- Reject dialog -->
    <Dialog v-model:open="showRejectDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject application</DialogTitle>
                <DialogDescription>
                    Record why <strong>{{ rejectTarget?.full_name }}</strong> is being rejected. No student will be created.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2 py-2">
                <Label for="reject-reason">Reason</Label>
                <Textarea id="reject-reason" v-model="rejectReason" rows="4" placeholder="Explain the reason for rejection" />
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isSubmitting" @click="showRejectDialog = false">Cancel</Button>
                <Button variant="destructive" :disabled="isSubmitting" @click="submitReject">Confirm rejection</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
