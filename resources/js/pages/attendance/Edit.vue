<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { Attendance, ClassSession, Student } from '@/types/models';
import { formatDate } from '@/utils/date';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Save, UserCheck, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    attendance: Attendance;
    classSessions: ClassSession[];
    students: Student[];
}

const props = defineProps<Props>();

const isSubmitting = ref(false);

// Form validation schema
const formSchema = toTypedSchema(
    z
        .object({
            class_session_id: z.string().min(1, 'Class session is required'),
            student_id: z.string().min(1, 'Student is required'),
            status: z.enum(['present', 'late', 'absent', 'excused'], {
                required_error: 'Attendance status is required',
            }),
            check_in_time: z.string().optional(),
            check_out_time: z.string().optional(),
            minutes_late: z.number().min(0).optional(),
            minutes_present: z.number().min(0).optional(),
            recording_method: z.enum(['manual', 'qr_code', 'rfid', 'geolocation', 'biometric', 'mobile_app'], {
                required_error: 'Recording method is required',
            }),
            participation_level: z.enum(['excellent', 'good', 'average', 'poor']).optional(),
            participation_score: z.number().min(0).max(100).optional(),
            participation_notes: z.string().optional(),
            notes: z.string().optional(),
            excuse_reason: z.string().optional(),
            is_verified: z.boolean().default(false),
        })
        .refine(
            (data) => {
                if (data.check_in_time && data.check_out_time) {
                    const checkIn = new Date(`2000-01-01T${data.check_in_time}`);
                    const checkOut = new Date(`2000-01-01T${data.check_out_time}`);
                    return checkOut >= checkIn;
                }
                return true;
            },
            {
                message: 'Check-out time must be after check-in time',
                path: ['check_out_time'],
            },
        )
        .refine(
            (data) => {
                if (data.status === 'excused' && !data.excuse_reason) {
                    return false;
                }
                return true;
            },
            {
                message: 'Excuse reason is required for excused absences',
                path: ['excuse_reason'],
            },
        ),
);

// Initialize form with existing data
const { handleSubmit, values } = useForm({
    validationSchema: formSchema,
    initialValues: {
        class_session_id: props.attendance.class_session_id?.toString() || '',
        student_id: props.attendance.student_id?.toString() || '',
        status: props.attendance.status || 'present',
        check_in_time: props.attendance.check_in_time?.substring(11, 16) || '',
        check_out_time: props.attendance.check_out_time?.substring(11, 16) || '',
        minutes_late: props.attendance.minutes_late || undefined,
        minutes_present: props.attendance.minutes_present || undefined,
        recording_method: props.attendance.recording_method || 'manual',
        participation_level: props.attendance.participation_level || undefined,
        participation_score: props.attendance.participation_score || undefined,
        participation_notes: props.attendance.participation_notes || '',
        notes: props.attendance.notes || '',
        excuse_reason: props.attendance.excuse_reason || '',
        is_verified: props.attendance.is_verified || false,
    },
});

