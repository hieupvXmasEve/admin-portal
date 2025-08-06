<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { AlertCircle, CheckCircle2, ChevronDown, Clock, Edit, Eye, FileCheck, RefreshCw, Trash2, Users, XCircle } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

// Types
interface StudentApplication {
    id: number;
    full_name: string;
    email: string;
    phone: string;
    campus_code: string;
    status: 'pending' | 'reviewed' | 'approved' | 'rejected';
    created_at: string;
    updated_at: string;
    student?: {
        id: number;
        student_code: string;
        full_name: string;
    };
}

interface FilterOption {
    value: string;
    label: string;
}

interface Campus {
    code: string;
    name: string;
}

interface Props {
    applications: {
        data: StudentApplication[];
        from: number;
        to: number;
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        per_page: number;
    };
    filters: {
        search: string;
        status: string;
        campus: string;
        per_page: number;
        sort: string;
        direction: 'asc' | 'desc';
    };
    campuses: Campus[];
    statusOptions: FilterOption[];
}

const props = defineProps<Props>();
const page = usePage();

// State
const selectedApplications = ref<StudentApplication[]>([]);
const showBatchConversionDialog = ref(false);
const showStatusUpdateDialog = ref(false);
const currentApplication = ref<StudentApplication | null>(null);
const isLoading = ref(false);

// Form state for conversions
const conversionForm = ref({
    program_id: '',
    curriculum_version_id: '',
    specialization_id: '',
    admission_date: format(new Date(), 'yyyy-MM-dd'),
    expected_graduation_date: '',
});

const statusForm = ref({
    status: '',
});

// Conversion options
const conversionOptions = ref({
    programs: [] as Array<{ id: number; name: string; code: string }>,
    curriculumVersions: [] as Array<{ id: number; version_name: string; effective_date: string }>,
    specializations: [] as Array<{ id: number; name: string }>,
});

const loadingOptions = ref(false);

// Computed
const hasSelectedApplications = computed(() => selectedApplications.value.length > 0);
const canConvertSelected = computed(() => selectedApplications.value.every((app) => app.status === 'approved' && !app.student));

// Status badge configuration
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return { variant: 'outline', icon: Clock, class: 'text-yellow-600 border-yellow-300' };
        case 'reviewed':
            return { variant: 'outline', icon: AlertCircle, class: 'text-blue-600 border-blue-300' };
        case 'approved':
            return { variant: 'outline', icon: CheckCircle2, class: 'text-green-600 border-green-300' };
        case 'rejected':
            return { variant: 'outline', icon: XCircle, class: 'text-red-600 border-red-300' };
        default:
            return { variant: 'outline', icon: Clock, class: 'text-gray-600 border-gray-300' };
    }
};

