<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDataTable } from '@/composables/useDataTable';
import { usePermission } from '@/composables/usePermission';
import { useStudentImpersonation } from '@/composables/useStudentImpersonation';
import type { Program, Semester, Specialization, Student } from '@/types/models';
import { getStudentStatusBadgeClass, getStudentStatusDescription, getStudentStatusLabel, STUDENT_STATUS_DESCRIPTIONS, STUDENT_STATUS_LABELS, StudentStatus } from '@/types/student';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { CircleHelp, ClipboardCheck, Download, Edit, Eye, FileSpreadsheet, Filter, LogIn, RefreshCw, RotateCw, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface StudentFilters {
    search: string;
    student_ids: string[];
    program_ids: string[];
    specialization_ids: string[];
    statuses: string[];
    intake_semester_ids: string[];
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
}

interface Props {
    students: {
        current_page: number;
        data: Student[];
        first_page_url: string;
        from: number | null;
        last_page: number;
        last_page_url: string;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number | null;
        total: number;
    };
    filters: {
        search?: string;
        student_ids?: string[];
        program_ids?: Array<number | string>;
        specialization_ids?: Array<number | string>;
        statuses?: string[];
        intake_semester_ids?: Array<number | string>;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    programs: Array<Pick<Program, 'id' | 'name'>>;
    specializations: Array<Pick<Specialization, 'id' | 'program_id' | 'name' | 'code'>>;
    intake_semesters: Array<Pick<Semester, 'id' | 'name' | 'code' | 'start_date' | 'end_date'>>;
    statistics: {
        total_students: number;
        active_students: number;
        enrolled_students: number;
        graduated_students: number;
        suspended_students: number;
        on_leave_students: number;
    };
}

const props = defineProps<Props>();

// Composables
const { loginAsStudent } = useStudentImpersonation();
const { can } = usePermission();

// Export state
const showExportDialog = ref(false);
const exportForm = ref({
    format: 'xlsx',
    scope: 'filtered',
});
const isExporting = ref(false);
const showAdvancedFilters = ref(false);

// Reactive data
const data = computed(() => props.students.data);

const STUDENT_CODE_LIMIT = 100;
const studentCodeDelimiter = /[\s,;]+/;

type AdvancedFilterKey = 'program_ids' | 'specialization_ids' | 'statuses' | 'intake_semester_ids';

interface AdvancedFilterDraft {
    program_ids: string[];
    specialization_ids: string[];
    statuses: string[];
    intake_semester_ids: string[];
}

const normalizeStudentCodes = (studentIds: string[]) => {
    return [
        ...new Set(
            studentIds
                .flatMap((studentId) => studentId.split(studentCodeDelimiter))
                .map((studentId) => studentId.trim())
                .filter(Boolean),
        ),
    ].slice(0, STUDENT_CODE_LIMIT);
};

const { filters, hasActiveFilters, clearAllFilters, setFilter, apply, handleSearch, handleSortChange, handlePageSizeChange, handlePaginationNavigate, currentSort, currentDirection, isLoading } = useDataTable<StudentFilters>({
    baseUrl: studentRoutes.list(),
    initialFilters: {
        search: props.filters?.search ?? '',
        student_ids: normalizeStudentCodes(props.filters?.student_ids ?? []),
        program_ids: props.filters?.program_ids?.map(String) ?? [],
        specialization_ids: props.filters?.specialization_ids?.map(String) ?? [],
        statuses: props.filters?.statuses ?? [],
        intake_semester_ids: props.filters?.intake_semester_ids?.map(String) ?? [],
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
        page: props.students.current_page || 1,
    },
    defaultValues: {
        search: '',
        student_ids: [],
        program_ids: [],
        specialization_ids: [],
        statuses: [],
        intake_semester_ids: [],
        sort: null,
        direction: null,
        per_page: 15,
        page: 1,
    },
    only: ['students', 'filters'],
    debounce: 400,
});

const handleStudentIdsChange = (studentIds: unknown[]) => {
    setFilter('student_ids', normalizeStudentCodes(studentIds.map(String)));
};

const createAdvancedFilterDraft = (): AdvancedFilterDraft => ({
    program_ids: [...filters.program_ids],
    specialization_ids: [...filters.specialization_ids],
    statuses: [...filters.statuses],
    intake_semester_ids: [...filters.intake_semester_ids],
});

const advancedFilterDraft = ref<AdvancedFilterDraft>(createAdvancedFilterDraft());

const filteredSpecializations = computed(() => {
    if (advancedFilterDraft.value.program_ids.length === 0) {
        return props.specializations;
    }

    const programIds = new Set(advancedFilterDraft.value.program_ids);

    return props.specializations.filter((specialization) => programIds.has(String(specialization.program_id)));
});

const activeAdvancedFilterCount = computed(() => filters.program_ids.length + filters.specialization_ids.length + filters.statuses.length + filters.intake_semester_ids.length);

const activeFilterChips = computed(() => [
    ...filters.program_ids.map((value) => ({
        key: 'program_ids' as const,
        value,
        label: `Program: ${props.programs.find((program) => String(program.id) === value)?.name ?? value}`,
    })),
    ...filters.specialization_ids.map((value) => ({
        key: 'specialization_ids' as const,
        value,
        label: `Specialization: ${props.specializations.find((specialization) => String(specialization.id) === value)?.name ?? value}`,
    })),
    ...filters.statuses.map((value) => ({
        key: 'statuses' as const,
        value,
        label: `Status: ${getStudentStatusLabel(value)}`,
    })),
    ...filters.intake_semester_ids.map((value) => ({
        key: 'intake_semester_ids' as const,
        value,
        label: `Intake: ${props.intake_semesters.find((semester) => String(semester.id) === value)?.name ?? value}`,
    })),
]);

const openAdvancedFilters = () => {
    advancedFilterDraft.value = createAdvancedFilterDraft();
    showAdvancedFilters.value = true;
};

const toggleDraftFilter = (key: AdvancedFilterKey, value: string, selected: boolean | 'indeterminate') => {
    const values = advancedFilterDraft.value[key];
    const nextValues = selected === true ? [...new Set([...values, value])] : values.filter((item) => item !== value);

    advancedFilterDraft.value[key] = nextValues;

    if (key === 'program_ids' && selected !== true) {
        advancedFilterDraft.value.specialization_ids = advancedFilterDraft.value.specialization_ids.filter((specializationId) => {
            const specialization = props.specializations.find((item) => String(item.id) === specializationId);

            return specialization && String(specialization.program_id) !== value;
        });
    }
};

const resetAdvancedFilterDraft = () => {
    advancedFilterDraft.value = {
        program_ids: [],
        specialization_ids: [],
        statuses: [],
        intake_semester_ids: [],
    };
};

const applyAdvancedFilters = () => {
    apply({
        program_ids: [...advancedFilterDraft.value.program_ids],
        specialization_ids: [...advancedFilterDraft.value.specialization_ids],
        statuses: [...advancedFilterDraft.value.statuses],
        intake_semester_ids: [...advancedFilterDraft.value.intake_semester_ids],
    });
    showAdvancedFilters.value = false;
};

const removeAdvancedFilter = (key: AdvancedFilterKey, value: string) => {
    const nextFilters: Partial<StudentFilters> = {
        [key]: filters[key].filter((item) => item !== value),
    };

    if (key === 'program_ids') {
        nextFilters.specialization_ids = filters.specialization_ids.filter((specializationId) => {
            const specialization = props.specializations.find((item) => String(item.id) === specializationId);

            return specialization && String(specialization.program_id) !== value;
        });
    }

    apply(nextFilters);
};

// Column definitions with h function
const columns: ColumnDef<Student>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.students.current_page;
            const perPage = props.students.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        accessorKey: 'student_id',
        header: 'Student ID',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'font-medium' }, student.student_id);
        },
    },
    {
        accessorKey: 'full_name',
        header: 'Name',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'font-medium' }, student.full_name);
        },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        enableSorting: false,
        cell: ({ row }) => {
            const email = row.original.email;
            return h('div', { class: 'text-sm text-gray-600' }, email);
        },
    },
    {
        accessorKey: 'campus.name',
        header: 'Campus',
        enableSorting: false,
        cell: ({ row }) => {
            const campus = row.original.campus?.name;
            return campus ? h('div', { class: 'text-sm' }, campus) : h('span', { class: 'text-gray-400' }, 'No campus');
        },
    },
    {
        accessorKey: 'program.name',
        header: 'Program',
        enableSorting: false,
        cell: ({ row }) => {
            const program = row.original.program?.name;
            return program ? h('div', { class: 'text-sm' }, program) : h('span', { class: 'text-gray-400' }, 'No program');
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.status as string;
            const colorClass = getStudentStatusBadgeClass(status);
            const displayText = getStudentStatusLabel(status);
            return h(
                'span',
                {
                    class: `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`,
                },
                displayText,
            );
        },
    },
    {
        accessorKey: 'intake',
        header: 'Intake',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            // Show intake as K0, K1, K2, etc.
            return h('div', { class: 'font-medium' }, `K${student.intake}`);
        },
    },
    {
        accessorKey: 'gc_starting_level',
        header: 'GC Starting',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            // Only show for intake_pre_uni_gc students with gc_starting_level
            if (student.gc_starting_level !== null) {
                return h('div', { class: 'text-sm font-medium' }, student.gc_starting_level ? student.gc_starting_level : 'Foundation');
            }
            return h('span', { class: 'text-gray-400' }, '-');
        },
    },
    {
        accessorKey: 'gc_current_level',
        header: 'GC Current',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            // Only show for intake_pre_uni_gc students with gc_starting_level
            if (student.gc_current_level !== null) {
                return h('div', { class: 'text-sm font-medium' }, student.gc_current_level ? student.gc_current_level : 'Foundation');
            }
            return h('span', { class: 'text-gray-400' }, '-');
        },
    },
    {
        accessorKey: 'gc_total_levels',
        header: 'GC Total',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original;
            // Only show for intake_pre_uni_gc students with gc_starting_level
            if (student.gc_starting_level !== null) {
                return h('div', { class: 'text-sm font-medium' }, student.gc_total_levels?.toString() || '-');
            }
            return h('span', { class: 'text-gray-400' }, '-');
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
    },
];

