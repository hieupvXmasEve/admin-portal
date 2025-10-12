<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import { createColumns } from '@/lib/table-utils';
import type { Attendance, ClassSession, Student } from '@/types/models';
import { formatDate } from '@/utils/date';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { AlertCircle, ArrowLeft, Calendar, CheckSquare, Edit, MapPin, UserPlus, Users, Video, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    session: ClassSession;
    attendanceData: Attendance[];
    studentsWithoutAttendance: Student[];
    statusOptions: Record<string, string>;
    filters: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const { post: apiCall } = useApi();

// Filter state - Initialize with props or defaults
const filters = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || 'all',
});

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.status && newFilters.status !== 'all') params.set('status', newFilters.status);

    const url = `/class-sessions/${props.session.id}${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['attendanceData', 'filters'],
    });
};

// Search handler for DebouncedInput
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const handleStatusFilter = (value: any) => {
    filters.value.status = String(value || 'all');
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        status: 'all',
    };
    router.visit(`/class-sessions/${props.session.id}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['attendanceData', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || (filters.value.status && filters.value.status !== 'all');
});

// Attendance data with computed values
const data = computed(() => props.attendanceData || []);

// Bulk update modal state
const showBulkUpdateModal = ref(false);
const loading = ref(false);

// Ref to track selected rows to clear after update
const selectedRows = ref<Attendance[]>([]);

// DataTable ref for clearing selection
const dataTableRef = ref<any>(null);

// Form schema for bulk update
const formSchema = toTypedSchema(
    z.object({
        status: z.string().min(1, 'Status is required'),
        attendanceIds: z.array(z.number()).min(1, 'At least one attendance record must be selected'),
    }),
);

const { handleSubmit, values, setFieldValue, resetForm } = useForm({
    validationSchema: formSchema,
    initialValues: {
        status: '',
        attendanceIds: [],
    },
});

// Selected attendance count for UI
const selectedAttendanceCount = ref(0);

// Handle selection change from DataTable
const handleSelectionChange = (selectedRowsData: Attendance[]) => {
    selectedRows.value = selectedRowsData;
    const attendanceIds = selectedRowsData.map((attendance) => attendance.id).filter((id): id is number => id !== undefined);

    selectedAttendanceCount.value = attendanceIds.length;
    setFieldValue('attendanceIds', attendanceIds);
};

// Submit bulk update
const onBulkUpdateSubmit = handleSubmit(async (values) => {
    try {
        loading.value = true;

        const result = await apiCall('/api/attendance/bulk-update', {
            attendance_ids: values.attendanceIds,
            status: values.status,
        });

        if (result.error.value) {
            console.error('API Error:', result.error.value);
            throw new Error('Failed to update attendance records');
        }

        if (result.data.value?.success) {
            toast.success(result.data.value.message || 'Attendance records updated successfully');
            showBulkUpdateModal.value = false;
            resetForm();

            // Clear table selection
            if (dataTableRef.value?.clearSelection) {
                dataTableRef.value.clearSelection();
            }

            router.reload({
                only: ['attendanceData', 'session'],
                onSuccess: () => {
                    // Clear selection after reload
                    selectedAttendanceCount.value = 0;
                    selectedRows.value = [];
                },
            });
        }
    } catch (error) {
        console.error('Failed to update attendance records:', error);
        toast.error('Failed to update attendance records');
    } finally {
        loading.value = false;
    }
});

const openBulkUpdateModal = () => {
    if (selectedAttendanceCount.value === 0) {
        toast.error('Please select at least one attendance record');
        return;
    }
    showBulkUpdateModal.value = true;
};

const closeBulkUpdateModal = () => {
    showBulkUpdateModal.value = false;
    // Only reset form fields, keep selection data intact
    setFieldValue('status', '');
};

// Column definitions for the data table
const baseColumns: ColumnDef<Attendance>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            return row.index + 1;
        },
    },
    {
        header: 'Student Name',
        accessorKey: 'student.full_name',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'font-medium' }, attendance.student?.full_name || 'N/A');
        },
    },
    {
        header: 'Student ID',
        accessorKey: 'student.student_id',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'font-mono text-sm' }, attendance.student?.student_id || 'N/A');
        },
    },
    {
        header: 'Email',
        accessorKey: 'student.email',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'text-sm text-muted-foreground' }, attendance.student?.email || 'N/A');
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            const variant = getStatusBadgeVariant(attendance.status);
            return h(Badge, { variant, class: 'capitalize' }, () => attendance.status);
        },
    },
    {
        header: 'Check In',
        accessorKey: 'check_in_time',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'text-sm' }, attendance.formatted_check_in_time || '-');
        },
    },
    {
        header: 'Minutes Late',
        accessorKey: 'minutes_late',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'text-sm' }, attendance.minutes_late ? `${attendance.minutes_late} min` : '-');
        },
    },
    {
        header: 'Recording Method',
        accessorKey: 'recording_method',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'text-sm capitalize' }, attendance.recording_method || 'Manual');
        },
    },
    {
        header: 'Recorded At',
        accessorKey: 'created_at',
        enableSorting: false,
        cell: ({ row }) => {
            const attendance = row.original;
            return h('div', { class: 'text-sm text-muted-foreground' }, new Date(attendance.created_at).toLocaleString());
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions',
    },
];

