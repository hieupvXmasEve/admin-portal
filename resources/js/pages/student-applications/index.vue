<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import { createColumns } from '@/lib/table-utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { AlertCircle, CheckCircle2, ChevronDown, Clock, Eye, RefreshCw, Trash2, Users, XCircle } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

// Types
interface StudentApplication {
    id: number;
    full_name: string;
    gender: string;
    ethnicity: string;
    birth_day: number;
    birth_month: number;
    birth_year: number;
    national_id: string;
    phone: string;
    email: string;
    address: string;
    health_information: string;
    parent_phone: string;
    parent_email: string;
    campus_code: string;
    intended_program: string;
    intended_specialization: string;
    intake: string;
    exam_date: string;
    english_test_type: string;
    listening: number;
    reading: number;
    writing: number;
    speaking: number;
    overall: number;
    submitted_photo: string | null;
    submitted_cccd: string | null;
    submitted_ccta: string | null;
    submitted_tn_translate: string | null;
    submitted_hb_translate: string | null;
    submitted_other: string | null;
    submitted_insurance_card: string | null;
    submitted_exemption_gc: string | null;
    study_link_status: string;
    english_qualifications: string;
    sut_id: string;
    is_international_applicant: boolean;
    exception_units: string;
    status: 'pending' | 'reviewed' | 'approved' | 'rejected';
    student_id: number | null;
    created_at: string;
    updated_at: string;
    student?: {
        id: number;
        student_id: string;
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
        converted: string;
        campus: string;
        per_page: number;
        sort: string;
        direction: 'asc' | 'desc';
    };
    campuses: Campus[];
    statusOptions: FilterOption[];
    conversionOptions: FilterOption[];
}

const props = defineProps<Props>();
// API
const api = useApi();

// State
const selectedApplications = ref<StudentApplication[]>([]);
const showBatchConversionDialog = ref(false);
const showStatusUpdateDialog = ref(false);
const showBulkStatusUpdateDialog = ref(false);
const showApplicationDetailsDialog = ref(false);
const currentApplication = ref<StudentApplication | null>(null);
const selectedApplicationForDetails = ref<StudentApplication | null>(null);
const isLoading = ref(false);

// Template refs
const dataTableRef = ref<InstanceType<typeof DataTable> | null>(null);

// Form state for conversions
const conversionForm = ref({
    admission_date: format(new Date(), 'yyyy-MM-dd'),
});

const statusForm = ref({
    status: '',
});