// View and edit functions
const viewStudent = (student: Student) => {
    router.visit(studentRoutes.studentAcademicSummary(student.id));
};

const editStudent = (student: Student) => {
    router.visit(studentRoutes.edit(student.id));
};

// Go to student actions page
const goToStudentActions = (student: Student) => {
    router.visit(studentRoutes.studentStatusActionIndex(student.id));
};
const goToStudentPlacementAndProgression = (student: Student) => {
    router.visit(studentRoutes.studentPlacement(student.id));
};

// Export functions
const openExportDialog = () => {
    showExportDialog.value = true;
};

const closeExportDialog = () => {
    showExportDialog.value = false;
};

const getStatusDisplayText = (status: string) => getStudentStatusLabel(status);

// Status list for help section
const statusList = computed(() => {
    return Object.values(StudentStatus).map((status) => ({
        value: status,
        label: STUDENT_STATUS_LABELS[status],
        description: STUDENT_STATUS_DESCRIPTIONS[status],
        badgeClass: getStudentStatusBadgeClass(status),
    }));
});

// Popover state for help icon
const helpPopoverOpen = ref(false);

const exportStudents = async () => {
    isExporting.value = true;

    try {
        const params = new URLSearchParams();

        // Add export parameters
        params.set('format', exportForm.value.format);
        params.set('scope', exportForm.value.scope);

        // Add current filters if exporting filtered results
        if (exportForm.value.scope === 'filtered') {
            if (filters.search) params.set('search', filters.search);
            filters.student_ids.forEach((studentId) => params.append('student_ids[]', studentId));
            filters.program_ids.forEach((programId) => params.append('program_ids[]', programId));
            filters.specialization_ids.forEach((specializationId) => params.append('specialization_ids[]', specializationId));
            filters.statuses.forEach((status) => params.append('statuses[]', status));
            filters.intake_semester_ids.forEach((semesterId) => params.append('intake_semester_ids[]', semesterId));
        }

        // Create download URL
        const exportUrl = `${studentRoutes.export()}?${params.toString()}`;

        // Get CSRF token from meta tag or cookie
        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];

        // Use fetch to download with authentication
        const response = await fetch(exportUrl, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
                Accept: exportForm.value.format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
        });

        if (!response.ok) {
            throw new Error(`Export failed: ${response.statusText}`);
        }

        // Get the filename from the response headers
        const contentDisposition = response.headers.get('content-disposition');
        let filename = `students.${exportForm.value.format}`;
        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename="?(.+?)"?(?:;|$)/);
            if (filenameMatch) {
                filename = filenameMatch[1];
            }
        }

        // Create a blob from the response
        const blob = await response.blob();

        // Create a download link and trigger it
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();

        // Clean up
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        // Close dialog and show success message
        closeExportDialog();
        toast.success(`Export completed! Your ${exportForm.value.format.toUpperCase()} file has been downloaded.`);
    } catch (error) {
        console.error('Export error:', error);
        toast.error(error instanceof Error ? error.message : 'Failed to export students. Please try again.');
    } finally {
        isExporting.value = false;
    }
};
</script>

