<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { RoomBooking } from '@/types/models';
import { Head, router, useForm as useInertiaForm, usePage } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

const props = defineProps<{
    booking: RoomBooking;
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

const today = new Date().toISOString().split('T')[0];

// Format time for input
const formatTimeForInput = (time: string) => {
    if (!time) return '';
    // Handle both "HH:mm:ss" and "HH:mm" formats
    return time.substring(0, 5);
};

// Format date for input (handle both string and date object)
const formatDateForInput = (date: string | Date): string => {
    if (!date) return '';
    // If already formatted as YYYY-MM-DD, return as is
    if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return date;
    }
    // If it's a date string with time, extract date part
    if (typeof date === 'string' && date.includes('T')) {
        return date.split('T')[0];
    }
    // If it's a Date object or other format, format it
    const d = typeof date === 'string' ? new Date(date) : date;
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Initial values from existing booking
const initialValues: BookingFormValues = {
    room_id: props.booking.room_id,
    title: props.booking.title,
    description: props.booking.description || '',
    booking_date: formatDateForInput(props.booking.booking_date),
    start_time: formatTimeForInput(props.booking.start_time),
    end_time: formatTimeForInput(props.booking.end_time),
    booking_type: props.booking.booking_type,
    priority: props.booking.priority || 'normal',
    contact_person: props.booking.contact_person || '',
    contact_phone: props.booking.contact_phone || '',
    contact_email: props.booking.contact_email || '',
    special_requirements: props.booking.special_requirements || '',
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
const selectedBuildingId = ref<number | null>((props.booking.room as any)?.building_id ?? (props.booking.room as any)?.building?.id ?? null);
const filteredRooms = computed(() => {
    if (!selectedBuildingId.value) return props.rooms || [];
    return props.rooms?.filter((r) => r.building_id === selectedBuildingId.value) || [];
});

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
    },
);

const onSubmit = handleSubmit((formValues) => {
    Object.assign(inertiaForm, formValues);

    inertiaForm.put(`/room-bookings/${props.booking.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success('Booking updated successfully');
        },
        onError: (formErrors) => {
            // Errors are automatically synced via watch above
            // Display general error if exists
            if (formErrors.general) {
                toast.error(formErrors.general);
            } else {
                // Show first field error
                const firstError = Object.values(formErrors)[0];
                if (firstError) {
                    toast.error((firstError as string) || 'Failed to update booking');
                }
            }
        },
    });
});

const goBack = () => router.visit(`/room-bookings/${props.booking.id}`);
</script>

<template>
    <Head :title="`Edit Booking: ${booking.title}`" />

    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Edit Booking</h1>
        <p class="text-muted-foreground mt-1">Update booking details</p>
    </div>

    <form @submit="onSubmit" class="space-y-6">
        <!-- Room Selection -->
        <Card>
            <CardHeader>
                <CardTitle>Room Selection</CardTitle>
                <CardDescription>Change the room for this booking</CardDescription>
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
                <CardDescription>Update booking information</CardDescription>
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
                <CardDescription>Update booking schedule</CardDescription>
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

        <!-- Contact Information -->
        <Card>
            <CardHeader>
                <CardTitle>Contact Information</CardTitle>
                <CardDescription>Update contact details</CardDescription>
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
            <Button type="submit" :disabled="inertiaForm.processing">
                {{ inertiaForm.processing ? 'Saving...' : 'Save Changes' }}
            </Button>
        </div>
    </form>
</template>
