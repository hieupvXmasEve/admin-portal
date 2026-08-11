<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useDataTable } from '@/composables/useDataTable';
import { useTableColumnVisibility } from '@/composables/use-table-column-visibility';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, VisibilityState } from '@tanstack/vue-table';
import { CheckCircle2, ChevronDown, Download, ExternalLink, Eye, FileText, MoreHorizontal, Pencil, Plus, SlidersHorizontal, X, XCircle } from 'lucide-vue-next';
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

interface ConversionReadiness {
    ready: boolean;
    missing: { field: string; crm_value: string | null; kind: string | null; reason: string }[];
    warnings: { field: string; crm_value: string; kind: string }[];
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
    conversion_readiness: ConversionReadiness | null;
    // Ship-list CRM + pre-existing fields (Evidence Base), hidden by default (D8).
    gender: string | null;
    ethnicity: string | null;
    address: string | null;
    english_test_type: string | null;
    overall: string | number | null;
    crm_campus: string | null;
    crm_major: string | null;
    province: string | null;
    permanent_address: string | null;
    birth_place: string | null;
    nationality: string | null;
    religion: string | null;
    id_card_place_of_issue: string | null;
    school: string | null;
    graduation_year: string | null;
    gpa: string | number | null;
    gpa_type: string | null;
    scholarship: string | null;
    pathway_gateway: string | null;
    uu_dai_gc: string | null;
    crm_paid_amount: string | number | null;
    last_synced_at: string | null;
}

/** Ship-list fields with no special formatting: render the raw value or "—". */
const SIMPLE_TEXT_FIELDS = [
    'gender',
    'ethnicity',
    'address',
    'english_test_type',
    'crm_campus',
    'crm_major',
    'province',
    'permanent_address',
    'birth_place',
    'nationality',
    'religion',
    'id_card_place_of_issue',
    'school',
    'graduation_year',
    'gpa_type',
    'scholarship',
    'pathway_gateway',
    'uu_dai_gc',
] as const satisfies readonly (keyof ApplicationRow)[];

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

// Select-driven advanced filters (backend: ListApplicationsQuery::SELECT_FILTERS).
const SELECT_FILTER_FIELDS = ['gender', 'ethnicity', 'religion', 'crm_major', 'scholarship', 'pathway_gateway', 'graduation_year', 'gpa_type', 'province', 'english_test_type'] as const;
// Free-text advanced filters (backend: ListApplicationsQuery::LIKE_FILTERS).
const TEXT_FILTER_FIELDS = ['school', 'birth_place'] as const;
// Numeric range advanced filters, paired min/max.
const RANGE_FILTER_FIELDS = ['gpa_min', 'gpa_max', 'overall_min', 'overall_max', 'paid_min', 'paid_max'] as const;

type AdvancedFilterKey = (typeof SELECT_FILTER_FIELDS)[number] | (typeof TEXT_FILTER_FIELDS)[number] | (typeof RANGE_FILTER_FIELDS)[number] | 'synced';

interface Filters {
    search: string;
    status: string;
    intake: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
    // Advanced filters (phase 4) — all default to '' (FilterSelect's "all" sentinel).
    gender: string;
    ethnicity: string;
    religion: string;
    crm_major: string;
    scholarship: string;
    pathway_gateway: string;
    graduation_year: string;
    gpa_type: string;
    province: string;
    english_test_type: string;
    school: string;
    birth_place: string;
    synced: string;
    gpa_min: string;
    gpa_max: string;
    overall_min: string;
    overall_max: string;
    paid_min: string;
    paid_max: string;
}

const ADVANCED_FILTER_FIELDS: readonly AdvancedFilterKey[] = [...SELECT_FILTER_FIELDS, ...TEXT_FILTER_FIELDS, ...RANGE_FILTER_FIELDS, 'synced'];

interface Props {
    applications: ApplicationsPaginator;
    filters: Partial<Filters>;
    currentCampus: Campus | null;
    documentTypes: DocumentType[];
    intakes: string[];
    statusOptions: StatusOption[];
    filterOptions: Partial<Record<(typeof SELECT_FILTER_FIELDS)[number], string[]>>;
}

