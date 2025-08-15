import { z } from 'zod';

// Room types and statuses (should match backend)
export const ROOM_TYPES = [
    'classroom',
    'laboratory',
    'computer_lab',
    'auditorium',
    'meeting_room',
    'library',
    'study_room',
    'workshop',
    'office',
    'other',
] as const;

export const ROOM_STATUSES = [
    'available',
    'occupied',
    'maintenance',
    'out_of_service',
    'reserved',
] as const;

export const DAYS_OF_WEEK = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
] as const;

// Time format regex (HH:MM)
export const timeRegex = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;

// Base schema for room form with comprehensive defaults
export const roomFormSchema = z.object({
    name: z.string({
        required_error: "Room name is required.",
    })
        .min(2, 'Room name must be at least 2 characters')
        .max(255, 'Room name must not exceed 255 characters')
        .describe('Room Name'),

    code: z.string({
        required_error: "Room code is required.",
    })
        .min(1, 'Room code is required')
        .max(50, 'Room code must not exceed 50 characters')
        .regex(/^[A-Za-z0-9\-_]+$/, 'Room code can only contain letters, numbers, hyphens, and underscores')
        .describe('Room Code'),

    building_id: z.coerce.number({
        required_error: "Building is required.",
        invalid_type_error: "Building must be selected.",
    })
        .int('Building must be selected')
        .min(1, 'Building is required')
        .default(1)
        .describe('Building'),

    floor: z.string({
        required_error: "Floor is required.",
    })
        .min(1, 'Floor is required')
        .max(50, 'Floor must not exceed 50 characters')
        .default('Ground')
        .describe('Floor'),

    type: z.enum(ROOM_TYPES, {
        required_error: 'Room type is required',
        invalid_type_error: 'Please select a valid room type',
    })
        .default('classroom')
        .describe('Room Type'),

    capacity: z.coerce.number({
        required_error: "Capacity is required.",
        invalid_type_error: "Capacity must be a number.",
    })
        .int('Capacity must be a whole number')
        .min(1, 'Capacity must be at least 1')
        .max(10000, 'Capacity must not exceed 10,000')
        .default(30)
        .describe('Capacity'),

    status: z.enum(ROOM_STATUSES, {
        required_error: 'Room status is required',
        invalid_type_error: 'Please select a valid room status',
    })
        .default('available')
        .describe('Status'),

    is_bookable: z.boolean()
        .default(true)
        .describe('Is Bookable'),

    requires_approval: z.boolean()
        .default(false)
        .describe('Requires Approval'),

    available_from: z.string()
        .optional()
        .refine((val) => !val || timeRegex.test(val), {
            message: 'Available from time must be in HH:MM format',
        })
        .default('07:00')
        .describe('Available From'),

    available_until: z.string()
        .optional()
        .refine((val) => !val || timeRegex.test(val), {
            message: 'Available until time must be in HH:MM format',
        })
        .default('18:00')
        .describe('Available Until'),

    // blocked_days: z.array(z.enum(DAYS_OF_WEEK))
    //     .optional()
    //     .default([])
    //     .describe('Blocked Days'),

    description: z.string()
        .max(1000, 'Description must not exceed 1000 characters')
        .optional()
        .describe('Description'),

    usage_guidelines: z.string()
        .max(2000, 'Usage guidelines must not exceed 2000 characters')
        .optional()
        .describe('Usage Guidelines'),

    booking_notes: z.string()
        .max(1000, 'Booking notes must not exceed 1000 characters')
        .optional()
        .describe('Booking Notes'),
}).refine((data) => {
    // Validate that available_until is after available_from if both are provided
    if (data.available_from && data.available_until) {
        const fromTime = data.available_from.split(':').map(Number);
        const untilTime = data.available_until.split(':').map(Number);

        const fromMinutes = fromTime[0] * 60 + fromTime[1];
        const untilMinutes = untilTime[0] * 60 + untilTime[1];

        return untilMinutes > fromMinutes;
    }
    return true;
}, {
    message: 'Available until time must be after available from time',
    path: ['available_until'],
});

export type RoomFormData = z.infer<typeof roomFormSchema>;

// Room filters schema for the index page
export const roomFiltersSchema = z.object({
    search: z.string().optional(),
    type: z.enum(ROOM_TYPES).optional(),
    status: z.enum(ROOM_STATUSES).optional(),
    building: z.string().optional(),
    floor: z.string().optional(),
    is_bookable: z.coerce.boolean().optional(),
    requires_approval: z.coerce.boolean().optional(),
    min_capacity: z.coerce.number().int().min(1).optional(),
    max_capacity: z.coerce.number().int().min(1).optional(),
    sort: z.string().optional().default('name'),
    direction: z.enum(['asc', 'desc']).optional().default('asc'),
    per_page: z.coerce.number().int().min(10).max(100).optional().default(15),
});

export type RoomFilters = z.infer<typeof roomFiltersSchema>;

// Validation for form data before submission (converts types)
export const processRoomFormData = (data: any): RoomFormData => {
    // Ensure blocked_days is an array
    if (typeof data.blocked_days === 'string') {
        try {
            data.blocked_days = JSON.parse(data.blocked_days);
        } catch {
            data.blocked_days = [];
        }
    }

    // Convert boolean strings to actual booleans
    if (typeof data.is_bookable === 'string') {
        data.is_bookable = data.is_bookable === 'true' || data.is_bookable === '1';
    }

    if (typeof data.requires_approval === 'string') {
        data.requires_approval = data.requires_approval === 'true' || data.requires_approval === '1';
    }

    // Convert capacity to number
    if (typeof data.capacity === 'string') {
        data.capacity = parseInt(data.capacity, 10);
    }

    return roomFormSchema.parse(data);
};

// Helper functions for getting options
export const getRoomTypeOptions = () => {
    return ROOM_TYPES.map(type => ({
        value: type,
        label: type.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' '),
    }));
};

export const getRoomStatusOptions = () => {
    const statusLabels: Record<typeof ROOM_STATUSES[number], string> = {
        available: 'Available',
        occupied: 'Occupied',
        maintenance: 'Under Maintenance',
        out_of_service: 'Out of Service',
        reserved: 'Reserved',
    };

    return ROOM_STATUSES.map(status => ({
        value: status,
        label: statusLabels[status],
    }));
};

export const getDaysOfWeekOptions = () => {
    return DAYS_OF_WEEK.map(day => ({
        value: day,
        label: day,
    }));
};
