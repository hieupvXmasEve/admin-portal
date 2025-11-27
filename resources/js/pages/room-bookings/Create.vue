<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import type { Room } from '@/types/models';
import { formatDateTime } from '@/utils/date';
import { Head, router, useForm as useInertiaForm, usePage } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useDebounceFn } from '@vueuse/core';
import { AlertTriangle, CheckCircle } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

const props = defineProps<{
    selected_room?: Room;
    booking_types?: Array<{ value: string; label: string }>;
    priorities?: Array<{ value: string; label: string }>;
    buildings?: Array<{ id: number; value: string; label: string }>;
    rooms?: Array<{ id: number; value: string; label: string; building_id: number; capacity: number }>;
}>();

// Zod schema
const bookingSchema = z.object({
    room_id: z.number({ required_error: 'Please select a room' }).min(1, 'Please select a room'),
    title: z.string().min(1, 'Title is required').max(200, 'Title must be at most 200 characters'),
    description: z.string().max(2000).optional().nullable(),
    booking_date: z.string().min(1, 'Booking date is required'),
    start_time: z.string().min(1, 'Start time is required'),
    end_time: z.string().min(1, 'End time is required'),
    booking_type: z.string().min(1, 'Booking type is required'),
    priority: z.string().optional().nullable(),
    contact_person: z.string().max(100).optional().nullable(),
    contact_phone: z.string().max(20).optional().nullable(),
    contact_email: z.string().email().max(255).optional().nullable().or(z.literal('')),
    special_requirements: z.string().max(2000).optional().nullable(),
});

type BookingFormValues = z.infer<typeof bookingSchema>;

// Get today's date in YYYY-MM-DD format (local timezone, not UTC)
const today = (() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
})();

// Initial values
const initialValues: BookingFormValues = {
    room_id: props.selected_room?.id ?? 0,
    title: '',
    description: '',
    booking_date: today,
    start_time: '09:00',
    end_time: '10:00',
    booking_type: 'meeting',
    priority: 'normal',
    contact_person: '',
    contact_phone: '',
    contact_email: '',
    special_requirements: '',
};

// Form setup
const { handleSubmit, values, setFieldValue, setErrors } = useForm<BookingFormValues>({
    validationSchema: toTypedSchema(bookingSchema),
    initialValues,
});

const inertiaForm = useInertiaForm(initialValues);

// Get errors from Inertia props
const page = usePage();
const errors = computed(() => (page.props.errors as Record<string, string>) || {});

// Sync Inertia errors to vee-validate when they change
watch(
    () => errors.value,
    (newErrors) => {
        if (newErrors && Object.keys(newErrors).length > 0) {
            // Set errors in vee-validate
            Object.entries(newErrors).forEach(([field, message]) => {
                if (field !== 'general') {
                    setErrors({ [field]: message });
                }
            });
        }
    },
    { immediate: true, deep: true },
);

// Filter rooms by selected building
const selectedBuildingId = ref<number | null>(null);
const filteredRooms = computed(() => {
    if (!selectedBuildingId.value) return props.rooms || [];
    return props.rooms?.filter((r) => r.building_id === selectedBuildingId.value) || [];
});

// Conflict checking
interface Conflict {
    type: 'room_booking' | 'class_session';
    id: number;
    title: string;
    start_time: string;
    end_time: string;
    status: string;
    is_editable?: boolean;
}

const conflicts = ref<Conflict[]>([]);
const isCheckingConflicts = ref(false);
const hasCheckedConflicts = ref(false);

const api = useApi();

