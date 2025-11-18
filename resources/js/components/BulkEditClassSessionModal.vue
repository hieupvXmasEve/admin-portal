<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import type { ClassSession, Lecture, Room } from '@/types/models';
import { toTypedSchema } from '@vee-validate/zod';
import { Clock, Edit, Save, User, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

// Props
interface Props {
    open: boolean;
    selectedSessions: ClassSession[];
    campus_id: number;
}

const props = defineProps<Props>();
// Emits
const emit = defineEmits<{
    'update:open': [value: boolean];
    'sessions-updated': [];
}>();

const api = useApi();

// Form validation schema - only include editable fields for bulk update
const bulkEditSchema = toTypedSchema(
    z
        .object({
            start_time: z.string().regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)').optional().or(z.literal('')),
            end_time: z.string().regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)').optional().or(z.literal('')),
            lecture_id: z.number().min(1, 'Lecturer is required').optional().or(z.literal(0)),
            room_id: z.number().min(1, 'Room is required').optional().or(z.literal(0)),
        })
        .refine(
            (data) => {
                // Only validate if both times are provided
                if (!data.start_time || !data.end_time) return true;
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

// Initialize form
const { handleSubmit, resetForm, isSubmitting } = useForm({
    validationSchema: bulkEditSchema,
    initialValues: {
        start_time: '',
        end_time: '',
        lecture_id: 0,
        room_id: 0,
    },
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

const selectedCount = computed(() => props.selectedSessions.length);

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
    if (props.selectedSessions.length === 0) {
        toast.error('No sessions selected');
        return;
    }

    try {
        // Build update data - only include fields that have values
        const updateData: Record<string, any> = {};

        if (formValues.start_time) {
            updateData.start_time = formValues.start_time;
        }
        if (formValues.end_time) {
            updateData.end_time = formValues.end_time;
        }
        if (formValues.lecture_id && formValues.lecture_id > 0) {
            updateData.lecture_id = formValues.lecture_id;
        }
        if (formValues.room_id && formValues.room_id > 0) {
            updateData.room_id = formValues.room_id;
        }

        // Check if there's anything to update
        if (Object.keys(updateData).length === 0) {
            toast.error('Please select at least one field to update');
            return;
        }

        // Get course offering ID from first session
        const courseOfferingId = props.selectedSessions[0]?.course_offering_id;
        if (!courseOfferingId) {
            toast.error('Invalid session data');
            return;
        }

        // Get session IDs
        const sessionIds = props.selectedSessions.map((s) => s.id);

        // Call bulk update API
        const response = await api.post(`/api/course-offerings/${courseOfferingId}/class-sessions/bulk-update`, {
            session_ids: sessionIds,
            ...updateData,
        });

        if (response.data?.value?.success) {
            toast.success(`Successfully updated ${selectedCount.value} class session(s)`);
            emit('sessions-updated');
            handleClose();
        } else {
            toast.error(response.data?.value?.message || 'Failed to update class sessions');
        }
    } catch (error: any) {
        console.error('Error updating sessions:', error);
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
            toast.error('Failed to update class sessions');
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
            resetForm({
                values: {
                    start_time: '',
                    end_time: '',
                    lecture_id: 0,
                    room_id: 0,
                },
            });
        } else {
            resetForm();
        }
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Edit class="h-5 w-5" />
                    Bulk Edit Class Sessions
                </DialogTitle>
                <DialogDescription>
                    Update {{ selectedCount }} selected session(s). Leave fields empty to keep current values.
                </DialogDescription>
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
                <!-- Time Range -->
                <div class="grid grid-cols-2 gap-4">
                    <FormField v-slot="{ componentField }" name="start_time">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                Start Time
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" placeholder="Keep current" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="end_time">
                        <FormItem>
                            <FormLabel>End Time</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" placeholder="Keep current" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Lecturer -->
                <FormField v-slot="{ componentField }" name="lecture_id">
                    <FormItem>
                        <FormLabel class="flex items-center gap-2">
                            <User class="h-4 w-4" />
                            Lecturer
                        </FormLabel>
                        <Select v-bind="componentField">
                            <FormControl>
                                <SelectTrigger>
                                    <SelectValue placeholder="Keep current lecturer" />
                                </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                <SelectItem :value="0">Keep current lecturer</SelectItem>
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
                        <FormLabel>Room</FormLabel>
                        <Select v-bind="componentField">
                            <FormControl>
                                <SelectTrigger>
                                    <SelectValue placeholder="Keep current room" />
                                </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                <SelectItem :value="0">Keep current room</SelectItem>
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
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="handleClose" :disabled="isSubmitting">
                    <X class="mr-2 h-4 w-4" />
                    Cancel
                </Button>
                <Button @click="onSubmit" :disabled="isSubmitting || isLoading">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isSubmitting ? 'Updating...' : `Update ${selectedCount} Session(s)` }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

