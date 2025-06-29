<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import { createColumns } from '@/lib/table-utils';
import type { CourseOffering, CourseRegistration } from '@/types/models';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { UserCheck } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref, watch } from 'vue';
import { z } from 'zod';

interface Props {
    courseOffering: CourseOffering & {
        course_registrations: CourseRegistration[];
    };
}

interface StatusUpdateFormData {
    fromStatus: string;
    toStatus: string;
    studentIds: number[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
    updated: [];
}>();

const { post: apiCall, loading } = useApi();

// Available registration statuses
const registrationStatuses = [
    { value: 'registered', label: 'Registered' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'dropped', label: 'Dropped' },
    { value: 'withdrawn', label: 'Withdrawn' },
    { value: 'completed', label: 'Completed' },
];

// Form schema
const formSchema = toTypedSchema(
    z.object({
        fromStatus: z.string().min(1, 'From status is required'),
        toStatus: z.string().min(1, 'To status is required'),
        studentIds: z.array(z.number()).min(1, 'At least one student must be selected'),
    }),
);

const { handleSubmit, values, setFieldValue } = useForm({
    validationSchema: formSchema,
    initialValues: {
        fromStatus: '',
        toStatus: '',
        studentIds: [],
    } satisfies StatusUpdateFormData,
});

// Selected students count for UI
const selectedStudentsCount = ref(0);

// Filter students based on selected from status
const filteredStudents = computed(() => {
    if (!values.fromStatus) return [];

    return props.courseOffering.course_registrations.filter((registration) => registration.registration_status === values.fromStatus);
});

// DataTable columns
const baseColumns: ColumnDef<CourseRegistration>[] = [
    {
        accessorKey: 'student.student_id',
        header: 'Student ID',
        cell: ({ row }) => {
            const registration = row.original;
            return h('span', { class: 'font-medium' }, registration.student?.student_id || 'N/A');
        },
    },
    {
        accessorKey: 'student.full_name',
        header: 'Student Name',
        cell: ({ row }) => {
            const registration = row.original;
            return registration.student?.full_name || 'N/A';
        },
    },
    {
        accessorKey: 'student.email',
        header: 'Email',
        cell: ({ row }) => {
            const registration = row.original;
            return h('span', { class: 'text-muted-foreground' }, registration.student?.email || 'N/A');
        },
    },
    {
        accessorKey: 'registration_status',
        header: 'Current Status',
        cell: ({ row }) => {
            const registration = row.original;
            return h(
                Badge,
                {
                    variant: getRegistrationStatusVariant(registration.registration_status),
                },
                () => registration.registration_status.toUpperCase(),
            );
        },
    },
];

const columns = createColumns(baseColumns, {
    enableSelection: true,
});

// Watch for changes in fromStatus to clear selections
watch(
    () => values.fromStatus,
    () => {
        selectedStudentsCount.value = 0;
        setFieldValue('studentIds', []);
    },
);

// Handle selection change from DataTable
const handleSelectionChange = (selectedRows: CourseRegistration[]) => {
    const studentIds = selectedRows.map((registration) => registration.student?.id).filter((id): id is number => id !== undefined);

    selectedStudentsCount.value = studentIds.length;
    setFieldValue('studentIds', studentIds);
};

const getRegistrationStatusVariant = (status: string) => {
    switch (status) {
        case 'registered':
            return 'default';
        case 'confirmed':
            return 'default';
        case 'dropped':
            return 'destructive';
        case 'withdrawn':
            return 'secondary';
        case 'completed':
            return 'outline';
        default:
            return 'outline';
    }
};

const onSubmit = handleSubmit(async (values) => {
    try {
        const response = await apiCall(`/api/course-offerings/${props.courseOffering.id}/bulk-update-status`, {
            method: 'POST',
            body: {
                from_status: values.fromStatus,
                to_status: values.toStatus,
                student_ids: values.studentIds,
            },
        });

        if (response?.success) {
            emit('updated');
            emit('close');
        }
    } catch (error) {
        console.error('Failed to update registration status:', error);
    }
});

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <Dialog :open="true" @update:open="handleClose">
        <DialogContent class="flex max-h-[90vh] !w-[90vw] !max-w-6xl flex-col overflow-hidden">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserCheck class="h-5 w-5" />
                    Bulk Registration Status Update
                </DialogTitle>
                <DialogDescription>
                    Update registration status for multiple students in {{ courseOffering.course_code }} - {{ courseOffering.course_title }}
                </DialogDescription>
            </DialogHeader>

            <form @submit="onSubmit" class="flex flex-1 flex-col space-y-6 overflow-hidden">
                <div class="grid grid-cols-2 gap-4">
                    <!-- From Status -->
                    <FormField v-slot="{ componentField }" name="fromStatus">
                        <FormItem>
                            <FormLabel>From Status</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select current status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="status in registrationStatuses" :key="status.value" :value="status.value">
                                            {{ status.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- To Status -->
                    <FormField v-slot="{ componentField }" name="toStatus">
                        <FormItem>
                            <FormLabel>To Status</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select target status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="status in registrationStatuses" :key="status.value" :value="status.value">
                                            {{ status.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Student Selection -->
                <div v-if="values.fromStatus" class="flex flex-1 flex-col overflow-hidden">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold">
                            Students with {{ registrationStatuses.find((s) => s.value === values.fromStatus)?.label }} status ({{
                                filteredStudents.length
                            }})
                        </h3>
                    </div>

                    <FormField name="studentIds">
                        <FormItem class="flex-1 overflow-hidden">
                            <FormControl>
                                <div class="overflow-auto p-0">
                                    <div v-if="filteredStudents.length === 0" class="py-8 text-center">
                                        <UserCheck class="text-muted-foreground mx-auto h-12 w-12" />
                                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No students found</h3>
                                        <p class="text-muted-foreground mt-1 text-sm">
                                            No students have
                                            {{ registrationStatuses.find((s) => s.value === values.fromStatus)?.label.toLowerCase() }} status.
                                        </p>
                                    </div>
                                    <DataTable
                                        v-else
                                        :data="filteredStudents"
                                        :columns="columns"
                                        :enable-row-selection="true"
                                        :show-column-toggle="false"
                                        empty-message="No students found with the selected status."
                                        @selection-change="handleSelectionChange"
                                    />
                                </div>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="handleClose"> Cancel </Button>
                    <Button type="submit" :disabled="loading || selectedStudentsCount === 0 || !values.fromStatus || !values.toStatus">
                        {{ loading ? 'Updating...' : `Update ${selectedStudentsCount} Student${selectedStudentsCount !== 1 ? 's' : ''}` }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