const checkConflicts = useDebounceFn(async () => {
    if (!values.room_id || !values.booking_date || !values.start_time || !values.end_time) {
        conflicts.value = [];
        hasCheckedConflicts.value = false;
        return;
    }

    isCheckingConflicts.value = true;
    try {
        const response = await api.post<{ has_conflicts: boolean; conflicts: Conflict[] }>('/api/room-bookings/check-conflicts', {
            room_id: values.room_id,
            booking_date: values.booking_date,
            start_time: values.start_time,
            end_time: values.end_time,
        });

        // Response structure from API: { success: true, has_conflicts: boolean, conflicts: Conflict[] }
        // useApi returns { data: { value: ApiResponse<T> } }
        // But the actual API response structure is { success, has_conflicts, conflicts } directly
        const responseData = response.data.value as any;

        if (responseData?.success) {
            // Conflicts are directly in response.data.value, not wrapped in data property
            conflicts.value = responseData.conflicts || [];
            hasCheckedConflicts.value = true;
        } else {
            conflicts.value = [];
            hasCheckedConflicts.value = false;
        }
    } catch (error) {
        console.error('Error checking conflicts:', error);
        conflicts.value = [];
        hasCheckedConflicts.value = false;
    } finally {
        isCheckingConflicts.value = false;
    }
}, 500);

// Watch for room changes to get building
watch(
    () => values.room_id,
    (newRoomId) => {
        if (newRoomId) {
            const room = props.rooms?.find((r) => r.id === newRoomId);
            if (room) {
                selectedBuildingId.value = room.building_id;
            }
        }
        checkConflicts();
    },
);

// Watch for time/date changes to check conflicts
watch(
    () => [values.booking_date, values.start_time, values.end_time],
    () => {
        checkConflicts();
    },
);

const onSubmit = handleSubmit((formValues) => {
    // Check for conflicts before submitting
    if (conflicts.value.length > 0) {
        toast.error('Cannot create booking due to time slot conflicts. Please choose a different time.');
        return;
    }

    // Prepare data for submission - convert empty strings to null for optional fields
    const submitData = {
        ...formValues,
        description: formValues.description || null,
        priority: formValues.priority || 'normal',
        contact_person: formValues.contact_person || null,
        contact_phone: formValues.contact_phone || null,
        contact_email: formValues.contact_email || null,
        special_requirements: formValues.special_requirements || null,
    };

    // Update inertia form with prepared data
    Object.assign(inertiaForm, submitData);

    inertiaForm.post('/room-bookings', {
        preserveScroll: true,
        preserveState: true, // Preserve form state when there's an error to keep user input
        onSuccess: () => {
            // Inertia will automatically handle the redirect from server
            // The server returns redirect()->route('room-bookings.show', $booking)
            // So we don't need to manually redirect here
        },
        onError: (errors) => {
            // Errors are automatically synced via watch above
            // Display general error if exists
            if (errors.general) {
                toast.error(errors.general);
            }
        },
        onFinish: () => {
            // Reset conflict checking state only on success
            // Keep it on error so user can see conflicts
            if (!inertiaForm.hasErrors && Object.keys(errors.value).length === 0) {
                hasCheckedConflicts.value = false;
                conflicts.value = [];
            }
        },
    });
});

const goBack = () => router.visit('/room-bookings-my');
</script>

