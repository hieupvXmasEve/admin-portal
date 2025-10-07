import { z } from 'zod';

// Scholarship Definition Interface
export interface ScholarshipDefinition {
    id: number;
    code: string;
    name: string;
    description: string | null;
    type: 'percentage' | 'fixed_amount';
    amount: number;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
    student_count?: number;
    created_at?: string;
    updated_at?: string;
}

// Student Scholarship Award Interface
export interface StudentScholarshipAward {
    id: number;
    student_id: number;
    scholarship_code: string;
    awarded_at: string;
    notes: string | null;
    scholarship?: ScholarshipDefinition;
    student?: {
        id: number;
        student_code: string;
        first_name: string;
        last_name: string;
    };
    created_at?: string;
    updated_at?: string;
}

// Zod Validation Schema for Scholarship Form
export const scholarshipFormSchema = z
    .object({
        code: z
            .string()
            .min(1, 'Scholarship code is required')
            .max(50, 'Scholarship code cannot exceed 50 characters')
            .regex(/^[A-Z0-9_-]+$/, 'Code must contain only uppercase letters, numbers, hyphens, and underscores')
            .transform((val) => val.toUpperCase()),
        name: z.string().min(1, 'Scholarship name is required').max(255, 'Scholarship name cannot exceed 255 characters'),
        description: z.string().max(1000, 'Description cannot exceed 1000 characters').optional().nullable(),
        type: z.enum(['percentage', 'fixed_amount'], {
            errorMap: () => ({ message: 'Please select a valid discount type' }),
        }),
        amount: z
            .number({
                required_error: 'Amount is required',
                invalid_type_error: 'Amount must be a number',
            })
            .positive('Amount must be greater than zero')
            .max(999999999.99, 'Amount is too large'),
        valid_from: z.string().min(1, 'Valid from date is required'),
        valid_until: z.string().min(1, 'Valid until date is required'),
        is_active: z.boolean().default(true),
    })
    .refine(
        (data) => {
            const from = new Date(data.valid_from);
            const until = new Date(data.valid_until);
            return from < until;
        },
        {
            message: 'Valid until date must be after valid from date',
            path: ['valid_until'],
        }
    )
    .refine(
        (data) => {
            if (data.type === 'percentage') {
                return data.amount <= 100;
            }
            return true;
        },
        {
            message: 'Percentage discount cannot exceed 100%',
            path: ['amount'],
        }
    );

export type ScholarshipFormData = z.infer<typeof scholarshipFormSchema>;

// Validation Rules Constants
export const ScholarshipValidationRules = {
    code: {
        minLength: 1,
        maxLength: 50,
        pattern: /^[A-Z0-9_-]+$/,
    },
    name: {
        minLength: 1,
        maxLength: 255,
    },
    description: {
        maxLength: 1000,
    },
    amount: {
        min: 0.01,
        max: 999999999.99,
    },
    percentage: {
        min: 0.01,
        max: 100,
    },
} as const;

// Validation Messages
export const ScholarshipValidationMessages = {
    code: {
        required: 'Scholarship code is required',
        unique: 'This scholarship code has already been taken',
        pattern: 'Code must contain only uppercase letters, numbers, hyphens, and underscores',
        maxLength: 'Scholarship code cannot exceed 50 characters',
    },
    name: {
        required: 'Scholarship name is required',
        maxLength: 'Scholarship name cannot exceed 255 characters',
    },
    description: {
        maxLength: 'Description cannot exceed 1000 characters',
    },
    type: {
        required: 'Scholarship type is required',
        invalid: 'Scholarship type must be either percentage or fixed_amount',
    },
    amount: {
        required: 'Scholarship amount is required',
        positive: 'Scholarship amount must be greater than zero',
        max: 'Scholarship amount is too large',
        percentageMax: 'Percentage discount cannot exceed 100%',
    },
    valid_from: {
        required: 'Valid from date is required',
        invalid: 'Valid from must be a valid date',
    },
    valid_until: {
        required: 'Valid until date is required',
        invalid: 'Valid until must be a valid date',
        after: 'Valid until date must be after valid from date',
    },
} as const;

// Helper Functions
export const formatScholarshipAmount = (amount: number, type: 'percentage' | 'fixed_amount'): string => {
    if (type === 'percentage') {
        return `${amount}%`;
    }
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

export const getScholarshipStatus = (scholarship: ScholarshipDefinition): 'active' | 'inactive' | 'expired' | 'upcoming' => {
    const now = new Date();
    const validFrom = new Date(scholarship.valid_from);
    const validUntil = new Date(scholarship.valid_until);

    if (!scholarship.is_active) return 'inactive';
    if (now > validUntil) return 'expired';
    if (now >= validFrom && now <= validUntil) return 'active';
    return 'upcoming';
};

export const isScholarshipValid = (scholarship: ScholarshipDefinition, date?: Date): boolean => {
    const checkDate = date || new Date();
    const validFrom = new Date(scholarship.valid_from);
    const validUntil = new Date(scholarship.valid_until);

    return scholarship.is_active && checkDate >= validFrom && checkDate <= validUntil;
};
