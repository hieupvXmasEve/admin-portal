<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, MinusCircle, PlusCircle, Settings } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    show: boolean;
    walletId: number;
}

interface Emits {
    (e: 'update:show', value: boolean): void;
    (e: 'success'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const adjustmentType = ref<'credit' | 'debit' | null>(null);

const form = useForm({
    amount: '' as string | number,
    description: '',
});

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' VND';
};

const submit = () => {
    if (!adjustmentType.value || !form.amount) return;

    // Convert to positive/negative based on adjustment type
    const adjustmentAmount = adjustmentType.value === 'credit' ? Number(form.amount) : -Number(form.amount);

    form.transform((data) => ({
        ...data,
        amount: adjustmentAmount,
    })).post(`/wallets/${props.walletId}/adjustment`, {
        onSuccess: () => {
            form.reset();
            adjustmentType.value = null;
            emit('success');
        },
        onError: () => {
            // Errors are handled by the form automatically
        },
    });
};

// Reset form when modal is closed
watch(
    () => props.show,
    (newValue) => {
        if (!newValue) {
            form.reset();
            form.clearErrors();
            adjustmentType.value = null;
        }
    },
);
</script>

<template>
    <Dialog :open="show" @update:open="$emit('update:show', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Settings class="h-5 w-5 text-yellow-600" />
                    Balance Adjustment
                </DialogTitle>
                <DialogDescription> Adjust the student's wallet balance. Use positive values to add funds, negative values to deduct funds. </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Adjustment Type -->
                <div class="space-y-3">
                    <Label>Adjustment Type *</Label>
                    <RadioGroup v-model="adjustmentType" class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem id="credit" value="credit" />
                            <Label for="credit" class="font-normal">Credit (Add funds)</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem id="debit" value="debit" />
                            <Label for="debit" class="font-normal">Debit (Deduct funds)</Label>
                        </div>
                    </RadioGroup>
                </div>

                <!-- Amount Input -->
                <div class="space-y-2">
                    <Label for="amount">Amount (VND) *</Label>
                    <Input id="amount" v-model="form.amount" type="number" min="1" step="1000" placeholder="Enter adjustment amount" :class="{ 'border-red-500': form.errors.amount }" />
                    <div v-if="form.errors.amount" class="text-sm text-red-600">
                        {{ form.errors.amount }}
                    </div>
                </div>

                <!-- Description Input -->
                <div class="space-y-2">
                    <Label for="description">Description *</Label>
                    <Textarea id="description" v-model="form.description" rows="3" placeholder="Reason for this adjustment (required)" :class="{ 'border-red-500': form.errors.description }" />
                    <div v-if="form.errors.description" class="text-sm text-red-600">
                        {{ form.errors.description }}
                    </div>
                </div>

                <!-- Adjustment Preview -->
                <Alert v-if="form.amount && adjustmentType" :class="adjustmentType === 'credit' ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'">
                    <component :is="adjustmentType === 'credit' ? PlusCircle : MinusCircle" :class="adjustmentType === 'credit' ? 'text-green-600' : 'text-red-600'" class="h-4 w-4" />
                    <AlertDescription :class="adjustmentType === 'credit' ? 'text-green-800' : 'text-red-800'">
                        <strong>{{ adjustmentType === 'credit' ? 'Credit' : 'Debit' }} Adjustment:</strong>
                        {{ adjustmentType === 'credit' ? '+' : '-' }}{{ formatCurrency(Number(form.amount)) }}
                    </AlertDescription>
                </Alert>

                <!-- Warning for debit adjustments -->
                <Alert v-if="adjustmentType === 'debit'" class="border-yellow-200 bg-yellow-50">
                    <AlertTriangle class="h-4 w-4 text-yellow-600" />
                    <AlertDescription class="text-yellow-800"> <strong>Warning:</strong> This will deduct funds from the student's wallet. Make sure the adjustment is necessary and properly documented. </AlertDescription>
                </Alert>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="$emit('update:show', false)"> Cancel </Button>
                    <Button type="submit" :disabled="form.processing || !adjustmentType" :class="adjustmentType === 'credit' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'" class="flex items-center gap-2">
                        <span v-if="form.processing" class="flex items-center gap-2">
                            <div class="h-4 w-4 animate-spin rounded-full border-b-2 border-white"></div>
                            Processing...
                        </span>
                        <span v-else>Apply Adjustment</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