<template>
    <Head title="Students Management" />

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold">Students Management</h1>
                <p class="text-sm text-gray-500">Manage all students in the system</p>
            </div>
            <div class="flex items-center space-x-2">
                <Popover v-model:open="helpPopoverOpen">
                    <PopoverTrigger as-child>
                        <Button variant="ghost" size="icon" class="h-9 w-9" @mouseenter="helpPopoverOpen = true" @mouseleave="helpPopoverOpen = false">
                            <CircleHelp class="h-4 w-4" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="max-h-[500px] w-96 overflow-y-auto p-4" side="bottom" align="end" @mouseenter="helpPopoverOpen = true" @mouseleave="helpPopoverOpen = false">
                        <div class="space-y-3">
                            <h4 class="text-sm font-semibold">Giải thích các trạng thái sinh viên</h4>
                            <div class="space-y-2">
                                <div v-for="status in statusList" :key="status.value" class="flex items-start gap-2 rounded-md border bg-gray-50 p-2">
                                    <span :class="`inline-flex flex-shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium ${status.badgeClass}`">
                                        {{ status.label }}
                                    </span>
                                    <p class="text-sm text-gray-600">{{ status.description }}</p>
                                </div>
                            </div>
                        </div>
                    </PopoverContent>
                </Popover>
                <Button @click="openExportDialog()" variant="outline" class="border-gray-300">
                    <Download class="mr-2 h-4 w-4" />
                    Export
                </Button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardContent class="p-4">
                    <h3 class="text-sm font-medium text-gray-500">Total Students</h3>
                    <p class="text-2xl font-semibold">{{ statistics.total_students }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <h3 class="text-sm font-medium text-gray-500">Active Students</h3>
                    <p class="text-2xl font-semibold">{{ statistics.active_students }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <h3 class="text-sm font-medium text-gray-500">Graduated Students</h3>
                    <p class="text-2xl font-semibold">{{ statistics.graduated_students }}</p>
                </CardContent>
            </Card>
        </div>

        <Card class="mt-4">
            <CardContent class="p-4">
                <div class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex w-full items-center gap-2 md:w-auto">
                        <DebouncedInput :model-value="filters.search" placeholder="Search by name, email or student ID..." class="w-full md:w-64" :disabled="isLoading" @update:model-value="handleSearch" />
                        <Button variant="outline" :disabled="isLoading" @click="openAdvancedFilters">
                            <Filter class="mr-2 h-4 w-4" />
                            Bộ lọc nâng cao
                            <Badge v-if="activeAdvancedFilterCount" variant="secondary" class="ml-2">{{ activeAdvancedFilterCount }}</Badge>
                        </Button>
                        <Button v-if="hasActiveFilters" variant="ghost" :disabled="isLoading" @click="clearAllFilters">
                            <X class="mr-2 h-4 w-4" />
                            Xóa tất cả
                        </Button>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <Label for="student-code-filter" class="text-sm font-medium">Mã SV</Label>
                    <TagsInput
                        id="student-code-filter"
                        :model-value="filters.student_ids"
                        :delimiter="studentCodeDelimiter"
                        :max="STUDENT_CODE_LIMIT"
                        add-on-paste
                        add-on-blur
                        add-on-tab
                        :disabled="isLoading"
                        class="min-h-10"
                        @update:model-value="handleStudentIdsChange"
                    >
                        <TagsInputItem v-for="studentId in filters.student_ids" :key="studentId" :value="studentId">
                            <TagsInputItemText />
                            <TagsInputItemDelete />
                        </TagsInputItem>
                        <TagsInputInput placeholder="Paste nhiều Mã SV, phân tách bằng dấu phẩy hoặc xuống dòng..." :max-length="20" />
                    </TagsInput>
                    <p class="text-muted-foreground text-xs">Tối đa {{ STUDENT_CODE_LIMIT }} mã. Kết quả khớp chính xác theo Mã SV trong campus hiện tại.</p>
                </div>
                <div v-if="activeFilterChips.length" class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="text-muted-foreground text-xs font-medium">Đang lọc:</span>
                    <Badge v-for="chip in activeFilterChips" :key="`${chip.key}-${chip.value}`" variant="secondary" class="gap-1 pr-1">
                        {{ chip.label }}
                        <button type="button" class="hover:bg-muted-foreground/20 rounded-sm p-0.5" :aria-label="`Bỏ ${chip.label}`" :disabled="isLoading" @click="removeAdvancedFilter(chip.key, chip.value)">
                            <X class="h-3 w-3" />
                        </button>
                    </Badge>
                </div>
                <div class="mt-4">
                    <DataTable :columns="columns" :data="data" :loading="isLoading" enable-server-sorting :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange">
                        <template #cell-status="{ row }">
                            <TooltipProvider :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <span :class="`inline-flex cursor-help items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getStudentStatusBadgeClass(row.original.status as string)}`">
                                            {{ getStudentStatusLabel(row.original.status as string) }}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent class="max-w-xs">
                                        <p>{{ getStudentStatusDescription(row.original.status as string) }}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </template>
                        <template #cell-actions="{ row }">
                            <div class="flex items-center space-x-1">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="viewStudent(row.original)">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> View </TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="editStudent(row.original)">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> Edit </TooltipContent>
                                    </Tooltip>
                                    <Tooltip v-if="can('change_student_status')">
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="goToStudentPlacementAndProgression(row.original)">
                                                <RotateCw class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>EGC Placement & Progression</TooltipContent>
                                    </Tooltip>
                                    <Tooltip v-if="can('view_student_action')">
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="goToStudentActions(row.original)">
                                                <ClipboardCheck class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> View Student Actions </TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="loginAsStudent(row.original)">
                                                <LogIn class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> Login as Student </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </CardContent>
        </Card>

        <DataPagination :pagination-data="students" item-name="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>

    <Sheet v-model:open="showAdvancedFilters">
        <SheetContent class="w-full gap-0 sm:max-w-lg">
            <SheetHeader class="border-b">
                <SheetTitle>Bộ lọc nâng cao</SheetTitle>
                <SheetDescription>Chọn nhiều giá trị rồi áp dụng một lần. Các bộ lọc được kết hợp với tìm kiếm và Mã SV hiện tại.</SheetDescription>
            </SheetHeader>

            <ScrollArea class="min-h-0 flex-1">
                <div class="space-y-6 p-4">
                    <section class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold">Program</h3>
                            <p class="text-muted-foreground text-xs">Chọn một hoặc nhiều chương trình.</p>
                        </div>
                        <div class="space-y-2">
                            <label v-for="program in programs" :key="program.id" :for="`advanced-program-${program.id}`" class="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm hover:bg-gray-50">
                                <Checkbox
                                    :id="`advanced-program-${program.id}`"
                                    :model-value="advancedFilterDraft.program_ids.includes(String(program.id))"
                                    @update:model-value="(selected) => toggleDraftFilter('program_ids', String(program.id), selected)"
                                />
                                <span>{{ program.name }}</span>
                            </label>
                        </div>
                    </section>

                    <Separator />

                    <section class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold">Specialization</h3>
                            <p class="text-muted-foreground text-xs">Danh sách được giới hạn theo Program đã chọn.</p>
                        </div>
                        <div class="space-y-2">
                            <label
                                v-for="specialization in filteredSpecializations"
                                :key="specialization.id"
                                :for="`advanced-specialization-${specialization.id}`"
                                class="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm hover:bg-gray-50"
                            >
                                <Checkbox
                                    :id="`advanced-specialization-${specialization.id}`"
                                    :model-value="advancedFilterDraft.specialization_ids.includes(String(specialization.id))"
                                    @update:model-value="(selected) => toggleDraftFilter('specialization_ids', String(specialization.id), selected)"
                                />
                                <span
                                    >{{ specialization.name }} <span class="text-muted-foreground">({{ specialization.code }})</span></span
                                >
                            </label>
                            <p v-if="filteredSpecializations.length === 0" class="text-muted-foreground text-sm">Không có specialization phù hợp.</p>
                        </div>
                    </section>

                    <Separator />

                    <section class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold">Status</h3>
                            <p class="text-muted-foreground text-xs">Chọn các trạng thái sinh viên cần hiển thị.</p>
                        </div>
                        <div class="space-y-2">
                            <label v-for="status in statusList" :key="status.value" :for="`advanced-status-${status.value}`" class="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm hover:bg-gray-50">
                                <Checkbox :id="`advanced-status-${status.value}`" :model-value="advancedFilterDraft.statuses.includes(status.value)" @update:model-value="(selected) => toggleDraftFilter('statuses', status.value, selected)" />
                                <span :class="`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${status.badgeClass}`">{{ status.label }}</span>
                            </label>
                        </div>
                    </section>

                    <Separator />

                    <section class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold">Intake semester</h3>
                            <p class="text-muted-foreground text-xs">Lọc theo kỳ nhập học của sinh viên.</p>
                        </div>
                        <div class="space-y-2">
                            <label v-for="semester in intake_semesters" :key="semester.id" :for="`advanced-intake-${semester.id}`" class="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm hover:bg-gray-50">
                                <Checkbox
                                    :id="`advanced-intake-${semester.id}`"
                                    :model-value="advancedFilterDraft.intake_semester_ids.includes(String(semester.id))"
                                    @update:model-value="(selected) => toggleDraftFilter('intake_semester_ids', String(semester.id), selected)"
                                />
                                <span
                                    >{{ semester.name }} <span class="text-muted-foreground">({{ semester.code }})</span></span
                                >
                            </label>
                        </div>
                    </section>
                </div>
            </ScrollArea>

            <SheetFooter class="border-t sm:flex-row sm:justify-between">
                <Button variant="ghost" type="button" @click="resetAdvancedFilterDraft">Đặt lại</Button>
                <Button type="button" @click="applyAdvancedFilters">Áp dụng</Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>

    <!-- Export Dialog -->
    <Dialog v-model:open="showExportDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FileSpreadsheet class="h-5 w-5" />
                    Export Students
                </DialogTitle>
                <DialogDescription> Choose export format and scope for your data export </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div>
                    <Label class="text-sm font-medium">Export Format</Label>
                    <Select v-model="exportForm.format">
                        <SelectTrigger>
                            <SelectValue placeholder="Select format" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="xlsx">
                                <div class="flex items-center">
                                    <FileSpreadsheet class="mr-2 h-4 w-4 text-green-600" />
                                    Excel (.xlsx)
                                </div>
                            </SelectItem>
                            <SelectItem value="csv">
                                <div class="flex items-center">
                                    <FileSpreadsheet class="mr-2 h-4 w-4 text-blue-600" />
                                    CSV (.csv)
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Label class="text-sm font-medium">Export Scope</Label>
                    <Select v-model="exportForm.scope">
                        <SelectTrigger>
                            <SelectValue placeholder="Select scope" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="filtered">
                                <div>
                                    <div class="font-medium">Current Filtered Results</div>
                                    <div class="text-muted-foreground text-xs">
                                        <span v-if="students.total">({{ students.total }} records)</span>
                                    </div>
                                </div>
                            </SelectItem>
                            <SelectItem value="all">
                                <div>
                                    <div class="font-medium">All Students</div>
                                    <div class="text-muted-foreground text-xs">Export all students from current campus</div>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="bg-muted rounded-lg p-3">
                    <div class="mb-2 text-sm font-medium">Export Information</div>
                    <ul class="text-muted-foreground space-y-1 text-xs">
                        <li v-if="exportForm.scope === 'filtered' && filters.search">• Search: "{{ filters.search }}"</li>
                        <li v-if="exportForm.scope === 'filtered' && filters.student_ids.length">• Mã SV: {{ filters.student_ids.length }} selected</li>
                        <li v-if="exportForm.scope === 'filtered' && filters.program_ids.length">• Program: {{ filters.program_ids.length }} selected</li>
                        <li v-if="exportForm.scope === 'filtered' && filters.specialization_ids.length">• Specialization: {{ filters.specialization_ids.length }} selected</li>
                        <li v-if="exportForm.scope === 'filtered' && filters.statuses.length">• Status: {{ filters.statuses.map(getStatusDisplayText).join(', ') }}</li>
                        <li v-if="exportForm.scope === 'filtered' && filters.intake_semester_ids.length">• Intake semester: {{ filters.intake_semester_ids.length }} selected</li>
                        <li v-if="exportForm.scope === 'all'">• All students from current campus will be exported</li>
                        <li>• Format: {{ exportForm.format === 'xlsx' ? 'Excel (.xlsx)' : 'CSV (.csv)' }}</li>
                    </ul>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeExportDialog" :disabled="isExporting"> Cancel </Button>
                <Button @click="exportStudents" :disabled="isExporting">
                    <Download v-if="!isExporting" class="mr-2 h-4 w-4" />
                    <RefreshCw v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
                    {{ isExporting ? 'Exporting...' : 'Export' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
