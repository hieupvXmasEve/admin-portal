<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { financeRoutes } from '@/utils/routes';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{ open: boolean; paymentId: number | null; type: 'refund' | 'retain_forfeit'; amount: number }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();
const form = useForm({ idempotency_key: crypto.randomUUID(), type: props.type, amount: props.amount, external_reference: '', policy_code: '', reason: '' });

watch(() => [props.open, props.paymentId, props.type, props.amount] as const, ([open]) => {
    if (!open) return;
    form.defaults({ idempotency_key: crypto.randomUUID(), type: props.type, amount: props.amount, external_reference: '', policy_code: '', reason: '' });
    form.reset();
});

const submit = (): void => {
    if (props.paymentId == null) return;
    form.post(financeRoutes.student360.disposeSurplus(props.paymentId), { preserveScroll: true, onSuccess: () => emit('update:open', false) });
};
</script>

<template>
    <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
        <SheetContent side="right" class="w-full overflow-y-auto p-4 sm:max-w-md">
            <SheetHeader><SheetTitle>{{ type === 'refund' ? 'Ghi nhận hoàn tiền' : 'Giữ lại / forfeit theo policy' }}</SheetTitle></SheetHeader>
            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div class="space-y-1"><Label>Số tiền</Label><Input v-model="form.amount" type="number" min="0.01" step="0.01" /><p class="text-destructive text-xs">{{ form.errors.amount }}</p></div>
                <div v-if="type === 'refund'" class="space-y-1"><Label>Bằng chứng / mã giao dịch hoàn tiền</Label><Input v-model="form.external_reference" /><p class="text-destructive text-xs">{{ form.errors.external_reference }}</p></div>
                <template v-else>
                    <div class="space-y-1"><Label>Mã policy đã phê duyệt</Label><Input v-model="form.policy_code" /><p class="text-destructive text-xs">{{ form.errors.policy_code }}</p></div>
                    <div class="space-y-1"><Label>Lý do</Label><Textarea v-model="form.reason" /><p class="text-destructive text-xs">{{ form.errors.reason }}</p></div>
                </template>
                <Button type="submit" :disabled="form.processing">Xác nhận độc lập</Button>
            </form>
        </SheetContent>
    </Sheet>
</template>