<template>
    <Head title="New Room Booking" />

    <div class="mb-6">
        <h1 class="text-2xl font-semibold">New Room Booking</h1>
        <p class="text-muted-foreground mt-1">Book a room for your meeting, class, or event</p>
    </div>

    <!-- General Error Alert -->
    <Alert v-if="errors.general" variant="destructive" class="mb-6">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>Error</AlertTitle>
        <AlertDescription>{{ errors.general }}</AlertDescription>
    </Alert>

    <form @submit="onSubmit" class="space-y-6">
        <!-- Room Selection -->
        <Card>
            <CardHeader>
                <CardTitle>Room Selection</CardTitle>
                <CardDescription>Choose a room for your booking</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Building (Optional Filter)</label>
                        <Select :model-value="selectedBuildingId?.toString() || 'all'" @update:model-value="(v) => (selectedBuildingId = v === 'all' ? null : Number(v))">
                            <SelectTrigger>
                                <SelectValue placeholder="All Buildings" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Buildings</SelectItem>
                                <SelectItem v-for="building in buildings" :key="building.id" :value="building.value">
                                    {{ building.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <FormField name="room_id" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Room *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField" :model-value="values.room_id?.toString()" @update:model-value="(v) => setFieldValue('room_id', Number(v))">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a room" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="room in filteredRooms" :key="room.id" :value="room.value"> {{ room.label }} ({{ room.capacity }} seats) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>
            </CardContent>
        </Card>

        <!-- Booking Details -->
        <Card>
            <CardHeader>
                <CardTitle>Booking Details</CardTitle>
                <CardDescription>Provide information about your booking</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <FormField name="title" v-slot="{ componentField }">
                    <FormItem>
                        <FormLabel>Title *</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="e.g., Team Meeting, Class Session" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField name="description" v-slot="{ componentField }">
                    <FormItem>
                        <FormLabel>Description</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Brief description of the booking purpose" rows="3" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <div class="grid grid-cols-2 gap-4">
                    <FormField name="booking_type" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Booking Type *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField" :model-value="values.booking_type" @update:model-value="(v) => setFieldValue('booking_type', String(v || ''))">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="type in booking_types" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField name="priority" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Priority</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField" :model-value="values.priority || 'normal'" @update:model-value="(v) => setFieldValue('priority', v ? String(v) : 'normal')">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select priority" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="p in priorities" :key="p.value" :value="p.value">
                                            {{ p.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>
            </CardContent>
        </Card>

        <!-- Date & Time -->
        <Card>
            <CardHeader>
                <CardTitle>Date & Time</CardTitle>
                <CardDescription>Select when you need the room</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <FormField name="booking_date" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Date *</FormLabel>
                            <FormControl>
                                <Input type="date" v-bind="componentField" :min="today" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField name="start_time" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Start Time *</FormLabel>
                            <FormControl>
                                <Input type="time" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField name="end_time" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>End Time *</FormLabel>
                            <FormControl>
                                <Input type="time" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>
            </CardContent>
        </Card>

        <!-- Conflict Alert -->
        <Alert v-if="conflicts.length > 0" variant="destructive">
            <AlertTriangle class="h-4 w-4" />
            <AlertTitle>Time Slot Conflict</AlertTitle>
            <AlertDescription>
                <p class="mb-2">This time slot conflicts with the following:</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li v-for="conflict in conflicts" :key="conflict.id" class="text-sm">
                        <span class="font-medium">{{ conflict.title }}</span>
                        <span class="text-muted-foreground"> ({{ formatDateTime(conflict.start_time) }} - {{ formatDateTime(conflict.end_time) }}) </span>
                        <span v-if="conflict.type === 'class_session'" class="ml-1 rounded bg-indigo-100 px-1 text-xs text-indigo-700"> Class Session </span>
                        <span v-else class="ml-1 rounded bg-amber-100 px-1 text-xs text-amber-700 capitalize">
                            {{ conflict.status }}
                        </span>
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-else-if="hasCheckedConflicts && values.room_id && values.booking_date && values.start_time && values.end_time" variant="default" class="border-green-200 bg-green-50">
            <CheckCircle class="h-4 w-4 text-green-600" />
            <AlertTitle class="text-green-800">Available</AlertTitle>
            <AlertDescription class="text-green-700"> This time slot is available for booking. </AlertDescription>
        </Alert>

        <div v-else-if="isCheckingConflicts" class="text-muted-foreground py-2 text-center text-sm">Checking availability...</div>

        <!-- Contact Information -->
        <Card>
            <CardHeader>
                <CardTitle>Contact Information</CardTitle>
                <CardDescription>Optional contact details for this booking</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <FormField name="contact_person" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Contact Person</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Name" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField name="contact_phone" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Phone</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Phone number" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField name="contact_email" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Email</FormLabel>
                            <FormControl>
                                <Input type="email" v-bind="componentField" placeholder="Email address" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <FormField name="special_requirements" v-slot="{ componentField }">
                    <FormItem>
                        <FormLabel>Special Requirements</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Any special setup or equipment requirements" rows="2" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </CardContent>
        </Card>

        <!-- Actions -->
        <div class="flex justify-end gap-4">
            <Button type="button" variant="outline" @click="goBack">Cancel</Button>
            <Button type="submit" :disabled="inertiaForm.processing || conflicts.length > 0 || isCheckingConflicts">
                {{ inertiaForm.processing ? 'Creating...' : conflicts.length > 0 ? 'Time Slot Unavailable' : 'Create Booking' }}
            </Button>
        </div>
    </form>
</template>
