<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import ChangeStudentStatusDialog from '@/components/students/ChangeStudentStatusDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { usePermission } from '@/composables/usePermission';
import { useStudentImpersonation } from '@/composables/useStudentImpersonation';
import type { Program, Student } from '@/types/models';
import { getStudentStatusBadgeClass, getStudentStatusDescription, getStudentStatusLabel, STUDENT_STATUS_DESCRIPTIONS, STUDENT_STATUS_LABELS, StudentStatus } from '@/types/student';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { CircleHelp, ClipboardCheck, Download, Edit, Eye, FileSpreadsheet, LogIn, RefreshCw, RotateCw, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface StudentFilters {
    search: string;
    campus_id: string;
    program_id: string;
    status: string;
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
        campus_id?: number;
        program_id?: number;
        status?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    // campuses: Campus[];
    programs: Program[];
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

// Change Status Dialog state
const showChangeDialog = ref(false);
const selectedStudent = ref<Student | null>(null);

// Export state
const showExportDialog = ref(false);
const exportForm = ref({
    format: 'xlsx',
    scope: 'filtered',
});
const isExporting = ref(false);

// Reactive data
const data = computed(() => props.students.data);

// Use Inertia Filters composable
const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handleSortChange, handlePageSizeChange, handlePaginationNavigate, currentSort, currentDirection } = useInertiaFilters<StudentFilters>({
    baseUrl: studentRoutes.list(),
    initialFilters: {
        search: props.filters.search || '',
        campus_id: props.filters?.campus_id?.toString() || 'all',
        program_id: props.filters?.program_id?.toString() || 'all',
        status: props.filters?.status || 'all',
        sort: props.filters?.sort || null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
        page: 1,
    },
    emptyFilters: {
        search: '',
        campus_id: 'all',
        program_id: 'all',
        status: 'all',
        sort: null,
        direction: null,
        per_page: 15,
        page: 1,
    },
    defaultValues: {
        campus_id: 'all',
        program_id: 'all',
        status: 'all',
        per_page: 15,
        direction: 'asc',
        page: 1,
    },
    only: ['students', 'filters'],
    debounce: 400,
});

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

// Change status dialog functions
const openChangeDialog = (student: Student) => {
    selectedStudent.value = student;
    showChangeDialog.value = true;
};
// Go to student actions page
const goToStudentActions = (student: Student) => {
    router.visit(studentRoutes.studentStatusActionIndex(student.id));
};
const closeChangeDialog = () => {
    showChangeDialog.value = false;
    selectedStudent.value = null;
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
            if (filters.program_id !== 'all') params.set('program_id', filters.program_id);
            if (filters.status !== 'all') params.set('status', filters.status);
        }

        // Create download URL
        const exportUrl = `/students/export?${params.toString()}`;

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
                        <DebouncedInput :model-value="filters.search" placeholder="Search by name, email or student ID..." class="w-full md:w-64" @update:model-value="handleSearch" />
                        <Select v-model="filters.program_id" @update:model-value="(v) => handleSelectFilter('program_id', v)">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by program..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Programs</SelectItem>
                                <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                    {{ program.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="filters.status" @update:model-value="(v) => handleSelectFilter('status', v)">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by status..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="suspended">Suspended</SelectItem>
                                <SelectItem value="graduated">Graduated</SelectItem>
                                <SelectItem value="intake_pre_uni_gc">Intake Pre-Uni GC</SelectItem>
                                <SelectItem value="intake_course">Intake Course</SelectItem>
                                <SelectItem value="deferred">Deferred</SelectItem>
                                <SelectItem value="admission_deferred">Admission Deferred</SelectItem>
                                <SelectItem value="dropout">Dropout</SelectItem>
                                <SelectItem value="dropout_transfer">Dropout Transfer</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button v-if="hasActiveFilters" variant="ghost" @click="clearFilters">
                            <X class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </div>
                <div class="mt-4">
                    <DataTable :columns="columns" :data="data" enable-server-sorting :initial-sort="currentSort" :initial-direction="currentDirection" @sort-change="handleSortChange">
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
                                            <Button variant="ghost" size="icon" @click="openChangeDialog(row.original)">
                                                <RotateCw class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> Change Status/GC Level </TooltipContent>
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
                        <li v-if="exportForm.scope === 'filtered' && filters.program_id !== 'all'">• Program: {{ programs.find((p) => p.id.toString() === filters.program_id)?.name || 'Unknown' }}</li>
                        <li v-if="exportForm.scope === 'filtered' && filters.status !== 'all'">• Status: {{ getStatusDisplayText(filters.status) }}</li>
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

    <!-- Change Status/GC Level Dialog -->
    <ChangeStudentStatusDialog v-if="selectedStudent" :open="showChangeDialog" :student="selectedStudent" @close="closeChangeDialog" @success="closeChangeDialog" />
</template>
