<script setup lang="ts">
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { PAYMENT_METHOD_LABELS, type PaymentMethod } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ open: boolean; studentId: number }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const sheetContentRef = ref<HTMLElement | null>(null);
const paymentMethods = Object.entries(PAYMENT_METHOD_LABELS) as [PaymentMethod, string][];

const form = useForm({
    amount: '',
    method: 'cash' as PaymentMethod,
    paid_at: '',
    external_ref: '',
    notes: '',
});

const submit = (): void => {
    form.post(financeRoutes.student360.recordPayment(props.studentId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="w-full overflow-y-auto p-4 sm:max-w-md">
            <div ref="sheetContentRef">
            <SheetHeader>
                <SheetTitle>Ghi nhận thanh toán</SheetTitle>
            </SheetHeader>
            <form class="mt-4 space-y-3" @submit.prevent="submit">
                <div class="space-y-1">
                    <Label for="record-amount">Số tiền (VND)</Label>
                    <Input id="record-amount" v-model="form.amount" type="number" min="1" step="1" />
                    <p v-if="form.errors.amount" class="text-sm text-red-600">{{ form.errors.amount }}</p>
                </div>
                <div class="space-y-1">
                    <Label for="record-method">Phương thức</Label>
                    <Select v-model="form.method">
                        <SelectTrigger id="record-method">
                            <SelectValue placeholder="Chọn phương thức" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="[value, label] in paymentMethods" :key="value" :value="value">
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.method" class="text-sm text-red-600">{{ form.errors.method }}</p>
                </div>
                <div class="space-y-1">
                    <Label for="record-paid-at">Ngày thu</Label>
                    <DatePicker
                        id="record-paid-at"
                        v-model="form.paid_at"
                        placeholder="Chọn ngày thu"
                        :portal-to="sheetContentRef ?? undefined"
                    />
                    <p v-if="form.errors.paid_at" class="text-sm text-red-600">{{ form.errors.paid_at }}</p>
                </div>
                <div class="space-y-1">
                    <Label for="record-ref">Mã biên nhận</Label>
                    <Input id="record-ref" v-model="form.external_ref" />
                    <p v-if="form.errors.external_ref" class="text-sm text-red-600">{{ form.errors.external_ref }}</p>
                </div>
                <div class="space-y-1">
                    <Label for="record-notes">Ghi chú</Label>
                    <Input id="record-notes" v-model="form.notes" />
                    <p v-if="form.errors.notes" class="text-sm text-red-600">{{ form.errors.notes }}</p>
                </div>
                <Button type="submit" class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Đang ghi nhận…' : 'Ghi nhận' }}
                </Button>
            </form>
            </div>
        </SheetContent>
    </Sheet>
</template>