// Table columns
const columns: ColumnDef<StudentApplication>[] = [
    {
        id: 'select',
        header: ({ table }) =>
            h(Checkbox, {
                checked: table.getIsAllPageRowsSelected(),
                'onUpdate:checked': (value: boolean) => table.toggleAllPageRowsSelected(!!value),
                ariaLabel: 'Select all',
            }),
        cell: ({ row }) =>
            h(Checkbox, {
                checked: row.getIsSelected(),
                'onUpdate:checked': (value: boolean) => row.toggleSelected(!!value),
                ariaLabel: 'Select row',
            }),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: 'full_name',
        header: 'Full Name',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'font-medium' }, application.full_name);
        },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm text-muted-foreground' }, application.email);
        },
    },
    {
        accessorKey: 'phone',
        header: 'Phone',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.phone);
        },
    },
    {
        accessorKey: 'campus_code',
        header: 'Campus',
        cell: ({ row }) => {
            const application = row.original;
            const campus = props.campuses.find((c) => c.code === application.campus_code);
            return h('div', { class: 'text-sm' }, campus?.name || application.campus_code);
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const application = row.original;
            const badgeConfig = getStatusBadge(application.status);
            return h(
                Badge,
                {
                    variant: badgeConfig.variant as any,
                    class: badgeConfig.class,
                },
                () => [h(badgeConfig.icon, { class: 'w-3 h-3 mr-1' }), application.status.charAt(0).toUpperCase() + application.status.slice(1)],
            );
        },
    },
    {
        accessorKey: 'student',
        header: 'Student',
        cell: ({ row }) => {
            const application = row.original;
            if (application.student) {
                return h('div', { class: 'text-sm' }, [h('div', { class: 'font-medium text-green-600' }, application.student.student_code), h('div', { class: 'text-xs text-muted-foreground' }, 'Converted')]);
            }
            return h('span', { class: 'text-xs text-muted-foreground' }, 'Not converted');
        },
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm text-muted-foreground' }, format(new Date(application.created_at), 'MMM dd, yyyy'));
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const application = row.original;
            return h(
                DropdownMenu,
                {},
                {
                    default: () => [
                        h(DropdownMenuTrigger, { asChild: true }, () => h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () => h(ChevronDown, { class: 'h-4 w-4' }))),
                        h(DropdownMenuContent, { align: 'end' }, () => [
                            h(DropdownMenuLabel, {}, () => 'Actions'),
                            h(
                                DropdownMenuItem,
                                {
                                    onClick: () => router.visit(`/student-applications/${application.id}`),
                                },
                                () => [h(Eye, { class: 'mr-2 h-4 w-4' }), 'View'],
                            ),
                            h(
                                DropdownMenuItem,
                                {
                                    onClick: () => router.visit(`/student-applications/${application.id}/edit`),
                                },
                                () => [h(Edit, { class: 'mr-2 h-4 w-4' }), 'Edit'],
                            ),
                            h(DropdownMenuSeparator, {}),
                            h(
                                DropdownMenuItem,
                                {
                                    onClick: () => openStatusDialog(application),
                                },
                                () => [h(RefreshCw, { class: 'mr-2 h-4 w-4' }), 'Change Status'],
                            ),
                            ...(application.status === 'approved' && !application.student
                                ? [
                                      h(
                                          DropdownMenuItem,
                                          {
                                              onClick: () => openConversionDialog(application),
                                          },
                                          () => [h(Users, { class: 'mr-2 h-4 w-4' }), 'Convert to Student'],
                                      ),
                                  ]
                                : []),
                            h(DropdownMenuSeparator, {}),
                            h(
                                DropdownMenuItem,
                                {
                                    onClick: () => deleteApplication(application),
                                    class: 'text-red-600',
                                    disabled: !!application.student,
                                },
                                () => [h(Trash2, { class: 'mr-2 h-4 w-4' }), 'Delete'],
                            ),
                        ]),
                    ],
                },
            );
        },
        enableSorting: false,
        enableHiding: false,
    },
];

