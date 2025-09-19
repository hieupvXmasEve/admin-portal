<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { WalletAdjustmentRequest } from '@/types/wallet';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, watch } from 'vue';
import { z } from 'zod';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
}

interface Props {
    isOpen: boolean;
    student?: Student | null;
    currentBalance?: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isOpen: false,
    student: null,
    currentBalance: '0.00',
    loading: false,
});

const emit = defineEmits<{
    close: [];
    submit: [data: WalletAdjustmentRequest];
}>();

// Form validation schema
const adjustmentSchema = toTypedSchema(
    z.object({
        amount: z.number({ required_error: 'Amount is required' }).min(0.01, 'Amount must be greater than 0').max(999999.99, 'Amount cannot exceed 999,999.99'),
        notes: z.string({ required_error: 'Notes are required' }).min(1, 'Notes are required').max(1000, 'Notes cannot exceed 1000 characters'),
    }),
);

// Initialize form with vee-validate
const { handleSubmit, resetForm, isSubmitting, values } = useForm({
    validationSchema: adjustmentSchema,
    initialValues: {
        amount: 0,
        notes: '',
    },
});

const newBalance = computed(() => {
    const current = parseFloat(props.currentBalance.replace(/[^0-9.-]/g, '')) || 0;
    return current + (values.amount || 0);
});

const formatAmount = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const submitAdjustment = handleSubmit((values) => {
    emit('submit', {
        amount: values.amount,
        notes: values.notes.trim(),
    });
});

const close = () => {
    if (!props.loading) {
        resetForm();
        emit('close');
    }
};

// Reset form when modal opens
watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);
</script>
<template>
    <Dialog :open="isOpen" @update:open="close">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Adjust Wallet Balance</DialogTitle>
                <DialogDescription> Adjust the Gold balance for {{ student?.full_name || 'this student' }} </DialogDescription>
            </DialogHeader>

            <!-- Student info -->
            <div v-if="student" class="bg-muted rounded-lg p-4">
                <div class="flex items-center space-x-3">
                    <div class="bg-primary/10 flex h-10 w-10 items-center justify-center rounded-full">
                        <span class="text-primary text-sm font-medium">
                            {{ student.full_name.charAt(0) }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-foreground text-sm font-medium">{{ student.full_name }}</p>
                        <p class="text-muted-foreground text-sm">{{ student.student_id }} • {{ student.email }}</p>
                        <p class="text-muted-foreground text-xs">
                            Current Balance: <span class="font-medium">{{ currentBalance }}</span> Gold
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submitAdjustment" class="space-y-4">
                <!-- Amount input -->
                <FormField v-slot="{ componentField }" name="amount">
                    <FormItem>
                        <FormLabel>Adjustment Amount</FormLabel>
                        <FormControl>
                            <div class="relative">
                                <Input v-bind="componentField" type="number" step="0.01" placeholder="Enter amount (positive to add, negative to subtract)" :disabled="loading" />
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-8">
                                    <span class="text-muted-foreground text-sm">Gold</span>
                                </div>
                            </div>
                        </FormControl>
                        <FormMessage />
                        <p class="text-muted-foreground text-xs">Use positive numbers to add Gold, negative numbers to subtract Gold</p>
                    </FormItem>
                </FormField>

                <!-- Notes input -->
                <FormField v-slot="{ componentField }" name="notes">
                    <FormItem>
                        <FormLabel>Notes <span class="text-destructive">*</span></FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" rows="3" placeholder="Explain the reason for this adjustment..." :disabled="loading" />
                        </FormControl>
                        <FormMessage />
                        <p class="text-muted-foreground text-xs">Required: Provide a clear reason for the balance adjustment</p>
                    </FormItem>
                </FormField>

                <!-- Preview -->
                <div v-if="values.amount !== null && values.amount !== 0" class="bg-muted rounded-lg p-3">
                    <p class="text-foreground text-sm">
                        <span class="font-medium">Preview:</span>
                        {{ currentBalance }} →
                        <span :class="newBalance >= 0 ? 'text-green-600' : 'text-red-600'" class="font-medium">
                            {{ formatAmount(newBalance) }}
                        </span>
                        Gold
                    </p>
                    <p v-if="newBalance < 0" class="text-destructive mt-1 text-xs">⚠️ This will result in a negative balance</p>
                </div>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="close" :disabled="loading"> Cancel </Button>
                <Button type="submit" @click="submitAdjustment" :disabled="loading || isSubmitting">
                    <span v-if="loading || isSubmitting" class="flex items-center">
                        <svg class="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Adjusting...
                    </span>
                    <span v-else>Adjust Balance</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
