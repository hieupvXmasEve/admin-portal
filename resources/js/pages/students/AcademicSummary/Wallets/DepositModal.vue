<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { Info, Plus } from 'lucide-vue-next';
import { watch } from 'vue';

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

const form = useForm({
    amount: '' as string | number,
    description: '',
});

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' VND';
};

const submit = () => {
    form.post(`/wallets/${props.walletId}/deposit`, {
        onSuccess: () => {
            form.reset();
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
        }
    },
);
</script>

<template>
    <Dialog :open="show" @update:open="$emit('update:show', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="h-5 w-5 text-green-600" />
                    Deposit to Wallet
                </DialogTitle>
                <DialogDescription> Add funds to the student's cash wallet. </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Amount Input -->
                <div class="space-y-2">
                    <Label for="amount">Amount (VND) *</Label>
                    <Input id="amount" v-model="form.amount" type="number" min="1000" max="100000000" step="1000" placeholder="Enter amount (minimum 1,000 VND)" :class="{ 'border-red-500': form.errors.amount }" />
                    <div v-if="form.errors.amount" class="text-sm text-red-600">
                        {{ form.errors.amount }}
                    </div>
                </div>

                <!-- Description Input -->
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="form.description" rows="3" placeholder="Optional description for this deposit" :class="{ 'border-red-500': form.errors.description }" />
                    <div v-if="form.errors.description" class="text-sm text-red-600">
                        {{ form.errors.description }}
                    </div>
                </div>

                <!-- Amount Preview -->
                <Alert v-if="form.amount && Number(form.amount) >= 1000" class="border-blue-200 bg-blue-50">
                    <Info class="h-4 w-4 text-blue-600" />
                    <AlertDescription class="text-blue-800"> <strong>Deposit Amount:</strong> {{ formatCurrency(Number(form.amount)) }} </AlertDescription>
                </Alert>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="$emit('update:show', false)"> Cancel </Button>
                    <Button type="submit" :disabled="form.processing" class="flex items-center gap-2">
                        <span v-if="form.processing" class="flex items-center gap-2">
                            <div class="h-4 w-4 animate-spin rounded-full border-b-2 border-white"></div>
                            Processing...
                        </span>
                        <span v-else>Deposit</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
