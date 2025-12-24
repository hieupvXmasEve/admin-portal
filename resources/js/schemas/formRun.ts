import { z } from 'zod';

export const SCOPE_TYPES = ['course', 'semester', 'department', 'global'] as const;

export const formRunSchema = z
    .object({
        form_id: z
            .string({
                required_error: 'Form template is required.',
            })
            .min(1, 'Form template is required.'),

        scope_type: z
            .enum(SCOPE_TYPES, {
                required_error: 'Context type is required.',
            })
            .default('global'),

        scope_id: z.string().optional(),

        semester_id: z.string().optional(),

        start_at: z
            .string({
                required_error: 'Start time is required.',
            })
            .min(1, 'Start time is required.'),

        end_at: z.string().optional(),

        is_mandatory: z.boolean().default(false),

        submission_limit_per_user: z.coerce.number().int().min(1).default(1),
    })
    .refine(
        (data) => {
            if (data.scope_type === 'semester' && (!data.semester_id || data.semester_id === 'none')) {
                return false;
            }
            return true;
        },
        {
            message: 'Semester is required when context type is Semester.',
            path: ['semester_id'],
        },
    )
    .refine(
        (data) => {
            if (data.scope_type === 'department' && !data.scope_id) {
                return false;
            }
            return true;
        },
        {
            message: 'Department is required when context type is Department.',
            path: ['scope_id'],
        },
    )
    .refine(
        (data) => {
            if (data.scope_type === 'course' && !data.scope_id) {
                return false;
            }
            return true;
        },
        {
            message: 'Course Offering ID is required when context type is Course Offering.',
            path: ['scope_id'],
        },
    )
    .refine(
        (data) => {
            if (data.start_at && data.end_at) {
                return new Date(data.end_at) > new Date(data.start_at);
            }
            return true;
        },
        {
            message: 'End time must be after start time.',
            path: ['end_at'],
        },
    );

export type FormRunFormData = z.infer<typeof formRunSchema>;
