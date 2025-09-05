<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import type { ClassSession, Lecture, Room } from '@/types/models';
import { toTypedSchema } from '@vee-validate/zod';
import { Calendar, Clock, Plus, Save, User, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

// Props
interface Props {
    open: boolean;
    courseOfferingId: number;
    campus_id: number;
}

const props = defineProps<Props>();

// Emits
const emit = defineEmits<{
    'update:open': [value: boolean];
    'session-created': [session: ClassSession];
}>();

const api = useApi();

// Form validation schema - all required fields for creating a new session
const sessionCreateSchema = toTypedSchema(
    z
        .object({
            session_title: z.string().min(1, 'Title is required').max(255, 'Title is too long'),
            session_description: z.string().optional(),
            session_date: z.string().min(1, 'Date is required'),
            start_time: z.string().regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)'),
            end_time: z.string().regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)'),
            session_type: z.enum(['lecture', 'tutorial', 'practical', 'workshop', 'seminar', 'exam'], {
                errorMap: () => ({ message: 'Please select a session type' }),
            }),
            delivery_mode: z.enum(['in_person', 'online', 'hybrid'], {
                errorMap: () => ({ message: 'Please select a delivery mode' }),
            }),
            status: z.enum(['scheduled', 'in_progress', 'completed', 'cancelled'], {
                errorMap: () => ({ message: 'Please select a status' }),
            }),
            lecture_id: z.number().min(1, 'Lecturer is required'),
            room_id: z.number().min(1, 'Room is required'),
            attendance_required: z.boolean(),
            attendance_tracking_enabled: z.boolean(),
            online_meeting_url: z.string().url('Invalid URL').optional().or(z.literal('')),
            instructor_notes: z.string().optional(),
        })
        .refine(
            (data) => {
                const start = new Date(`2000-01-01T${data.start_time}`);
                const end = new Date(`2000-01-01T${data.end_time}`);
                return end > start;
            },
            {
                message: 'End time must be after start time',
                path: ['end_time'],
            },
        ),
);

// Initialize form with default values
const { handleSubmit, resetForm, isSubmitting } = useForm({
    validationSchema: sessionCreateSchema,
    initialValues: {
        session_title: '',
        session_description: '',
        session_date: '',
        start_time: '',
        end_time: '',
        session_type: 'lecture' as const,
        delivery_mode: 'in_person' as const,
        status: 'scheduled' as const,
        lecture_id: 0,
        room_id: 0,
        attendance_required: true,
        attendance_tracking_enabled: true,
        online_meeting_url: '',
        instructor_notes: '',
    },
});

// Local state
const availableRooms = ref<Room[]>([]);
const availableLecturers = ref<Lecture[]>([]);
const isLoading = ref(false);

// Computed
const isOpen = computed({
    get: () => props.open,
    set: (value: boolean) => emit('update:open', value),
});

// Session type options
const sessionTypeOptions = [
    { value: 'lecture', label: 'Lecture' },
    { value: 'tutorial', label: 'Tutorial' },
    { value: 'practical', label: 'Practical' },
    { value: 'workshop', label: 'Workshop' },
    { value: 'seminar', label: 'Seminar' },
    { value: 'exam', label: 'Exam' },
];

// Delivery mode options
const deliveryModeOptions = [
    { value: 'in_person', label: 'In Person' },
    { value: 'online', label: 'Online' },
    { value: 'hybrid', label: 'Hybrid' },
];