// Form submission
const onSubmit = handleSubmit((formValues) => {
    isSubmitting.value = true;

    const data = {
        ...formValues,
        class_session_id: parseInt(formValues.class_session_id),
        student_id: parseInt(formValues.student_id),
    };

    router.put(`/attendance/${props.attendance.id}`, data, {
        onSuccess: () => {
            toast.success('Attendance record updated successfully');
            router.visit('/attendance');
        },
        onError: (errors) => {
            toast.error('Failed to update attendance record');
            console.error('Form errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});

// Navigation
const navigateBack = () => {
    router.visit('/attendance');
};

const navigateToShow = () => {
    // router.visit(`/attendance/${props.attendance.id}`);
};

// Options for dropdowns
const statusOptions = [
    { value: 'present', label: 'Present' },
    { value: 'late', label: 'Late' },
    { value: 'absent', label: 'Absent' },
    { value: 'excused', label: 'Excused' },
];

const recordingMethodOptions = [
    { value: 'manual', label: 'Manual' },
    { value: 'qr_code', label: 'QR Code' },
    { value: 'rfid', label: 'RFID' },
    { value: 'geolocation', label: 'Geolocation' },
    { value: 'biometric', label: 'Biometric' },
    { value: 'mobile_app', label: 'Mobile App' },
];

const participationLevelOptions = [
    { value: 'excellent', label: 'Excellent' },
    { value: 'good', label: 'Good' },
    { value: 'average', label: 'Average' },
    { value: 'poor', label: 'Poor' },
];

// Show time fields for present/late status
const showTimeFields = computed(() => {
    return ['present', 'late'].includes(values.status || '');
});

// Show excuse reason for excused status
const showExcuseReason = computed(() => {
    return values.status === 'excused';
});
</script>

<template>
    <Head :title="`Edit Attendance - ${attendance.student?.full_name}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="navigateBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Attendance
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Edit Attendance Record</h1>
                    <p class="text-muted-foreground">
                        Update attendance for <span class="text-primary font-bold">{{ attendance.student?.full_name }}</span>
                    </p>
                </div>
            </div>
            <Button variant="outline" @click="navigateToShow"> View Details </Button>
        </div>

        <form @submit="onSubmit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Form -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Basic Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <UserCheck class="h-5 w-5" />
                                Attendance Details
                            </CardTitle>
                            <CardDescription> Update the session, student, and attendance status. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Class Session -->
                            <FormField v-slot="{ componentField }" name="class_session_id">
                                <FormItem>
                                    <FormLabel>Class Session *</FormLabel>
                                    <Select v-bind="componentField" :disabled="true">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a class session" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="session in classSessions" :key="session.id" :value="session.id.toString()">
                                                {{ session.course_offering?.curriculum_unit?.unit.code }} -
                                                {{ session.session_title }}
                                                ({{ formatDate(session.session_date) }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- Student -->
                            <FormField v-slot="{ componentField }" name="student_id">
                                <FormItem>
                                    <FormLabel>Student *</FormLabel>
                                    <Select v-bind="componentField" :disabled="true">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a student" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="student in students" :key="student.id" :value="student.id.toString()"> {{ student.student_id }} - {{ student.full_name }} </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- Status -->
                            <FormField v-slot="{ componentField }" name="status">
                                <FormItem>
                                    <FormLabel>Attendance Status *</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- Recording Method -->
                            <FormField v-slot="{ componentField }" name="recording_method">
                                <FormItem>
                                    <FormLabel>Recording Method *</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select method" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="option in recordingMethodOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>

                    <!-- Time Details -->
                    <Card v-if="showTimeFields">
                        <CardHeader>
                            <CardTitle>Time Details</CardTitle>
                            <CardDescription> Update check-in and check-out times for present/late status. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Check-in Time -->
                                <FormField v-slot="{ componentField }" name="check_in_time">
                                    <FormItem>
                                        <FormLabel>Check-in Time</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="time" placeholder="HH:MM" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <!-- Check-out Time -->
                                <FormField v-slot="{ componentField }" name="check_out_time">
                                    <FormItem>
                                        <FormLabel>Check-out Time</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="time" placeholder="HH:MM" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Minutes Late -->
                                <FormField v-slot="{ componentField }" name="minutes_late">
                                    <FormItem>
                                        <FormLabel>Minutes Late</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="number" min="0" placeholder="0" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <!-- Minutes Present -->
                                <FormField v-slot="{ componentField }" name="minutes_present">
                                    <FormItem>
                                        <FormLabel>Minutes Present</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="number" min="0" placeholder="0" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Participation -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Participation</CardTitle>
                            <CardDescription> Update student participation level and score if applicable. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <!-- Participation Level -->
                                <FormField v-slot="{ componentField }" name="participation_level">
                                    <FormItem>
                                        <FormLabel>Participation Level</FormLabel>
                                        <Select v-bind="componentField">
                                            <FormControl>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select level" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent>
                                                <SelectItem v-for="option in participationLevelOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <!-- Participation Score -->
                                <FormField v-slot="{ componentField }" name="participation_score">
                                    <FormItem>
                                        <FormLabel>Participation Score (0-100)</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="number" min="0" max="100" placeholder="0" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <!-- Participation Notes -->
                            <FormField v-slot="{ componentField }" name="participation_notes">
                                <FormItem>
                                    <FormLabel>Participation Notes</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Notes about student participation" rows="3" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>

                    <!-- Notes and Reasons -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Additional Information</CardTitle>
                            <CardDescription> Update any additional notes or excuse reasons. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Excuse Reason -->
                            <FormField v-if="showExcuseReason" v-slot="{ componentField }" name="excuse_reason">
                                <FormItem>
                                    <FormLabel>Excuse Reason *</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Reason for excused absence" rows="3" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- General Notes -->
                            <FormField v-slot="{ componentField }" name="notes">
                                <FormItem>
                                    <FormLabel>Notes</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Additional notes about this attendance record" rows="3" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>
                </div>

                <!-- Settings Sidebar -->
                <div class="space-y-6">
                    <!-- Verification -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Verification</CardTitle>
                            <CardDescription> Mark this attendance record as verified. </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FormField v-slot="{ componentField }" name="is_verified">
                                <FormItem>
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-0.5">
                                            <FormLabel>Verified</FormLabel>
                                            <p class="text-muted-foreground text-xs">Mark as verified by administrator</p>
                                        </div>
                                        <FormControl>
                                            <Switch v-bind="componentField" />
                                        </FormControl>
                                    </div>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Button type="submit" class="w-full" :disabled="isSubmitting">
                                <Save class="mr-2 h-4 w-4" />
                                {{ isSubmitting ? 'Updating...' : 'Update Record' }}
                            </Button>

                            <Button type="button" variant="outline" class="w-full" @click="navigateBack" :disabled="isSubmitting">
                                <X class="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Status Help -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Status Guidelines</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="text-sm"><strong>Present:</strong> Student attended on time</div>
                            <div class="text-sm"><strong>Late:</strong> Student arrived after start time</div>
                            <div class="text-sm"><strong>Absent:</strong> Student did not attend</div>
                            <div class="text-sm"><strong>Excused:</strong> Student absent with valid reason</div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    </div>
</template>
