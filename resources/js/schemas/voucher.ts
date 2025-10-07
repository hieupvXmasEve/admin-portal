import { z } from 'zod';

export const VOUCHER_TYPES = ['informational', 'discount'] as const;
export const DISCOUNT_TYPES = ['percentage', 'fixed_amount'] as const;

// Zod schema matching Laravel validation rules in VoucherController.php
export const voucherFormSchema = z
    .object({
        code: z
            .string({ required_error: 'Voucher code is required.' })
            .min(1, 'Voucher code is required.')
            .max(50, 'Voucher code cannot exceed 50 characters.')
            .regex(
                /^[A-Z0-9_-]+$/,
                'Voucher code must contain only uppercase letters, numbers, hyphens, and underscores.',
            )
            .describe('Voucher Code'),

        name: z
            .string({ required_error: 'Voucher name is required.' })
            .min(1, 'Voucher name is required.')
            .max(255, 'Voucher name cannot exceed 255 characters.')
            .describe('Voucher Name'),

        description: z
            .string()
            .max(1000, 'Description cannot exceed 1000 characters.')
            .optional()
            .or(z.literal(''))
            .describe('Description'),

        voucher_type: z
            .enum(VOUCHER_TYPES, {
                required_error: 'Voucher type is required.',
                invalid_type_error: 'Voucher type must be either informational or discount.',
            })
            .describe('Voucher Type'),

        discount_type: z
            .enum(DISCOUNT_TYPES, {
                invalid_type_error: 'Discount type must be either percentage or fixed_amount.',
            })
            .optional()
            .nullable()
            .describe('Discount Type'),

        discount_value: z
            .number({
                invalid_type_error: 'Discount value must be a number.',
            })
            .min(0, 'Discount value must be greater than or equal to zero.')
            .max(999999999.99, 'Discount value is too large.')
            .optional()
            .nullable()
            .describe('Discount Value'),

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
    )
    .refine(
        (data) => {
            // If voucher type is discount, discount_type and discount_value are required
            if (data.voucher_type === 'discount') {
                return data.discount_type !== null && data.discount_type !== undefined;
            }
            return true;
        },
        {
            message: 'Discount type is required for discount vouchers.',
            path: ['discount_type'],
        },
    )
    .refine(
        (data) => {
            // If voucher type is discount, discount_value must be greater than 0
            if (data.voucher_type === 'discount') {
                return (
                    data.discount_value !== null &&
                    data.discount_value !== undefined &&
                    data.discount_value > 0
                );
            }
            return true;
        },
        {
            message: 'Discount value must be greater than zero for discount vouchers.',
            path: ['discount_value'],
        },
    )
    .refine(
        (data) => {
            // If discount type is percentage, value cannot exceed 100
            if (data.voucher_type === 'discount' && data.discount_type === 'percentage') {
                return data.discount_value !== null && data.discount_value <= 100;
            }
            return true;
        },
        {
            message: 'Percentage discount cannot exceed 100%.',
            path: ['discount_value'],
        },
    );

export type VoucherFormData = z.infer<typeof voucherFormSchema>;

// Helper function to get voucher type options
export const getVoucherTypeOptions = () => {
    return [
        { value: 'informational', label: 'Informational' },
        { value: 'discount', label: 'Discount' },
    ];
};

// Helper function to get discount type options
export const getDiscountTypeOptions = () => {
    return [
        { value: 'fixed_amount', label: 'Fixed Amount' },
        { value: 'percentage', label: 'Percentage' },
    ];
};
