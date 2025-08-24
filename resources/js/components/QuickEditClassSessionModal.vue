<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import type { ClassSession, Lecture, Room } from '@/types/models';
import { toTypedSchema } from '@vee-validate/zod';
import { Calendar, Clock, Edit, Save, User, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

// Props
interface Props {
    open: boolean;
    session: ClassSession | null;
    campus_id: number;
}

const props = defineProps<Props>();
// Emits
const emit = defineEmits<{
    'update:open': [value: boolean];
    'session-updated': [session: ClassSession];
}>();

const api = useApi();

// Form validation schema - only include editable fields
const sessionEditSchema = toTypedSchema(
    z
        .object({
            session_date: z.string().min(1, 'Date is required'),
            start_time: z.string().regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)'),
            end_time: z.string().regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)'),
            lecture_id: z.number().min(1, 'Lecturer is required'),
            session_title: z.string().min(1, 'Title is required').max(255, 'Title is too long'),
            room_id: z.number().min(1, 'Room is required'),
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

// Simple approach - use computed initial values
const formInitialized = ref(false);

// Reactive initial values based on current session
const initialValues = computed(() => {
    if (!props.session) {
        return {
            session_date: '',
            start_time: '',
            end_time: '',
            lecture_id: 0,
            session_title: '',
            room_id: 0,
        };
    }

    return {
        session_date: props.session.session_date || '',
        start_time: props.session.start_time || '',
        end_time: props.session.end_time || '',
        lecture_id: props.session.lecture_id || 0,
        session_title: props.session.session_title || '',
        room_id: props.session.room_id || 0,
    };
});

// Initialize form with proper handling
const { handleSubmit, resetForm, isSubmitting } = useForm({
    validationSchema: sessionEditSchema,
    initialValues: initialValues.value,
    validateOnMount: false,
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

// Methods
const loadDropdownData = async () => {
    if (!props.session) return;

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

const initializeForm = async () => {
    if (!props.session) return;

    // Reset form with current session data
    await nextTick();

    resetForm({
        values: {
            session_date: props.session.session_date || '',
            start_time: props.session.start_time || '',
            end_time: props.session.end_time || '',
            lecture_id: props.session.lecture_id || 0,
            session_title: props.session.session_title || '',
            room_id: props.session.room_id || 0,
        },
    });

    formInitialized.value = true;
};

const onSubmit = handleSubmit(async (formValues) => {
    if (!props.session) return;

    try {
        // Include all required fields from the original session to satisfy backend validation
        const updateData = {
            // Editable fields from form
            session_date: formValues.session_date,
            start_time: formValues.start_time,
            end_time: formValues.end_time,
            lecture_id: formValues.lecture_id,
            session_title: formValues.session_title,
            room_id: formValues.room_id,

            // Required fields from original session (unchanged)
            course_offering_id: props.session.course_offering_id,
            session_type: props.session.session_type || 'lecture',
            delivery_mode: props.session.delivery_mode || 'in_person',
            status: props.session.status || 'scheduled',

            // Optional fields from original session
            session_description: props.session.session_description || '',
            attendance_required: props.session.attendance_required || false,
            attendance_tracking_enabled: props.session.attendance_tracking_enabled || false,
            online_meeting_url: props.session.online_meeting_url || '',
            instructor_notes: props.session.instructor_notes || '',
            learning_objectives: props.session.learning_objectives || [],
            required_materials: props.session.required_materials || [],
            topics_covered: props.session.topics_covered || [],
        };

        const response = await api.put(`/class-sessions/${props.session.id}`, updateData);

        if (response.data?.value?.success) {
            toast.success('Class session updated successfully');
            emit('session-updated', response.data.value.data);
            handleClose();
        } else {
            toast.error(response.data?.value?.message || 'Failed to update class session');
        }
    } catch (error: any) {
        console.error('Error updating session:', error);
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
        } else {
            toast.error('Failed to update class session');
        }
    }
});

const handleClose = () => {
    formInitialized.value = false;
    resetForm();
    emit('update:open', false);
};

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
};

// Watch for session changes and modal open state
watch(
    () => [props.session, props.open],
    async ([newSession, isOpen]) => {
        if (isOpen && newSession) {
            formInitialized.value = false;
            await loadDropdownData();
            await initializeForm();
        } else if (!isOpen) {
            formInitialized.value = false;
            resetForm();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Edit class="h-5 w-5" />
                    Quick Edit Class Session
                </DialogTitle>
                <DialogDescription> Update the basic details of this class session </DialogDescription>
            </DialogHeader>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-8">
                <div class="flex items-center space-x-2">
                    <div class="border-primary h-4 w-4 animate-spin rounded-full border-b-2"></div>
                    <span class="text-muted-foreground">Loading...</span>
                </div>
            </div>

            <!-- Form -->
            <form v-else-if="session" @submit="onSubmit" class="space-y-4">
                <!-- Current Session Info (Read-only) -->
                <div class="bg-muted/30 space-y-1 rounded-lg p-3">
                    <div class="text-sm font-medium">{{ session.session_title }}</div>
                    <div class="text-muted-foreground text-xs">Current: {{ formatDate(session.session_date) }} at {{ session.start_time }} - {{ session.end_time }}</div>
                </div>

                <!-- Session Title -->
                <FormField v-slot="{ componentField }" name="session_title">
                    <FormItem>
                        <FormLabel>Session Title *</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="Enter session title" />
                        </FormControl>
                        <FormMessage v-if="formInitialized" />
                    </FormItem>
                </FormField>

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
                        <FormMessage v-if="formInitialized" />
                    </FormItem>
                </FormField>

                <!-- Time Range -->
                <div class="grid grid-cols-2 gap-4">
                    <FormField v-slot="{ componentField }" name="start_time">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                Start Time *
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" />
                            </FormControl>
                            <FormMessage v-if="formInitialized" />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="end_time">
                        <FormItem>
                            <FormLabel>End Time *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" />
                            </FormControl>
                            <FormMessage v-if="formInitialized" />
                        </FormItem>
                    </FormField>
                </div>

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
                        <FormMessage v-if="formInitialized" />
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
                        <FormMessage v-if="formInitialized" />
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
                    {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
