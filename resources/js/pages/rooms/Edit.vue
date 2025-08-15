<script setup lang="ts">
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { roomFormSchema } from '@/schemas/room';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Building2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useForm } from 'vee-validate';
import { toast } from 'vue-sonner';

// Accept backend-provided props
const props = defineProps<{
    room: Record<string, any>;
    buildings: Array<{ id: number; label: string; name: string; code: string }>;
    room_types: Array<{ value: string; label: string }>;
    room_statuses: Array<{ value: string; label: string }>;
    days_of_week: Array<{ value: string; label: string }>;
}>();

// Helpers to normalize time values
const toHM = (val?: string | null) => {
    if (!val) return '';
    // Expect HH:MM or HH:MM:SS; return HH:MM for form
    const m = String(val).match(/^\d{1,2}:\d{2}(?::\d{2})?$/);
    if (!m) return '';
    return String(val).length >= 5 ? String(val).slice(0, 5) : '';
};

const toHMS = (val?: string | null) => {
    if (!val) return null;
    if (/^\d{1,2}:\d{2}:\d{2}$/.test(val)) return val;
    if (/^\d{1,2}:\d{2}$/.test(val)) return `${val}:00`;
    return null;
};

// Vee-validate form bound to AutoForm (so we can control initial values)
const { handleSubmit, meta, isSubmitting } = useForm({
    validationSchema: toTypedSchema(roomFormSchema),
    initialValues: {
        name: props.room.name ?? '',
        code: props.room.code ?? '',
        building_id: String(props.room.building_id ?? ''),
        floor: props.room.floor ?? '',
        type: props.room.type ?? (props.room_types?.[0]?.value ?? 'classroom'),
        capacity: props.room.capacity ?? 30,
        status: props.room.status ?? (props.room_statuses?.[0]?.value ?? 'available'),
        is_bookable: Boolean(props.room.is_bookable),
        requires_approval: Boolean(props.room.requires_approval),
        available_from: toHM(props.room.available_from),
        available_until: toHM(props.room.available_until),
        // blocked_days: Array.isArray(props.room.blocked_days) ? props.room.blocked_days : [],
        description: props.room.description ?? '',
        usage_guidelines: props.room.usage_guidelines ?? '',
        booking_notes: props.room.booking_notes ?? '',
    },
});

// Field configuration for AutoForm
const fieldConfig = computed(() => ({
    name: {
        description: 'Enter the name of the room (e.g., Lecture Theatre 1, Lab A)',
        inputProps: { placeholder: 'e.g., Lecture Theatre 1, Lab A' },
    },
    code: {
        description: 'Unique identifier for the room',
        inputProps: { placeholder: 'e.g., LT1, LAB-A, CR101' },
    },
    building_id: {
        label: 'Building',
        description: 'Select the building where this room is located',
        component: 'select',
        inputProps: {
            placeholder: 'Select a building',
            options: props.buildings.map((b) => ({ value: String(b.id), label: b.label })),
        },
    },
    floor: {
        description: 'Floor number or name where the room is located',
        inputProps: { placeholder: 'e.g., Ground, 1st, 2nd' },
    },
    type: {
        label: 'Room Type',
        description: 'Select the type of room',
        component: 'select',
        inputProps: { placeholder: 'Select room type', options: props.room_types },
    },
    capacity: {
        description: 'Maximum number of people the room can accommodate',
        inputProps: { type: 'number', min: '1', max: '10000' },
    },
    status: {
        label: 'Room Status',
        description: 'Current status of the room',
        component: 'select',
        inputProps: { placeholder: 'Select status', options: props.room_statuses },
    },
    is_bookable: {
        label: 'Is Bookable',
        description: 'Can this room be booked by users?',
        component: 'switch',
    },
    requires_approval: {
        label: 'Requires Approval',
        description: 'Does booking this room require approval?',
        component: 'switch',
    },
    available_from: {
        label: 'Available From',
        description: 'Start time for daily availability',
        inputProps: { type: 'time' },
    },
    available_until: {
        label: 'Available Until',
        description: 'End time for daily availability',
        inputProps: { type: 'time' },
    },
    // blocked_days: {
    //     // Provide labeled options for the inner enum selector
    //     inputProps: { options: props.days_of_week },
    // },
    description: {
        component: 'textarea',
        inputProps: { placeholder: 'Brief description of the room...' },
    },
    usage_guidelines: {
        component: 'textarea',
        inputProps: { placeholder: 'Guidelines for using this room...' },
    },
    booking_notes: {
        component: 'textarea',
        inputProps: { placeholder: 'Important notes for booking this room...' },
    },
}));

// Submit handler uses AutoForm's form context
const onSubmit = handleSubmit((values) => {
    const payload = {
        name: values.name,
        code: values.code,
        building_id: Number(values.building_id),
        floor: values.floor,
        type: values.type,
        capacity: Number(values.capacity),
        status: values.status,
        is_bookable: Boolean(values.is_bookable),
        requires_approval: Boolean(values.requires_approval),
        available_from: toHMS(values.available_from),
        available_until: toHMS(values.available_until),
        // blocked_days: Array.isArray(values.blocked_days) ? values.blocked_days : [],
        description: values.description || null,
        usage_guidelines: values.usage_guidelines || null,
        booking_notes: values.booking_notes || null,
    };

    router.put(systemRoutes.rooms.update(props.room.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Room updated successfully!');
            router.visit(systemRoutes.rooms.index());
        },
        onError: (errors) => {
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = (errors as Record<string, string | string[]>)[firstErrorKey];
            toast.error(Array.isArray(firstError) ? firstError[0] : firstError || 'Failed to update room');
        },
    });
});

// Navigation
const goBack = () => router.visit(systemRoutes.rooms.index());
const viewRoom = () => router.visit(systemRoutes.rooms.show(props.room.id));

const isFormValid = computed(() => meta.value.valid);
const hasChanges = computed(() => meta.value.dirty);
</script>

<template>
    <Head :title="`Edit Room - ${room.name}`" />

    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div class="flex-1">
            <h1 class="text-2xl font-semibold">Edit Room</h1>
            <p class="text-muted-foreground">Modify room details for {{ room.name }}</p>
        </div>
        <Button variant="outline" @click="viewRoom"> View Room </Button>
    </div>

    <!-- AutoForm Card -->
    <Card class="mt-6">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Building2 class="h-5 w-5" />
                Room Information
            </CardTitle>
            <CardDescription> Update the details for this room. </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <AutoForm class="w-full space-y-6" :schema="roomFormSchema" :field-config="fieldConfig" :form="{ handleSubmit, meta, isSubmitting } as any" @submit="onSubmit">
                <div class="flex items-center justify-end gap-4">
                    <Button type="button" variant="outline" @click="goBack"> Cancel </Button>
                    <Button type="submit" :disabled="isSubmitting || !isFormValid || !hasChanges" class="min-w-[120px]">
                        <span v-if="isSubmitting">Updating...</span>
                        <span v-else>Update Room</span>
                    </Button>
                </div>
            </AutoForm>
        </CardContent>
    </Card>
</template>
