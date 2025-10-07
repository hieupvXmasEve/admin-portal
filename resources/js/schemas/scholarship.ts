import { z } from 'zod';

export const SCHOLARSHIP_TYPES = ['percentage', 'fixed_amount'] as const;

// Zod schema matching Laravel validation rules in ScholarshipRequest.php
export const scholarshipFormSchema = z
    .object({
        code: z
            .string({ required_error: 'Scholarship code is required.' })
            .min(1, 'Scholarship code is required.')
            .max(50, 'Scholarship code cannot exceed 50 characters.')
            .regex(
                /^[A-Z0-9_-]+$/,
                'Scholarship code must contain only uppercase letters, numbers, hyphens, and underscores.',
            )
            .describe('Scholarship Code'),

        name: z
            .string({ required_error: 'Scholarship name is required.' })
            .min(1, 'Scholarship name is required.')
            .max(255, 'Scholarship name cannot exceed 255 characters.')
            .describe('Scholarship Name'),

        description: z
            .string()
            .max(1000, 'Description cannot exceed 1000 characters.')
            .optional()
            .or(z.literal(''))
            .describe('Description'),

        type: z
            .enum(SCHOLARSHIP_TYPES, {
                required_error: 'Scholarship type is required.',
                invalid_type_error: 'Scholarship type must be either percentage or fixed_amount.',
            })
            .describe('Discount Type'),

        amount: z
            .number({
                required_error: 'Scholarship amount is required.',
                invalid_type_error: 'Scholarship amount must be a number.',
            })
            .min(0.01, 'Scholarship amount must be greater than zero.')
            .max(999999999.99, 'Scholarship amount is too large.')
            .describe('Discount Amount'),

        valid_from: z
            .string({ required_error: 'Valid from date is required.' })
            .min(1, 'Valid from date is required.')
            .refine((date) => !isNaN(Date.parse(date)), {
                message: 'Valid from must be a valid date.',
            })
            .describe('Valid From'),

        valid_until: z
            .string({ required_error: 'Valid until date is required.' })
            .min(1, 'Valid until date is required.')
            .refine((date) => !isNaN(Date.parse(date)), {
                message: 'Valid until must be a valid date.',
            })
            .describe('Valid Until'),

        is_active: z.boolean().default(true).describe('Active Status'),
    })
    .refine(
        (data) => {
            // Validate that valid_until is after valid_from
            if (data.valid_from && data.valid_until) {
                const fromDate = new Date(data.valid_from);
                const untilDate = new Date(data.valid_until);
                return untilDate > fromDate;
            }
            return true;
        },
        {
            message: 'Valid until date must be after valid from date.',
            path: ['valid_until'],
        },
    );

export type ScholarshipFormData = z.infer<typeof scholarshipFormSchema>;

// Helper function to get scholarship type options
export const getScholarshipTypeOptions = () => {
    return [
        { value: 'fixed_amount', label: 'Fixed Amount' },
        { value: 'percentage', label: 'Percentage' },
    ];
};

// Student Scholarship Assignment form schema
export const studentScholarshipAssignmentSchema = z.object({
    student_identifier: z
        .string({ required_error: 'Student email or ID is required.' })
        .min(1, 'Student email or ID is required.')
        .max(255, 'Student identifier cannot exceed 255 characters.')
        .describe('Student Email or ID'),

    scholarship_code: z
        .string({ required_error: 'Scholarship is required.' })
        .min(1, 'Scholarship is required.')
        .max(50, 'Scholarship code cannot exceed 50 characters.')
        .describe('Scholarship'),

    awarded_at: z
        .string()
        .optional()
        .refine(
            (date) => {
                if (!date) return true; // Optional field
                return !isNaN(Date.parse(date));
            },
            {
                message: 'Awarded date must be a valid date.',
            }
        )
        .describe('Awarded Date'),

    notes: z
        .string()
        .max(1000, 'Notes cannot exceed 1000 characters.')
        .optional()
        .or(z.literal(''))
        .describe('Notes'),
});

export type StudentScholarshipAssignmentData = z.infer<typeof studentScholarshipAssignmentSchema>;