const columns = createColumns(baseColumns, {
    enableSelection: true,
});

// Helper functions
const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'scheduled':
            return 'default';
        case 'in_progress':
            return 'secondary';
        case 'completed':
            return 'outline';
        case 'cancelled':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'present':
            return 'default';
        case 'late':
            return 'secondary';
        case 'absent':
            return 'destructive';
        case 'excused':
            return 'outline';
        default:
            return 'default';
    }
};

const getDeliveryModeIcon = (mode: string) => {
    return mode === 'online' ? Video : MapPin;
};

// Navigation functions
const navigateBack = () => {
    router.visit(`/course-offerings/${props.session.course_offering_id}`);
};

const editSession = () => {
    router.visit(`/class-sessions/${props.session.id}/edit`);
};

const editAttendance = (attendance: Attendance) => {
    router.visit(`/attendance/${attendance.id}/edit`);
};

// Generate attendance for a session
const generateAttendance = () => {
    router.post(
        `/class-sessions/${props.session.id}/generate-attendance`,
        {},
        {
            onSuccess: (page: any) => {
                const message = page.props.flash?.success || 'Attendance records generated successfully';
                toast.success(message);
                router.reload({ only: ['attendanceData', 'studentsWithoutAttendance', 'session'] });
            },
            onError: (errors: any) => {
                console.error('Generate attendance errors:', errors);
                const message = errors.flash?.error || 'Failed to generate attendance records';
                toast.error(message);
            },
        },
    );
};

// Export attendance to CSV
// const exportAttendance = () => {
//     const params = new URLSearchParams();
//     if (filters.value.search) params.set('search', filters.value.search);
//     if (filters.value.status && filters.value.status !== 'all') params.set('status', filters.value.status);

//     const url = `/class-sessions/${props.session.id}/export-attendance${params.toString() ? '?' + params.toString() : ''}`;
//     window.open(url, '_blank');
//     toast.success('Attendance data exported successfully');
// };
</script>