const props = defineProps<Props>();

const advancedFilterDefaults = Object.fromEntries(ADVANCED_FILTER_FIELDS.map((field) => [field, ''])) as Record<AdvancedFilterKey, string>;
const advancedFilterInitial = Object.fromEntries(ADVANCED_FILTER_FIELDS.map((field) => [field, props.filters[field] ?? ''])) as Record<AdvancedFilterKey, string>;

// Shared filter/sort/pagination plumbing (same composable as the other list pages).
const { filters, setFilter, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection, isLoading, clearAllFilters } = useDataTable<Filters>({
    baseUrl: route('student-applications.index'),
    initialFilters: {
        search: props.filters.search ?? '',
        status: props.filters.status ?? 'all',
        intake: props.filters.intake ?? 'all',
        sort: props.filters.sort ?? 'created_at',
        direction: props.filters.direction ?? 'desc',
        per_page: props.filters.per_page ?? 15,
        page: props.applications.current_page ?? 1,
        ...advancedFilterInitial,
    },
    defaultValues: {
        search: '',
        status: 'all',
        intake: 'all',
        sort: 'created_at',
        direction: 'desc',
        per_page: 15,
        page: 1,
        ...advancedFilterDefaults,
    },
    only: ['applications', 'filters'],
    // New advanced-filter selects stay on the default debounce, not immediateFields:
    // useDataTable.navigate() drops a request while one is in flight, so putting
    // ~12 selects here would make dropped navigations routine.
    immediateFields: ['status', 'intake'],
});

const data = computed(() => props.applications.data);
const showAdvancedFilters = ref(false);

// Source-order groups: identity → admission → academic → location → english → sync → finance.
// Sortable flags mirror StudentApplicationSortColumns::ALLOWED on the backend.
const columns = computed<ColumnDef<ApplicationRow>[]>(() => [
    { accessorKey: 'full_name', header: 'Applicant', enableSorting: true },
    { accessorKey: 'student_code', header: 'Code', enableSorting: true },
    { accessorKey: 'national_id', header: 'National ID', enableSorting: false },
    { accessorKey: 'phone', header: 'Phone', enableSorting: false },
    { accessorKey: 'intended_program', header: 'Program', enableSorting: true },
    { accessorKey: 'intake', header: 'Intake', enableSorting: true },
    { accessorKey: 'status', header: 'Status', enableSorting: true },
    { accessorKey: 'created_at', header: 'Created', enableSorting: true },
    // Identity
    { accessorKey: 'gender', header: 'Gender', enableSorting: true },
    { accessorKey: 'ethnicity', header: 'Ethnicity', enableSorting: false },
    { accessorKey: 'religion', header: 'Religion', enableSorting: false },
    // Admission
    { accessorKey: 'crm_campus', header: 'CRM Campus', enableSorting: true },
    { accessorKey: 'crm_major', header: 'CRM Major', enableSorting: true },
    { accessorKey: 'scholarship', header: 'Scholarship', enableSorting: false },
    { accessorKey: 'pathway_gateway', header: 'Pathway Gateway', enableSorting: false },
    // Academic
    { accessorKey: 'graduation_year', header: 'Graduation Year', enableSorting: false },
    { accessorKey: 'gpa_type', header: 'GPA Type', enableSorting: false },
    { accessorKey: 'school', header: 'School', enableSorting: true },
    { accessorKey: 'gpa', header: 'GPA', enableSorting: true },
    // Location
    { accessorKey: 'province', header: 'Province', enableSorting: true },
    { accessorKey: 'birth_place', header: 'Birth Place', enableSorting: false },
    { accessorKey: 'permanent_address', header: 'Permanent Address', enableSorting: false },
    { accessorKey: 'address', header: 'Address', enableSorting: false },
    { accessorKey: 'nationality', header: 'Nationality', enableSorting: true },
    { accessorKey: 'id_card_place_of_issue', header: 'ID Issue Place', enableSorting: false },
    // English
    { accessorKey: 'english_test_type', header: 'English Test', enableSorting: false },
    { accessorKey: 'overall', header: 'Overall Score', enableSorting: true },
    // Sync
    { accessorKey: 'last_synced_at', header: 'Last Synced', enableSorting: true },
    // Finance
    { accessorKey: 'crm_paid_amount', header: 'Paid Amount', enableSorting: true },
    { accessorKey: 'uu_dai_gc', header: 'UU/DAI/GC', enableSorting: false },
    ...props.documentTypes.map(
        (type): ColumnDef<ApplicationRow> => ({
            id: `doc_${type.code}`,
            header: () => h('span', { class: 'block max-w-[160px] truncate', title: type.name }, type.name),
            meta: { label: type.name },
            enableSorting: false,
        }),
    ),
    { id: 'actions', header: '', enableSorting: false, enableHiding: false },
]);

