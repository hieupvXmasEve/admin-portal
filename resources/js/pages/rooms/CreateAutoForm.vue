<script setup lang="ts">
import { AutoForm } from '@/components/ui/auto-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ROOM_STATUSES, ROOM_TYPES, timeRegex } from '@/schemas/room';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Building2 } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

// Props from backend (buildings data)
const props = defineProps<{
    buildings: Array<{
        id: number;
        label: string;
        name: string;
        code: string;
    }>;
    room_types: Array<{
        value: string;
        label: string;
    }>;
    room_statuses: Array<{
        value: string;
        label: string;
    }>;
    days_of_week: Array<{
        value: string;
        label: string;
    }>;
}>();

// Defaults based on incoming props (first element if present)
const defaultBuildingId = props.buildings && props.buildings.length > 0 ? props.buildings[0].id : 1;
const defaultRoomType = props.room_types && props.room_types.length > 0 ? (props.room_types[0].value as (typeof ROOM_TYPES)[number]) : ('classroom' as (typeof ROOM_TYPES)[number]);
const defaultRoomStatus = props.room_statuses && props.room_statuses.length > 0 ? (props.room_statuses[0].value as (typeof ROOM_STATUSES)[number]) : ('available' as (typeof ROOM_STATUSES)[number]);

const roomFormSchema = z
    .object({
        name: z
            .string({
                required_error: 'Room name is required.',
            })
            .min(2, 'Room name must be at least 2 characters')
            .max(255, 'Room name must not exceed 255 characters')
            .describe('Room Name'),

        code: z
            .string({
                required_error: 'Room code is required.',
            })
            .min(1, 'Room code is required')
            .max(50, 'Room code must not exceed 50 characters')
            .regex(/^[A-Za-z0-9\-_]+$/, 'Room code can only contain letters, numbers, hyphens, and underscores')
            .describe('Room Code'),

        building_id: z.coerce
            .number({
                required_error: 'Building is required.',
                invalid_type_error: 'Building must be selected.',
            })
            .int('Building must be selected')
            .min(1, 'Building is required')
            .default(defaultBuildingId)
            .describe('Building'),

        floor: z
            .string({
                required_error: 'Floor is required.',
            })
            .min(1, 'Floor is required')
            .max(50, 'Floor must not exceed 50 characters')
            .default('Ground')
            .describe('Floor'),

        type: z
            .enum(ROOM_TYPES, {
                required_error: 'Room type is required',
                invalid_type_error: 'Please select a valid room type',
            })
            .default(defaultRoomType)
            .describe('Room Type'),

        capacity: z.coerce
            .number({
                required_error: 'Capacity is required.',
                invalid_type_error: 'Capacity must be a number.',
            })
            .int('Capacity must be a whole number')
            .min(1, 'Capacity must be at least 1')
            .max(10000, 'Capacity must not exceed 10,000')
            .default(30)
            .describe('Capacity'),

        status: z
            .enum(ROOM_STATUSES, {
                required_error: 'Room status is required',
                invalid_type_error: 'Please select a valid room status',
            })
            .default(defaultRoomStatus)
            .describe('Status'),

        is_bookable: z.boolean().default(true).describe('Is Bookable'),

        requires_approval: z.boolean().default(false).describe('Requires Approval'),

        available_from: z
            .string()
            .optional()
            .refine((val) => !val || timeRegex.test(val), {
                message: 'Available from time must be in HH:MM format',
            })
            .default('07:00')
            .describe('Available From'),

        available_until: z
            .string()
            .optional()
            .refine((val) => !val || timeRegex.test(val), {
                message: 'Available until time must be in HH:MM format',
            })
            .default('18:00')
            .describe('Available Until'),

        description: z.string().max(1000, 'Description must not exceed 1000 characters').optional().describe('Description'),

        usage_guidelines: z.string().max(2000, 'Usage guidelines must not exceed 2000 characters').optional().describe('Usage Guidelines'),

        booking_notes: z.string().max(1000, 'Booking notes must not exceed 1000 characters').optional().describe('Booking Notes'),
    })
    .refine(
        (data) => {
            // Validate that available_until is after available_from if both are provided
            if (data.available_from && data.available_until) {
                const fromTime = data.available_from.split(':').map(Number);
                const untilTime = data.available_until.split(':').map(Number);

                const fromMinutes = fromTime[0] * 60 + fromTime[1];
                const untilMinutes = untilTime[0] * 60 + untilTime[1];

                return untilMinutes > fromMinutes;
            }
            return true;
        },
        {
            message: 'Available until time must be after available from time',
            path: ['available_until'],
        },
    );