<template>
    <Head :title="`${session.session_title} - Class Session Attendance`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="navigateBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">{{ session.session_title }}</h1>
                    <p class="text-muted-foreground">
                        {{ session.course_offering?.curriculum_unit?.unit.code }} -
                        {{ session.course_offering?.curriculum_unit?.unit.name }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <!-- <Button variant="outline" @click="exportAttendance">
                    <Download class="mr-2 h-4 w-4" />
                    Export CSV
                </Button> -->
                <!-- <Button variant="outline" @click="generateAttendance">
                    <UserPlus class="mr-2 h-4 w-4" />
                    Generate Attendance
                </Button> -->
                <Button @click="editSession">
                    <Edit class="mr-2 h-4 w-4" />
                    Edit Session
                </Button>
            </div>
        </div>

        <!-- Session Overview Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <!-- Session Info Card -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center space-x-2">
                        <Calendar class="text-muted-foreground h-4 w-4" />
                        <div class="space-y-1">
                            <p class="text-sm font-medium">{{ formatDate(session.session_date) }}</p>
                            <p class="text-muted-foreground text-xs">
                                {{ session.formatted_time }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Status Card -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center space-x-2">
                        <component :is="getDeliveryModeIcon(session.delivery_mode)" class="text-muted-foreground h-4 w-4" />
                        <div class="space-y-1">
                            <Badge :variant="getStatusVariant(session.status)" class="capitalize">
                                {{ session.status?.replace('_', ' ') }}
                            </Badge>
                            <p class="text-muted-foreground text-xs capitalize">
                                {{ session.delivery_mode?.replace('_', ' ') }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Attendance Rate Card -->
            <Card>
                <CardContent class="p-6">
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <Users class="text-muted-foreground h-4 w-4" />
                            <span class="text-sm font-medium">Attendance Rate</span>
                        </div>
                        <div class="space-y-1">
                            <div class="text-2xl font-bold text-green-600">{{ session.attendance_stats?.attendance_percentage || 0 }}%</div>
                            <Progress :value="session.attendance_stats?.attendance_percentage || 0" class="h-2" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Total Students Card -->
            <Card>
                <CardContent class="p-6">
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <UserPlus class="text-muted-foreground h-4 w-4" />
                            <span class="text-sm font-medium">Total Students</span>
                        </div>
                        <div class="text-2xl font-bold">
                            {{ (session.attendance_stats?.total || 0) + (studentsWithoutAttendance?.length || 0) }}
                        </div>
                        <p class="text-muted-foreground text-xs">{{ session.attendance_stats?.total || 0 }} with records</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Attendance Statistics -->
        <Card v-if="session.attendance_stats">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Attendance Statistics
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="space-y-2 text-center">
                        <Badge variant="default" class="px-3 py-1 text-lg">
                            {{ session.attendance_stats.present }}
                        </Badge>
                        <p class="text-muted-foreground text-sm">Present</p>
                    </div>
                    <div class="space-y-2 text-center">
                        <Badge variant="secondary" class="px-3 py-1 text-lg">
                            {{ session.attendance_stats.late }}
                        </Badge>
                        <p class="text-muted-foreground text-sm">Late</p>
                    </div>
                    <div class="space-y-2 text-center">
                        <Badge variant="destructive" class="px-3 py-1 text-lg">
                            {{ session.attendance_stats.absent }}
                        </Badge>
                        <p class="text-muted-foreground text-sm">Absent</p>
                    </div>
                    <div class="space-y-2 text-center">
                        <Badge variant="outline" class="px-3 py-1 text-lg">
                            {{ session.attendance_stats.excused }}
                        </Badge>
                        <p class="text-muted-foreground text-sm">Excused</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Students Without Attendance Records Alert -->
        <Alert v-if="studentsWithoutAttendance && studentsWithoutAttendance.length > 0" class="border-yellow-200 bg-yellow-50">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
                <strong>{{ studentsWithoutAttendance.length }} student(s)</strong> don't have attendance records yet.
                <Button variant="link" class="h-auto p-0 text-yellow-700" @click="generateAttendance"> Click here to generate attendance records for all enrolled students. </Button>
            </AlertDescription>
        </Alert>

        <!-- Filters Section -->
        <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
            <div class="min-w-[200px] flex-1">
                <DebouncedInput placeholder="Search students by name, ID, or email..." v-model="filters.search" @debounced="handleSearch" />
            </div>

            <div class="min-w-[150px]">
                <Select v-model="filters.status" @update:model-value="handleStatusFilter">
                    <SelectTrigger>
                        <SelectValue placeholder="Filter by status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="(label, value) in statusOptions" :key="value" :value="value">
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                <X class="mr-2 h-4 w-4" />
                Clear Filters
            </Button>
        </div>

        <!-- Attendance Data Table -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Student Attendance Details ({{ data.length }})
                </CardTitle>
            </CardHeader>
            <CardContent>
                <!-- Bulk Update Button -->
                <div v-if="selectedAttendanceCount > 0" class="bg-muted/50 mt-4 flex items-center justify-between rounded-lg border p-4">
                    <div class="flex items-center gap-2">
                        <CheckSquare class="text-primary h-5 w-5" />
                        <span class="font-medium">{{ selectedAttendanceCount }} student{{ selectedAttendanceCount !== 1 ? 's' : '' }} selected</span>
                    </div>
                    <Button @click="openBulkUpdateModal">
                        <CheckSquare class="mr-2 h-4 w-4" />
                        Bulk Update Attendance
                    </Button>
                </div>
                <DataTable ref="dataTableRef" :data="data" :columns="columns" :enable-row-selection="true" :show-column-toggle="false" @selection-change="handleSelectionChange">
                    <template #cell-actions="{ row }">
                        <Button variant="ghost" size="sm" @click="editAttendance(row.original)">
                            <Edit class="h-4 w-4" />
                        </Button>
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <!-- Bulk Update Modal -->
        <Dialog :open="showBulkUpdateModal" @update:open="closeBulkUpdateModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <CheckSquare class="h-5 w-5" />
                        Bulk Update Attendance
                    </DialogTitle>
                    <DialogDescription> Update attendance status for {{ selectedAttendanceCount }} selected student{{ selectedAttendanceCount !== 1 ? 's' : '' }} </DialogDescription>
                </DialogHeader>

                <form @submit="onBulkUpdateSubmit" class="space-y-6">
                    <FormField v-slot="{ componentField }" name="status">
                        <FormItem>
                            <FormLabel>New Status</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select new status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="present">Present</SelectItem>
                                        <SelectItem value="absent">Absent</SelectItem>
                                        <SelectItem value="late">Late</SelectItem>
                                        <SelectItem value="excused">Excused</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeBulkUpdateModal"> Cancel </Button>
                        <Button type="submit" :disabled="loading || !values.status">
                            {{ loading ? 'Updating...' : `Update ${selectedAttendanceCount} Record${selectedAttendanceCount !== 1 ? 's' : ''}` }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