// Everything new starts hidden (D8): default visible set equals today's 8 +
// document columns + actions, so existing users see no change until they opt in.
const NEW_COLUMN_IDS = [...SIMPLE_TEXT_FIELDS, 'overall', 'gpa', 'crm_paid_amount', 'last_synced_at'] as const;
const defaultColumnVisibility: VisibilityState = Object.fromEntries(NEW_COLUMN_IDS.map((id) => [id, false]));
const knownColumnIds = computed(() => columns.value.map((column) => column.id ?? ('accessorKey' in column ? String(column.accessorKey) : undefined)).filter((id): id is string => !!id));
const { columnVisibility } = useTableColumnVisibility('student-applications:columns:v1', defaultColumnVisibility, knownColumnIds.value);

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

// D12: 0.00 in gpa/overall means "no data", not a score — render both as "—".
const formatScore = (value: string | number | null): string => {
    if (value === null) return '—';
    const numeric = Number(value);
    return numeric === 0 ? '—' : numeric.toFixed(2);
};

const formatVnd = (value: string | number | null): string => (value === null ? '—' : new Intl.NumberFormat('vi-VN').format(Number(value)) + '₫');

const fieldValue = (row: ApplicationRow, field: (typeof SIMPLE_TEXT_FIELDS)[number]): string => row[field] || '—';

// Documents of a given catalog type for a row (empty when none submitted).
const docsForType = (row: ApplicationRow, code: string): DocRef[] => row.documents_by_type?.[code] ?? [];

const goShow = (id: number) => router.visit(route('student-applications.show', id));
const goEdit = (id: number) => router.visit(route('student-applications.edit', id));
const goCreate = () => router.visit(route('student-applications.create'));

// Export the current (campus-scoped) view to Excel for the CRM to complete:
// missing fields come out blank. Filters always apply now (D11 removed
// `scope`), so the export matches exactly what's on screen.
const exportExcel = () => {
    const params: Record<string, string> = { format: 'xlsx' };
    if (filters.search) params.search = filters.search;
    if (filters.status && filters.status !== 'all') params.status = filters.status;
    if (filters.intake && filters.intake !== 'all') params.intake = filters.intake;
    if (filters.sort) params.sort = filters.sort;
    if (filters.direction) params.direction = filters.direction;
    for (const field of ADVANCED_FILTER_FIELDS) {
        if (filters[field]) params[field] = filters[field];
    }

    window.location.href = route('student-applications.export', params);
};

// FilterSelect expects {value,label} pairs; the backend already returns
// campus-scoped distinct values, so label === value.
const selectOptions = (field: (typeof SELECT_FILTER_FIELDS)[number]) => (props.filterOptions[field] ?? []).map((value) => ({ value, label: value }));

const syncedOptions = [
    { value: 'synced', label: 'Synced' },
    { value: 'not_synced', label: 'Not synced' },
];

const advancedFilterCount = computed(() => ADVANCED_FILTER_FIELDS.filter((field) => !!filters[field]).length);

// Open the focused documents view in a new browser tab.
const openDocuments = (id: number) => window.open(route('student-applications.documents', id), '_blank', 'noopener');

// Lifecycle actions reload the list in place (the controller redirects back) —
// they never navigate away from this page. The controller always flashes a
// success/error message and useFlashToast (mounted in AppLayout) turns that
// into the toast — approve() always redirects 200/302 even on domain failure
// (e.g. "no curriculum version"), so a toast fired from onSuccess here would
// show "approved" while the flash bridge simultaneously shows the real error.
const reloadOptions = (onError: () => void) => ({
    preserveScroll: true,
    preserveState: true,
    onError,
});