// Field configuration for AutoForm - simplified and focused
const fieldConfig = computed(() => ({
    name: {
        description: 'Enter the name of the room (e.g., Lecture Theatre 1, Lab A)',
        inputProps: {
            placeholder: 'e.g., Lecture Theatre 1, Lab A',
        },
    },
    code: {
        description: 'Unique identifier for the room',
        inputProps: {
            placeholder: 'e.g., LT1, LAB-A, CR101',
        },
    },
    building_id: {
        label: 'Building',
        description: 'Select the building where this room is located',
        component: 'select',
        inputProps: {
            placeholder: 'Select a building',
            // Provide labeled options to AutoForm select
            options: props.buildings.map((b) => ({ value: String(b.id), label: b.label })),
        },
    },
    floor: {
        description: 'Floor number or name where the room is located',
        inputProps: {
            placeholder: 'e.g., Ground, 1st, 2nd',
        },
    },
    type: {
        label: 'Room Type',
        description: 'Select the type of room',
        component: 'select',
        inputProps: {
            placeholder: 'Select room type',
            options: props.room_types, // labeled {value,label}
        },
    },
    capacity: {
        description: 'Maximum number of people the room can accommodate (default: 30)',
        inputProps: {
            type: 'number',
            min: '1',
            max: '10000',
        },
    },
    status: {
        label: 'Room Status',
        description: 'Current status of the room',
        component: 'select',
        inputProps: {
            placeholder: 'Select status',
            options: props.room_statuses, // labeled {value,label}
        },
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
        inputProps: {
            type: 'time',
        },
    },
    available_until: {
        label: 'Available Until',
        description: 'End time for daily availability',
        inputProps: {
            type: 'time',
        },
    },
    description: {
        component: 'textarea',
        inputProps: {
            placeholder: 'Brief description of the room...',
        },
    },
    usage_guidelines: {
        component: 'textarea',
        inputProps: {
            placeholder: 'Guidelines for using this room...',
        },
    },
    booking_notes: {
        component: 'textarea',
        inputProps: {
            placeholder: 'Important notes for booking this room...',
        },
    },
}));

// Form submission - simplified approach following your example pattern
function onSubmit(values: Record<string, any>) {
    // Show submitted values like in your example (for debugging)
    toast({
        title: 'Submitting room data:',
        description: h('pre', { class: 'mt-2 w-[340px] rounded-md bg-slate-950 p-4' }, h('code', { class: 'text-white' }, JSON.stringify(values, null, 2))),
    });
    console.log('%c value', 'color: red', values);
    return;
    // Process and submit the form data
    const formData = {
        name: values.name,
        code: values.code,
        building_id: Number(values.building_id),
        floor: values.floor,
        type: values.type,
        capacity: Number(values.capacity),
        status: values.status,
        is_bookable: Boolean(values.is_bookable),
        requires_approval: Boolean(values.requires_approval),
        available_from: values.available_from,
        available_until: values.available_until,
        description: values.description || null,
        usage_guidelines: values.usage_guidelines || null,
        booking_notes: values.booking_notes || null,
    };

    router.post(systemRoutes.rooms.store(), formData, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Room created successfully!');
            router.visit(systemRoutes.rooms.index());
        },
        onError: (errors) => {
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];
            toast.error(Array.isArray(firstError) ? firstError[0] : firstError || 'Failed to create room');
        },
    });
}

// Navigation
const goBack = () => {
    router.visit(systemRoutes.rooms.index());
};
</script>

<template>
    <Head title="Create Room" />

    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h1 class="text-2xl font-semibold">Create New Room</h1>
            <p class="text-muted-foreground">Add a new room to the system</p>
        </div>
    </div>

    <!-- AutoForm Card -->
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Building2 class="h-5 w-5" />
                Room Information
            </CardTitle>
            <CardDescription> Fill in the details for the new room. All required fields must be completed. </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <AutoForm class="w-full space-y-6" :schema="roomFormSchema" :field-config="fieldConfig" @submit="onSubmit">
                <!-- Submit button -->
                <Button type="submit" class="w-full"> Create Room </Button>
            </AutoForm>
        </CardContent>
    </Card>
</template>
