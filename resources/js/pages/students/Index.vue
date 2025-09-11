<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApiRequest';
import { toast } from 'vue-sonner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useGlobalConfirmDialog } from '@/composables';
import type { Program, Student, CourseOffering } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Download, Edit, Eye, FileSpreadsheet, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

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
        course_offering_id?: number;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    // campuses: Campus[];
    programs: Program[];
    courseOfferings?: CourseOffering[];
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
console.log(props.students);

// Global confirm dialog composable
const confirmDialog = useGlobalConfirmDialog();

// API composable
const api = useApi();

// Export state
const showExportDialog = ref(false);
const exportForm = ref({
    format: 'xlsx',
    scope: 'filtered',
});
const isExporting = ref(false);

// Reactive data
const data = computed(() => props.students.data);

const filters = ref({
    search: props.filters.search || '',
    campus_id: props.filters.campus_id ? props.filters.campus_id.toString() : 'all',
    program_id: props.filters.program_id ? props.filters.program_id.toString() : 'all',
    status: props.filters.status || 'all',
    course_offering_id: props.filters.course_offering_id ? props.filters.course_offering_id.toString() : 'all',
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
            const status = row.original.status;
            const statusColors: Record<string, string> = {
                active: 'bg-green-100 text-green-800',
                admitted: 'bg-yellow-100 text-yellow-800',
                inactive: 'bg-gray-100 text-gray-800',
                suspended: 'bg-red-100 text-red-800',
                graduated: 'bg-blue-100 text-blue-800',
                dropped_out: 'bg-red-100 text-red-800',
            };
            const colorClass = statusColors[status] || 'bg-gray-100 text-gray-800';
            const displayText = status ? status.replace('_', ' ').toUpperCase() : 'Unknown';

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
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
    },
];

// Search handler for DebouncedInput
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    updateFilters();
};

const handleFilter = () => {
    updateFilters();
};

const clearFilters = () => {
    filters.value = {
        search: '',
        campus_id: 'all',
        program_id: 'all',
        status: 'all',
        course_offering_id: 'all',
    };
    router.visit(studentRoutes.list(), {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || filters.value.campus_id !== 'all' || filters.value.program_id !== 'all' || filters.value.status !== 'all' || filters.value.course_offering_id !== 'all';
});

// View and edit functions
const viewStudent = (student: Student) => {
    router.visit(studentRoutes.studentAcademicSummary(student.id));
};

const editStudent = (student: Student) => {
    router.visit(studentRoutes.edit(student.id));
};

const deleteStudent = (student: Student) => {
    const deleteRoute = route('students.destroy', student.id);

    confirmDialog.confirmDelete(student.full_name, 'student', () => {
        router.delete(deleteRoute, {
            onSuccess: () => {
                console.log('Student deleted successfully');
            },
        });
    });
};

// Pagination handlers
const handlePageChange = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = {
        ...getFilterParams(),
        per_page: pageSize.toString(),
    };

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });

    const url = `${studentRoutes.list()}${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

const getFilterParams = () => {
    return {
        search: filters.value.search,
        campus_id: filters.value.campus_id === 'all' ? '' : filters.value.campus_id,
        program_id: filters.value.program_id === 'all' ? '' : filters.value.program_id,
        status: filters.value.status === 'all' ? '' : filters.value.status,
        course_offering_id: filters.value.course_offering_id === 'all' ? '' : filters.value.course_offering_id,
    };
};

const updateFilters = () => {
    const params = getFilterParams();

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });

    const url = `${studentRoutes.list()}${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

const handlePaginationNavigate = (url: string) => {
    console.log(url);

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students'],
    });
};

// Export functions
const openExportDialog = () => {
    showExportDialog.value = true;
};

const closeExportDialog = () => {
    showExportDialog.value = false;
};

const getStatusDisplayText = (status: string) => {
    const statusMap: Record<string, string> = {
        'active': 'Active',
        'inactive': 'Inactive', 
        'suspended': 'Suspended',
        'graduated': 'Graduated',
        'intake_pre_uni_gc': 'Intake Pre-Uni GC',
        'intake_course': 'Intake Course',
        'deferred': 'Deferred',
        'dropout': 'Dropout',
        'dropout_transfer': 'Dropout Transfer', 
        'pending': 'Pending'
    };
    return statusMap[status] || status;
};

const exportStudents = async () => {
    isExporting.value = true;

    try {
        const params = new URLSearchParams();

        // Add export parameters
        params.set('format', exportForm.value.format);
        params.set('scope', exportForm.value.scope);

        // Add current filters if exporting filtered results
        if (exportForm.value.scope === 'filtered') {
            if (filters.value.search) params.set('search', filters.value.search);
            if (filters.value.program_id !== 'all') params.set('program_id', filters.value.program_id);
            if (filters.value.status !== 'all') params.set('status', filters.value.status);
            if (filters.value.course_offering_id !== 'all') params.set('course_offering_id', filters.value.course_offering_id);
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
                <Button @click="openExportDialog()" variant="outline" class="border-gray-300">
                    <Download class="mr-2 h-4 w-4" />
                    Export
                </Button>
                <Link :href="studentRoutes.create()">
                    <Button> <Plus class="mr-2 h-4 w-4" /> Add Student </Button>
                </Link>
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
                        <DebouncedInput v-model="filters.search" placeholder="Search by name, email or student ID..." class="w-full md:w-64" @debounced="handleSearch" />
                        <!-- <Select v-model="filters.campus_id" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by campus..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Campuses</SelectItem>
                                <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select> -->
                        <Select v-model="filters.program_id" @update:model-value="handleFilter">
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
                        <Select v-model="filters.status" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by status..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                                <SelectItem value="suspended">Suspended</SelectItem>
                                <SelectItem value="graduated">Graduated</SelectItem>
                                <SelectItem value="intake_pre_uni_gc">Intake Pre-Uni GC</SelectItem>
                                <SelectItem value="intake_course">Intake Course</SelectItem>
                                <SelectItem value="deferred">Deferred</SelectItem>
                                <SelectItem value="dropout">Dropout</SelectItem>
                                <SelectItem value="dropout_transfer">Dropout Transfer</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-if="courseOfferings && courseOfferings.length > 0" v-model="filters.course_offering_id" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by course..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Courses</SelectItem>
                                <SelectItem v-for="courseOffering in courseOfferings" :key="courseOffering.id" :value="courseOffering.id.toString()">
                                    {{ courseOffering.unit?.code }} - {{ courseOffering.unit?.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button v-if="hasActiveFilters" variant="ghost" @click="clearFilters">
                            <X class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </div>
                <div class="mt-4">
                    <DataTable :columns="columns" :data="data" :total="students.total" @page-changed="handlePageChange">
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
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="deleteStudent(row.original)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> Delete </TooltipContent>
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
                        <li v-if="exportForm.scope === 'filtered' && filters.program_id !== 'all'">
                            • Program: {{ programs.find(p => p.id.toString() === filters.program_id)?.name || 'Unknown' }}
                        </li>
                        <li v-if="exportForm.scope === 'filtered' && filters.status !== 'all'">• Status: {{ getStatusDisplayText(filters.status) }}</li>
                        <li v-if="exportForm.scope === 'filtered' && filters.course_offering_id !== 'all' && courseOfferings">
                            • Course: {{ courseOfferings.find(c => c.id.toString() === filters.course_offering_id)?.unit?.code }} - {{ courseOfferings.find(c => c.id.toString() === filters.course_offering_id)?.unit?.name }}
                        </li>
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