const approve = (row: ApplicationRow) => {
    router.post(route('student-applications.approve', row.id), {}, reloadOptions(() => toast.error('Failed to approve application.')));
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
    router.post(
        route('student-applications.reject', rejectTarget.value.id),
        { rejected_reason: rejectReason.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showRejectDialog.value = false;
            },
            onError: () => toast.error('Failed to reject application.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
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
            <CardContent class="space-y-3 py-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
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
                </div>

                <Collapsible v-model:open="showAdvancedFilters">
                    <div class="flex items-center justify-between">
                        <CollapsibleTrigger as-child>
                            <Button variant="ghost" size="sm" class="gap-2">
                                <SlidersHorizontal class="h-4 w-4" />
                                More filters
                                <Badge v-if="advancedFilterCount > 0" variant="secondary">{{ advancedFilterCount }}</Badge>
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': showAdvancedFilters }" />
                            </Button>
                        </CollapsibleTrigger>
                        <Button v-if="advancedFilterCount > 0" variant="ghost" size="sm" class="text-muted-foreground gap-2" @click="clearAllFilters">
                            <X class="h-4 w-4" />
                            Clear filters
                        </Button>
                    </div>

                    <CollapsibleContent class="divide-border space-y-4 divide-y pt-3">
                        <!-- Identity -->
                        <div class="space-y-2 pt-4 first:pt-0">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Identity</p>
                            <FilterPanel :columns="4">
                                <FilterSelect :model-value="filters.gender" :options="selectOptions('gender')" placeholder="Gender" all-label="All genders" @change="(v) => setFilter('gender', v)" />
                                <FilterSelect :model-value="filters.ethnicity" :options="selectOptions('ethnicity')" placeholder="Ethnicity" all-label="All ethnicities" @change="(v) => setFilter('ethnicity', v)" />
                                <FilterSelect :model-value="filters.religion" :options="selectOptions('religion')" placeholder="Religion" all-label="All religions" @change="(v) => setFilter('religion', v)" />
                            </FilterPanel>
                        </div>

                        <!-- Admission -->
                        <div class="space-y-2 pt-4">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Admission</p>
                            <FilterPanel :columns="4">
                                <FilterSelect :model-value="filters.crm_major" :options="selectOptions('crm_major')" placeholder="CRM major" all-label="All majors" @change="(v) => setFilter('crm_major', v)" />
                                <FilterSelect :model-value="filters.scholarship" :options="selectOptions('scholarship')" placeholder="Scholarship" all-label="All scholarships" @change="(v) => setFilter('scholarship', v)" />
                                <FilterSelect :model-value="filters.pathway_gateway" :options="selectOptions('pathway_gateway')" placeholder="Pathway gateway" all-label="All pathways" @change="(v) => setFilter('pathway_gateway', v)" />
                            </FilterPanel>
                        </div>

                        <!-- Academic -->
                        <div class="space-y-2 pt-4">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Academic</p>
                            <FilterPanel :columns="4">
                                <FilterSelect :model-value="filters.graduation_year" :options="selectOptions('graduation_year')" placeholder="Graduation year" all-label="All years" @change="(v) => setFilter('graduation_year', v)" />
                                <FilterSelect :model-value="filters.gpa_type" :options="selectOptions('gpa_type')" placeholder="GPA type" all-label="All GPA types" @change="(v) => setFilter('gpa_type', v)" />
                                <FilterSearchInput :model-value="filters.school" placeholder="School…" @search="(v) => setFilter('school', v)" />
                                <div class="space-y-1">
                                    <p class="text-muted-foreground text-xs">GPA range</p>
                                    <div class="flex items-center gap-1">
                                        <Input type="number" placeholder="Min" :model-value="filters.gpa_min" @update:model-value="(v) => setFilter('gpa_min', String(v))" />
                                        <span class="text-muted-foreground text-xs">–</span>
                                        <Input type="number" placeholder="Max" :model-value="filters.gpa_max" @update:model-value="(v) => setFilter('gpa_max', String(v))" />
                                    </div>
                                </div>
                            </FilterPanel>
                        </div>

                        <!-- Location -->
                        <div class="space-y-2 pt-4">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Location</p>
                            <FilterPanel :columns="4">
                                <FilterSelect :model-value="filters.province" :options="selectOptions('province')" placeholder="Province" all-label="All provinces" @change="(v) => setFilter('province', v)" />
                                <FilterSearchInput :model-value="filters.birth_place" placeholder="Birth place…" @search="(v) => setFilter('birth_place', v)" />
                            </FilterPanel>
                        </div>

                        <!-- English -->
                        <div class="space-y-2 pt-4">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">English</p>
                            <FilterPanel :columns="4">
                                <FilterSelect :model-value="filters.english_test_type" :options="selectOptions('english_test_type')" placeholder="English test" all-label="All test types" @change="(v) => setFilter('english_test_type', v)" />
                                <div class="space-y-1">
                                    <p class="text-muted-foreground text-xs">Overall range</p>
                                    <div class="flex items-center gap-1">
                                        <Input type="number" placeholder="Min" :model-value="filters.overall_min" @update:model-value="(v) => setFilter('overall_min', String(v))" />
                                        <span class="text-muted-foreground text-xs">–</span>
                                        <Input type="number" placeholder="Max" :model-value="filters.overall_max" @update:model-value="(v) => setFilter('overall_max', String(v))" />
                                    </div>
                                </div>
                            </FilterPanel>
                        </div>

                        <!-- Sync + Finance -->
                        <div class="space-y-2 pt-4">
                            <p class="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Sync &amp; finance</p>
                            <FilterPanel :columns="4">
                                <FilterSelect :model-value="filters.synced" :options="syncedOptions" placeholder="CRM sync" all-label="All" @change="(v) => setFilter('synced', v)" />
                                <div class="space-y-1">
                                    <p class="text-muted-foreground text-xs">Paid amount range</p>
                                    <div class="flex items-center gap-1">
                                        <Input type="number" placeholder="Min" :model-value="filters.paid_min" @update:model-value="(v) => setFilter('paid_min', String(v))" />
                                        <span class="text-muted-foreground text-xs">–</span>
                                        <Input type="number" placeholder="Max" :model-value="filters.paid_max" @update:model-value="(v) => setFilter('paid_max', String(v))" />
                                    </div>
                                </div>
                            </FilterPanel>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </CardContent>
        </Card>

        <!-- Table (shared DataTable + named cell slots) -->
        <DataTable
            :columns="columns"
            :data="data"
            :loading="isLoading"
            show-column-toggle
            min-width
            :column-visibility="columnVisibility"
            @update:column-visibility="(value) => (columnVisibility = value)"
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
                <div class="flex flex-wrap items-center gap-1">
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusBadgeClass(row.original.status)">
                        {{ row.original.status }}
                    </span>
                    <a
                        v-if="row.original.conversion_readiness && !row.original.conversion_readiness.ready"
                        :href="route('student-applications.crm-mappings.index')"
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 hover:bg-amber-200"
                        :title="row.original.conversion_readiness.missing.map((m) => m.crm_value ?? m.field).join(', ')"
                    >
                        Chưa map
                    </a>
                </div>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-muted-foreground whitespace-nowrap">{{ formatDate(row.original.created_at) }}</span>
            </template>

            <!-- CRM ship-list columns, hidden by default (D8). Plain text fields share one formatter. -->
            <template v-for="field in SIMPLE_TEXT_FIELDS" :key="field" #[`cell-${field}`]="{ row }">{{ fieldValue(row.original, field) }}</template>

            <template #cell-gpa="{ row }">{{ formatScore(row.original.gpa) }}</template>
            <template #cell-overall="{ row }">{{ formatScore(row.original.overall) }}</template>
            <template #cell-crm_paid_amount="{ row }">{{ formatVnd(row.original.crm_paid_amount) }}</template>
            <template #cell-last_synced_at="{ row }">{{ row.original.last_synced_at ? formatDate(row.original.last_synced_at) : '—' }}</template>

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
                    <Button v-if="row.original.status === 'pending'" size="sm" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="approve(row.original)">
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