// Filter functions
const applyFilters = (newFilters: Partial<typeof props.filters>) => {
    const params = new URLSearchParams();

    Object.entries({ ...props.filters, ...newFilters }).forEach(([key, value]) => {
        if (value && value !== '') {
            params.set(key, value.toString());
        }
    });

    router.visit(`/student-applications${params.toString() ? '?' + params : ''}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['applications', 'filters'],
    });
};

const onSearch = (value: string) => {
    applyFilters({ search: value });
};

const onStatusFilter = (value: string) => {
    applyFilters({ status: value === 'all' ? '' : value });
};

const onCampusFilter = (value: string) => {
    applyFilters({ campus: value === 'all' ? '' : value });
};

const onPageSizeChange = (size: number) => {
    applyFilters({ per_page: size });
};

const onNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['applications', 'filters'],
    });
};

// Selection handlers
const onSelectionChange = (selected: StudentApplication[]) => {
    selectedApplications.value = selected;
};

// Dialog functions
const openStatusDialog = (application: StudentApplication) => {
    currentApplication.value = application;
    statusForm.value.status = application.status;
    showStatusUpdateDialog.value = true;
};

const openConversionDialog = async (application?: StudentApplication) => {
    if (application) {
        selectedApplications.value = [application];
    }
    showBatchConversionDialog.value = true;
    await loadConversionOptions();
};

// Load conversion options
const loadConversionOptions = async () => {
    try {
        loadingOptions.value = true;
        const response = await fetch('/student-applications/api/conversion-options');
        const data = await response.json();
        conversionOptions.value.programs = data.programs || [];
    } catch (error) {
        console.error('Failed to load conversion options:', error);
        toast.error('Failed to load conversion options. Please try again.');
    } finally {
        loadingOptions.value = false;
    }
};

const loadCurriculumVersions = async (programId: string) => {
    if (!programId) {
        conversionOptions.value.curriculumVersions = [];
        return;
    }

    try {
        const response = await fetch(`/student-applications/api/conversion-options?program_id=${programId}`);
        const data = await response.json();
        conversionOptions.value.curriculumVersions = data.curriculumVersions || [];
    } catch (error) {
        console.error('Failed to load curriculum versions:', error);
        toast.error('Failed to load curriculum versions.');
    }
};

const loadSpecializations = async (programId: string) => {
    if (!programId) {
        conversionOptions.value.specializations = [];
        return;
    }

    try {
        const response = await fetch(`/student-applications/api/conversion-options?program_id=${programId}`);
        const data = await response.json();
        conversionOptions.value.specializations = data.specializations || [];
    } catch (error) {
        console.error('Failed to load specializations:', error);
        toast.error('Failed to load specializations.');
    }
};

// Watch for program changes
watch(
    () => conversionForm.value.program_id,
    async (newProgramId) => {
        conversionForm.value.curriculum_version_id = '';
        conversionForm.value.specialization_id = '';

        if (newProgramId) {
            await Promise.all([loadCurriculumVersions(newProgramId), loadSpecializations(newProgramId)]);
        } else {
            conversionOptions.value.curriculumVersions = [];
            conversionOptions.value.specializations = [];
        }
    },
);

const closeDialogs = () => {
    showStatusUpdateDialog.value = false;
    showBatchConversionDialog.value = false;
    currentApplication.value = null;
    conversionForm.value = {
        program_id: '',
        curriculum_version_id: '',
        specialization_id: '',
        admission_date: format(new Date(), 'yyyy-MM-dd'),
        expected_graduation_date: '',
    };
    statusForm.value.status = '';

    // Clear conversion options
    conversionOptions.value = {
        programs: [],
        curriculumVersions: [],
        specializations: [],
    };
};

// Action handlers
const updateStatus = () => {
    if (!currentApplication.value) return;

    isLoading.value = true;
    router.patch(`/student-applications/${currentApplication.value.id}/status`, statusForm.value, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            closeDialogs();
            toast.success('Application status updated successfully!');
        },
        onError: (errors) => {
            console.error('Update status errors:', errors);
            const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to update application status.';
            toast.error(errorMessage);
        },
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

const batchConvert = () => {
    if (selectedApplications.value.length === 0) return;

    isLoading.value = true;
    const count = selectedApplications.value.length;

    router.post(
        '/student-applications/batch-convert',
        {
            application_ids: selectedApplications.value.map((app) => app.id),
            ...conversionForm.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                closeDialogs();
                selectedApplications.value = [];

                // Check if there are any errors in the response
                const flashMessages = page.props.flash as any;
                if (flashMessages?.warning) {
                    toast.warning(flashMessages.warning);
                } else {
                    toast.success(`Successfully converted ${count} application(s) to students!`);
                }
            },
            onError: (errors) => {
                console.error('Batch conversion errors:', errors);
                const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to convert applications.';
                toast.error(errorMessage);
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

const deleteApplication = (application: StudentApplication) => {
    if (application.student) {
        toast.error('Cannot delete application that has been converted to a student.');
        return;
    }

    if (confirm(`Are you sure you want to delete ${application.full_name}'s application? This action cannot be undone.`)) {
        router.delete(`/student-applications/${application.id}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${application.full_name}'s application has been deleted.`);
            },
            onError: (errors) => {
                console.error('Delete errors:', errors);
                const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to delete application.';
                toast.error(errorMessage);
            },
        });
    }
};

const clearFilters = () => {
    router.visit('/student-applications', {
        preserveState: true,
        preserveScroll: true,
        only: ['applications', 'filters'],
    });
};
</script>