const bulkStatusForm = ref({
    status: '',
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

// Helper function to format birth date
const formatBirthDate = (day: number, month: number, year: number) => {
    if (!day || !month || !year) return 'N/A';
    return `${day.toString().padStart(2, '0')}/${month.toString().padStart(2, '0')}/${year}`;
};

// Helper function to format boolean as Yes/No
const formatBoolean = (value: boolean) => {
    return value ? 'Yes' : 'No';
};

// Table columns
const baseSuggestedCoursesColumns: ColumnDef<StudentApplication>[] = [
    {
        accessorKey: 'id',
        header: 'ID',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm font-mono' }, application.id.toString());
        },
    },
    {
        accessorKey: 'full_name',
        header: 'Full Name',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'font-medium min-w-[150px]' }, application.full_name);
        },
    },
    {
        accessorKey: 'gender',
        header: 'Gender',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.gender || 'N/A');
        },
    },
    {
        accessorKey: 'ethnicity',
        header: 'Ethnicity',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.ethnicity || 'N/A');
        },
    },
    {
        accessorKey: 'birth_date',
        header: 'Birth Date',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, formatBirthDate(application.birth_day, application.birth_month, application.birth_year));
        },
    },
    {
        accessorKey: 'national_id',
        header: 'National ID',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm font-mono' }, application.national_id || 'N/A');
        },
    },
    {
        accessorKey: 'phone',
        header: 'Phone',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.phone || 'N/A');
        },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm text-muted-foreground min-w-[200px]' }, application.email || 'N/A');
        },
    },
    {
        accessorKey: 'address',
        header: 'Address',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm max-w-[200px] truncate', title: application.address }, application.address || 'N/A');
        },
    },
    {
        accessorKey: 'health_information',
        header: 'Health Info',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm max-w-[150px] truncate', title: application.health_information }, application.health_information || 'N/A');
        },
    },
    {
        accessorKey: 'parent_phone',
        header: 'Parent Phone',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.parent_phone || 'N/A');
        },
    },
    {
        accessorKey: 'parent_email',
        header: 'Parent Email',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm text-muted-foreground min-w-[200px]' }, application.parent_email || 'N/A');
        },
    },
    {
        accessorKey: 'campus_code',
        header: 'Campus',
        cell: ({ row }) => {
            const application = row.original;
            const campus = props.campuses.find((c) => c.code === application.campus_code);
            return h('div', { class: 'text-sm' }, campus?.name || application.campus_code || 'N/A');
        },
    },
    {
        accessorKey: 'intended_program',
        header: 'Intended Program',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm min-w-[150px]' }, application.intended_program || 'N/A');
        },
    },
    {
        accessorKey: 'intended_specialization',
        header: 'Specialization',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm min-w-[150px]' }, application.intended_specialization || 'N/A');
        },
    },
    {
        accessorKey: 'intake',
        header: 'Intake',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.intake || 'N/A');
        },
    },
    {
        accessorKey: 'exam_date',
        header: 'Exam Date',
        cell: ({ row }) => {
            const application = row.original;
            if (!application.exam_date) return h('span', { class: 'text-sm text-muted-foreground' }, 'N/A');
            return h('div', { class: 'text-sm' }, format(new Date(application.exam_date), 'MMM dd, yyyy'));
        },
    },
    {
        accessorKey: 'english_test_type',
        header: 'English Test',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.english_test_type || 'N/A');
        },
    },
    {
        accessorKey: 'listening',
        header: 'Listening',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.listening ? application.listening.toString() : 'N/A');
        },
    },
    {
        accessorKey: 'reading',
        header: 'Reading',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.reading ? application.reading.toString() : 'N/A');
        },
    },
    {
        accessorKey: 'writing',
        header: 'Writing',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.writing ? application.writing.toString() : 'N/A');
        },
    },
    {
        accessorKey: 'speaking',
        header: 'Speaking',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.speaking ? application.speaking.toString() : 'N/A');
        },
    },
    {
        accessorKey: 'overall',
        header: 'Overall Score',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm font-medium' }, application.overall ? application.overall.toString() : 'N/A');
        },
    },
    {
        accessorKey: 'submitted_photo',
        header: 'Photo',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_photo;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'submitted_cccd',
        header: 'CCCD',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_cccd;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'submitted_ccta',
        header: 'CCTA',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_ccta;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'submitted_tn_translate',
        header: 'TN Translate',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_tn_translate;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'submitted_hb_translate',
        header: 'HB Translate',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_hb_translate;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'submitted_other',
        header: 'Other Docs',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_other;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'submitted_insurance_card',
        header: 'Insurance',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_insurance_card;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'submitted_exemption_gc',
        header: 'Exemption GC',
        cell: ({ row }) => {
            const application = row.original;
            const documentPath = application.submitted_exemption_gc;
            if (documentPath) {
                return h('a', {
                    href: documentPath,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer'
                }, 'View');
            }
            return h('span', { class: 'text-sm text-red-600' }, 'No');
        },
    },
    {
        accessorKey: 'study_link_status',
        header: 'StudyLink Status',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm' }, application.study_link_status || 'N/A');
        },
    },
    {
        accessorKey: 'english_qualifications',
        header: 'English Qualifications',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm max-w-[200px] truncate', title: application.english_qualifications }, application.english_qualifications || 'N/A');
        },
    },
    {
        accessorKey: 'sut_id',
        header: 'SUT ID',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm font-mono' }, application.sut_id || 'N/A');
        },
    },
    {
        accessorKey: 'is_international_applicant',
        header: 'International',
        cell: ({ row }) => {
            const application = row.original;
            const isInternational = application.is_international_applicant;
            return h('div', { class: `text-sm ${isInternational ? 'text-blue-600' : 'text-gray-600'}` }, formatBoolean(isInternational));
        },
    },
    {
        accessorKey: 'exception_units',
        header: 'Exception Units',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm max-w-[150px] truncate', title: application.exception_units }, application.exception_units || 'N/A');
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
                return h('div', { class: 'text-sm' }, [h('div', { class: 'font-medium text-green-600' }, application.student.student_id), h('div', { class: 'text-xs text-muted-foreground' }, 'Converted')]);
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
        accessorKey: 'updated_at',
        header: 'Updated',
        cell: ({ row }) => {
            const application = row.original;
            return h('div', { class: 'text-sm text-muted-foreground' }, format(new Date(application.updated_at), 'MMM dd, yyyy'));
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
                                    onClick: () => openApplicationDetailsDialog(application),
                                },
                                () => [h(Eye, { class: 'mr-2 h-4 w-4' }), 'View'],
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
const suggestedCoursesColumns = createColumns(baseSuggestedCoursesColumns, {
    enableSelection: true,
});
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

const onConversionFilter = (value: string) => {
    applyFilters({ converted: value === 'all' ? '' : value });
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
};

const openBulkStatusDialog = () => {
    bulkStatusForm.value.status = '';
    showBulkStatusUpdateDialog.value = true;
};

const openApplicationDetailsDialog = (application: StudentApplication) => {
    selectedApplicationForDetails.value = application;
    showApplicationDetailsDialog.value = true;
};

const closeDialogs = () => {
    showStatusUpdateDialog.value = false;
    showBatchConversionDialog.value = false;
    showBulkStatusUpdateDialog.value = false;
    showApplicationDetailsDialog.value = false;
    currentApplication.value = null;
    selectedApplicationForDetails.value = null;
    conversionForm.value = {
        admission_date: format(new Date(), 'yyyy-MM-dd'),
    };
    statusForm.value.status = '';
    bulkStatusForm.value.status = '';
};

// Action handlers
const updateStatus = () => {
    if (!currentApplication.value) return;

    isLoading.value = true;
    router.patch(`/student-applications/${currentApplication.value.id}/status`, statusForm.value, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
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
            admission_date: conversionForm.value.admission_date,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                closeDialogs();
                selectedApplications.value = [];

                // Clear table selection
                dataTableRef.value?.clearSelection();

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

const updateBulkStatus = async () => {
    if (selectedApplications.value.length === 0 || !bulkStatusForm.value.status) return;

    isLoading.value = true;
    const count = selectedApplications.value.length;

    try {
        const { data: response } = await api.patch('/api/student-applications/bulk/status', {
            application_ids: selectedApplications.value.map((app) => app.id),
            status: bulkStatusForm.value.status,
        });

        if (response.value?.success) {
            closeDialogs();
            selectedApplications.value = [];

            // Clear table selection
            dataTableRef.value?.clearSelection();

            // Refresh the page data
            router.reload({
                preserveState: true,
                preserveScroll: true,
                only: ['applications'],
            });

            toast.success(`Successfully updated status for ${count} application(s)!`);
        } else {
            throw new Error(response.value?.message || 'Failed to update application statuses');
        }
    } catch (error) {
        console.error('Bulk status update error:', error);
        toast.error(error instanceof Error ? error.message : 'Failed to update application statuses. Please try again.');
    } finally {
        isLoading.value = false;
    }
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

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Student Applications</h1>
            <p class="text-muted-foreground">Manage and process student applications for admission</p>
        </div>
        <div class="flex items-center space-x-2">
            <Button v-if="hasSelectedApplications" @click="openBulkStatusDialog()" variant="outline" class="border-blue-300 text-blue-600 hover:bg-blue-50">
                <RefreshCw class="mr-2 h-4 w-4" />
                Update Status ({{ selectedApplications.length }})
            </Button>

            <Button v-if="hasSelectedApplications && canConvertSelected" @click="openConversionDialog()" class="bg-green-600 hover:bg-green-700">
                <Users class="mr-2 h-4 w-4" />
                Convert {{ selectedApplications.length }} to Students
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

                <!-- Conversion Filter -->
                <div class="min-w-[180px]">
                    <Select :model-value="filters.converted || 'all'" @update:model-value="onConversionFilter">
                        <SelectTrigger>
                            <SelectValue placeholder="All Applications" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Applications</SelectItem>
                            <SelectItem v-for="option in conversionOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
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
        <DataTable ref="dataTableRef" :data="applications.data" :columns="suggestedCoursesColumns" :enable-row-selection="true" @selection-change="onSelectionChange" empty-message="No applications found" />

        <DataPagination :pagination-data="applications" item-name="applications" @navigate="onNavigate" @page-size-change="onPageSizeChange" />
    </CardContent>

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
                <div class="space-y-2">
                    <Label for="admission_date">Admission Date *</Label>
                    <Input v-model="conversionForm.admission_date" type="date" required />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeDialogs" :disabled="isLoading"> Cancel </Button>
                <Button @click="batchConvert" :disabled="isLoading || !conversionForm.admission_date" class="bg-green-600 hover:bg-green-700">
                    <RefreshCw v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                    <Users class="mr-2 h-4 w-4" />
                    Convert to Students
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Bulk Status Update Dialog -->
    <Dialog v-model:open="showBulkStatusUpdateDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Update Status for Multiple Applications</DialogTitle>
                <DialogDescription> Change the status for {{ selectedApplications.length }} selected application(s) </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div>
                    <Label for="bulk-status">New Status</Label>
                    <Select v-model="bulkStatusForm.status">
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
                <Button @click="updateBulkStatus" :disabled="isLoading || !bulkStatusForm.status" class="bg-blue-600 hover:bg-blue-700">
                    <RefreshCw v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                    Update Status
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Application Details Dialog -->
    <Dialog v-model:open="showApplicationDetailsDialog">
        <DialogContent class="sm:max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Eye class="h-5 w-5" />
                    Student Application Details
                </DialogTitle>
                <DialogDescription v-if="selectedApplicationForDetails">
                    Viewing details for {{ selectedApplicationForDetails.full_name }}'s application
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedApplicationForDetails" class="space-y-6">
                <!-- Personal Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Personal Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Application ID</Label>
                                <p class="font-mono">{{ selectedApplicationForDetails.id }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Full Name</Label>
                                <p class="font-medium">{{ selectedApplicationForDetails.full_name || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Gender</Label>
                                <p>{{ selectedApplicationForDetails.gender || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Ethnicity</Label>
                                <p>{{ selectedApplicationForDetails.ethnicity || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Birth Date</Label>
                                <p>{{ formatBirthDate(selectedApplicationForDetails.birth_day, selectedApplicationForDetails.birth_month, selectedApplicationForDetails.birth_year) }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">National ID</Label>
                                <p class="font-mono">{{ selectedApplicationForDetails.national_id || 'N/A' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Contact Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Contact Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1  gap-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Phone</Label>
                                <p>{{ selectedApplicationForDetails.phone || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Email</Label>
                                <p class="text-blue-600">{{ selectedApplicationForDetails.email || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Address</Label>
                                <p class="break-words">{{ selectedApplicationForDetails.address || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Parent Phone</Label>
                                <p>{{ selectedApplicationForDetails.parent_phone || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Parent Email</Label>
                                <p class="text-blue-600">{{ selectedApplicationForDetails.parent_email || 'N/A' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Academic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Academic Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Campus</Label>
                                <p>{{ campuses.find(c => c.code === selectedApplicationForDetails.campus_code)?.name || selectedApplicationForDetails.campus_code || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Intended Program</Label>
                                <p>{{ selectedApplicationForDetails.intended_program || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Specialization</Label>
                                <p>{{ selectedApplicationForDetails.intended_specialization || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Intake</Label>
                                <p>{{ selectedApplicationForDetails.intake || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Exam Date</Label>
                                <p>{{ selectedApplicationForDetails.exam_date ? format(new Date(selectedApplicationForDetails.exam_date), 'MMM dd, yyyy') : 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">SUT ID</Label>
                                <p class="font-mono">{{ selectedApplicationForDetails.sut_id || 'N/A' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- English Test Scores -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">English Test Scores</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Test Type</Label>
                                <p>{{ selectedApplicationForDetails.english_test_type || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Listening</Label>
                                <p class="font-medium">{{ selectedApplicationForDetails.listening || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Reading</Label>
                                <p class="font-medium">{{ selectedApplicationForDetails.reading || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Writing</Label>
                                <p class="font-medium">{{ selectedApplicationForDetails.writing || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Speaking</Label>
                                <p class="font-medium">{{ selectedApplicationForDetails.speaking || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Overall Score</Label>
                                <p class="font-semibold text-lg">{{ selectedApplicationForDetails.overall || 'N/A' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Document Submissions -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Document Submissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Photo</Label>
                                <p v-if="selectedApplicationForDetails.submitted_photo">
                                    <a :href="selectedApplicationForDetails.submitted_photo" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">CCCD</Label>
                                <p v-if="selectedApplicationForDetails.submitted_cccd">
                                    <a :href="selectedApplicationForDetails.submitted_cccd" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">CCTA</Label>
                                <p v-if="selectedApplicationForDetails.submitted_ccta">
                                    <a :href="selectedApplicationForDetails.submitted_ccta" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">TN Translate</Label>
                                <p v-if="selectedApplicationForDetails.submitted_tn_translate">
                                    <a :href="selectedApplicationForDetails.submitted_tn_translate" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">HB Translate</Label>
                                <p v-if="selectedApplicationForDetails.submitted_hb_translate">
                                    <a :href="selectedApplicationForDetails.submitted_hb_translate" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Other Documents</Label>
                                <p v-if="selectedApplicationForDetails.submitted_other">
                                    <a :href="selectedApplicationForDetails.submitted_other" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Insurance Card</Label>
                                <p v-if="selectedApplicationForDetails.submitted_insurance_card">
                                    <a :href="selectedApplicationForDetails.submitted_insurance_card" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Exemption GC</Label>
                                <p v-if="selectedApplicationForDetails.submitted_exemption_gc">
                                    <a :href="selectedApplicationForDetails.submitted_exemption_gc" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">View Document</a>
                                </p>
                                <p v-else class="text-red-600">Not Submitted</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Additional Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Additional Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Health Information</Label>
                                <p class="break-words">{{ selectedApplicationForDetails.health_information || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">StudyLink Status</Label>
                                <p>{{ selectedApplicationForDetails.study_link_status || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">English Qualifications</Label>
                                <p class="break-words">{{ selectedApplicationForDetails.english_qualifications || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">International Applicant</Label>
                                <p :class="selectedApplicationForDetails.is_international_applicant ? 'text-blue-600' : 'text-gray-600'">{{ formatBoolean(selectedApplicationForDetails.is_international_applicant) }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Exception Units</Label>
                                <p class="break-words">{{ selectedApplicationForDetails.exception_units || 'N/A' }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Application Status</Label>
                                <Badge :variant="getStatusBadge(selectedApplicationForDetails.status).variant" :class="getStatusBadge(selectedApplicationForDetails.status).class">
                                    <component :is="getStatusBadge(selectedApplicationForDetails.status).icon" class="w-3 h-3 mr-1" />
                                    {{ selectedApplicationForDetails.status.charAt(0).toUpperCase() + selectedApplicationForDetails.status.slice(1) }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Conversion Status -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Conversion Status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Student Conversion</Label>
                                <div v-if="selectedApplicationForDetails.student">
                                    <p class="font-medium text-green-600">{{ selectedApplicationForDetails.student.student_id }}</p>
                                    <p class="text-xs text-muted-foreground">Converted to Student</p>
                                </div>
                                <p v-else class="text-muted-foreground">Not Converted</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Created Date</Label>
                                <p>{{ format(new Date(selectedApplicationForDetails.created_at), 'MMM dd, yyyy HH:mm') }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium text-muted-foreground">Last Updated</Label>
                                <p>{{ format(new Date(selectedApplicationForDetails.updated_at), 'MMM dd, yyyy HH:mm') }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeDialogs()">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
