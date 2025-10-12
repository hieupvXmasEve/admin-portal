import { z } from 'zod';

export const billingCycleFormSchema = z.object({
    semester_id: z.number({ required_error: 'Semester is required' }),
    name: z.string().min(1, 'Name is required').max(255, 'Name must not exceed 255 characters'),
    start_date: z.string().min(1, 'Start date is required'),
    end_date: z.string().min(1, 'End date is required'),
    due_date: z.string().min(1, 'Due date is required'),
});

export type BillingCycleFormData = z.infer<typeof billingCycleFormSchema>;

export const billingCycleNameOnlySchema = billingCycleFormSchema.pick({
    name: true,
});

export type BillingCycleNameOnlyFormData = z.infer<typeof billingCycleNameOnlySchema>;