<template>
    <Head title="Student Applications" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Applications</h1>
                <p class="text-muted-foreground">Manage and process student applications for admission</p>
            </div>
            <div class="flex items-center space-x-2">
                <Button v-if="hasSelectedApplications && canConvertSelected" @click="openConversionDialog()" class="bg-green-600 hover:bg-green-700">
                    <Users class="mr-2 h-4 w-4" />
                    Convert {{ selectedApplications.length }} to Students
                </Button>

                <Button @click="router.visit('/student-applications/create')">
                    <FileCheck class="mr-2 h-4 w-4" />
                    New Application
                </Button>
            </div>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
                <CardDescription>Filter applications by status, campus, or search terms</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-center gap-4">
                    <!-- Search -->
                    <div class="min-w-[200px] flex-1">
                        <DebouncedInput :model-value="filters.search || ''" @debounced="onSearch" placeholder="Search by name, email, phone, or ID..." class="w-full" />
                    </div>

                    <!-- Status Filter -->
                    <div class="min-w-[150px]">
                        <Select :model-value="filters.status || 'all'" @update:model-value="onStatusFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Campus Filter -->
                    <div class="min-w-[150px]">
                        <Select :model-value="filters.campus || 'all'" @update:model-value="onCampusFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="All Campuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Campuses</SelectItem>
                                <SelectItem v-for="campus in campuses" :key="campus.code" :value="campus.code">
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Clear Filters -->
                    <Button variant="outline" @click="clearFilters"> Clear Filters </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Applications Table -->
        <CardContent class="p-0">
            <DataTable :data="applications.data" :columns="columns" :enable-row-selection="true" @selection-change="onSelectionChange" empty-message="No applications found" />

            <div class="border-t px-6">
                <DataPagination :pagination-data="applications" item-name="applications" @navigate="onNavigate" @page-size-change="onPageSizeChange" />
            </div>
        </CardContent>
    </div>

    <!-- Status Update Dialog -->
    <Dialog v-model:open="showStatusUpdateDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Update Application Status</DialogTitle>
                <DialogDescription> Change the status of {{ currentApplication?.full_name }}'s application </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div>
                    <Label for="status">Status</Label>
                    <Select v-model="statusForm.status">
                        <SelectTrigger>
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeDialogs" :disabled="isLoading"> Cancel </Button>
                <Button @click="updateStatus" :disabled="isLoading || !statusForm.status">
                    <RefreshCw v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                    Update Status
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Batch Conversion Dialog -->
    <Dialog v-model:open="showBatchConversionDialog">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    Convert Applications to Students
                    <RefreshCw v-if="loadingOptions" class="h-4 w-4 animate-spin" />
                </DialogTitle>
                <DialogDescription> Convert {{ selectedApplications.length }} approved application(s) to student records </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="program_id">Program *</Label>
                        <Select v-model="conversionForm.program_id" :disabled="loadingOptions">
                            <SelectTrigger>
                                <SelectValue placeholder="Select program" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="program in conversionOptions.programs" :key="program.id" :value="program.id.toString()"> {{ program.name }} ({{ program.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="curriculum_version_id">Curriculum Version *</Label>
                        <Select v-model="conversionForm.curriculum_version_id" :disabled="!conversionForm.program_id || conversionOptions.curriculumVersions.length === 0">
                            <SelectTrigger>
                                <SelectValue placeholder="Select version" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="version in conversionOptions.curriculumVersions" :key="version.id" :value="version.id.toString()"> {{ version.version_name }} ({{ format(new Date(version.effective_date), 'yyyy') }}) </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="specialization_id">Specialization</Label>
                        <Select v-model="conversionForm.specialization_id" :disabled="!conversionForm.program_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select specialization (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">No Specialization</SelectItem>
                                <SelectItem v-for="specialization in conversionOptions.specializations" :key="specialization.id" :value="specialization.id.toString()">
                                    {{ specialization.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="admission_date">Admission Date *</Label>
                        <Input v-model="conversionForm.admission_date" type="date" required />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="expected_graduation_date">Expected Graduation Date</Label>
                    <Input v-model="conversionForm.expected_graduation_date" type="date" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeDialogs" :disabled="isLoading"> Cancel </Button>
                <Button @click="batchConvert" :disabled="isLoading || !conversionForm.program_id || !conversionForm.curriculum_version_id || !conversionForm.admission_date" class="bg-green-600 hover:bg-green-700">
                    <RefreshCw v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                    <Users class="mr-2 h-4 w-4" />
                    Convert to Students
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