// Status options
const statusOptions = [
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

// Methods
const loadDropdownData = async () => {
    isLoading.value = true;
    try {
        // Load available rooms for the current campus
        const roomsResponse = await api.get('/api/rooms', {
            campus_id: props.campus_id,
            status: 'available',
        });

        if (roomsResponse.data?.value?.success) {
            availableRooms.value = roomsResponse.data.value.data || [];
        }

        // Load available lecturers for the current campus
        const lecturersResponse = await api.get('/api/lectures', {
            campus_id: props.campus_id,
            is_active: true,
            is_available_for_assignment: true,
        });

        if (lecturersResponse.data?.value?.success) {
            availableLecturers.value = lecturersResponse.data.value.data || [];
        }
    } catch (error) {
        console.error('Error loading dropdown data:', error);
        toast.error('Failed to load form data');
    } finally {
        isLoading.value = false;
    }
};

const onSubmit = handleSubmit(async (formValues) => {
    try {
        const createData = {
            course_offering_id: props.courseOfferingId,
            session_title: formValues.session_title,
            session_description: formValues.session_description || '',
            session_date: formValues.session_date,
            start_time: formValues.start_time,
            end_time: formValues.end_time,
            session_type: formValues.session_type,
            delivery_mode: formValues.delivery_mode,
            status: formValues.status,
            attendance_required: formValues.attendance_required,
            attendance_tracking_enabled: formValues.attendance_tracking_enabled,
            online_meeting_url: formValues.online_meeting_url || '',
            instructor_notes: formValues.instructor_notes || '',
            lecture_id: formValues.lecture_id,
            room_id: formValues.room_id,
        };

        const response = await api.post('/api/class-sessions', createData);

        if (response.data?.value?.success) {
            toast.success('Class session created successfully');
            emit('session-created', response.data.value.data);
            handleClose();
        } else {
            toast.error(response.data?.value?.message || 'Failed to create class session');
        }
    } catch (error: any) {
        console.error('Error creating session:', error);
        if (error.response?.status === 422) {
            // Validation errors
            const validationErrors = error.response.data.errors;
            if (validationErrors) {
                Object.keys(validationErrors).forEach((field) => {
                    toast.error(validationErrors[field][0]);
                });
            }
        } else if (error.response?.status === 409) {
            // Conflict errors (room/lecturer conflicts)
            toast.error(error.response.data.message || 'Schedule conflict detected');
        } else if (error.response?.status === 400) {
            // Business logic errors (like exceeding total_sessions)
            toast.error(error.response.data.message || 'Cannot create session');
        } else {
            toast.error('Failed to create class session');
        }
    }
});

const handleClose = () => {
    resetForm();
    emit('update:open', false);
};

// Watch for modal open state
watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            await loadDropdownData();
        } else {
            resetForm();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="h-5 w-5" />
                    Add New Class Session
                </DialogTitle>
                <DialogDescription> Create a new class session for this course offering </DialogDescription>
            </DialogHeader>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-8">
                <div class="flex items-center space-x-2">
                    <div class="border-primary h-4 w-4 animate-spin rounded-full border-b-2"></div>
                    <span class="text-muted-foreground">Loading...</span>
                </div>
            </div>

            <!-- Form -->
            <form v-else @submit="onSubmit" class="space-y-4">
                <!-- Session Basic Info -->
                <div class="grid grid-cols-1 gap-4">
                    <!-- Session Title -->
                    <FormField v-slot="{ componentField }" name="session_title">
                        <FormItem>
                            <FormLabel>Session Title *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter session title" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Session Description -->
                    <FormField v-slot="{ componentField }" name="session_description">
                        <FormItem>
                            <FormLabel>Session Description</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Optional description for this session" rows="2" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Date and Time -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Teaching Date -->
                    <FormField v-slot="{ componentField }" name="session_date">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Calendar class="h-4 w-4" />
                                Teaching Date *
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="date" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Start Time -->
                    <FormField v-slot="{ componentField }" name="start_time">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                Start Time *
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- End Time -->
                    <FormField v-slot="{ componentField }" name="end_time">
                        <FormItem>
                            <FormLabel>End Time *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Session Type & Delivery Mode -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Session Type -->
                    <FormField v-slot="{ componentField }" name="session_type">
                        <FormItem>
                            <FormLabel>Session Type *</FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select session type" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="option in sessionTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Delivery Mode -->
                    <FormField v-slot="{ componentField }" name="delivery_mode">
                        <FormItem>
                            <FormLabel>Delivery Mode *</FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select delivery mode" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="option in deliveryModeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Lecturer & Room -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Lecturer -->
                    <FormField v-slot="{ componentField }" name="lecture_id">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <User class="h-4 w-4" />
                                Lecturer *
                            </FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a lecturer" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="lecturer in availableLecturers" :key="lecturer.id" :value="lecturer.id">
                                        {{ lecturer.display_name }}
                                        <span v-if="lecturer.department" class="text-muted-foreground ml-2 text-xs"> ({{ lecturer.department }}) </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Room -->
                    <FormField v-slot="{ componentField }" name="room_id">
                        <FormItem>
                            <FormLabel>Room *</FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a room" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="room in availableRooms" :key="room.id" :value="room.id">
                                        <div class="flex w-full items-center justify-between">
                                            <span>{{ room.name }}</span>
                                            <span class="text-muted-foreground text-xs"> {{ room.building?.name }} • {{ room.capacity }} seats </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Status & Online Meeting URL -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Status -->
                    <FormField v-slot="{ componentField }" name="status">
                        <FormItem>
                            <FormLabel>Status *</FormLabel>
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

                    <!-- Online Meeting URL -->
                    <FormField v-slot="{ componentField }" name="online_meeting_url">
                        <FormItem>
                            <FormLabel>Online Meeting URL</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="https://..." />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Attendance Settings -->
                <div class="space-y-3">
                    <FormField v-slot="{ componentField }" name="attendance_required">
                        <FormItem class="flex flex-row items-start space-x-3 space-y-0">
                            <FormControl>
                                <input
                                    type="checkbox"
                                    :checked="componentField.modelValue"
                                    @change="componentField['onUpdate:modelValue']?.($event.target.checked)"
                                    class="rounded border-gray-300 text-primary focus:ring-primary"
                                />
                            </FormControl>
                            <div class="space-y-1 leading-none">
                                <FormLabel> Attendance Required </FormLabel>
                            </div>
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="attendance_tracking_enabled">
                        <FormItem class="flex flex-row items-start space-x-3 space-y-0">
                            <FormControl>
                                <input
                                    type="checkbox"
                                    :checked="componentField.modelValue"
                                    @change="componentField['onUpdate:modelValue']?.($event.target.checked)"
                                    class="rounded border-gray-300 text-primary focus:ring-primary"
                                />
                            </FormControl>
                            <div class="space-y-1 leading-none">
                                <FormLabel> Enable Attendance Tracking </FormLabel>
                            </div>
                        </FormItem>
                    </FormField>
                </div>

                <!-- Instructor Notes -->
                <FormField v-slot="{ componentField }" name="instructor_notes">
                    <FormItem>
                        <FormLabel>Instructor Notes</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Optional notes for the instructor" rows="2" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="handleClose" :disabled="isSubmitting">
                    <X class="mr-2 h-4 w-4" />
                    Cancel
                </Button>
                <Button @click="onSubmit" :disabled="isSubmitting || isLoading">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isSubmitting ? 'Creating...' : 'Create Session' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
