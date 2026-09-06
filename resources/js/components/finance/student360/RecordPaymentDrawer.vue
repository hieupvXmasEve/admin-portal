<script setup lang="ts">
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useApi } from '@/composables/useApiRequest';
import { PAYMENT_METHOD_LABELS, type PaymentMethod } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface PreviewCandidate {
    invoice_line_id: number;
    charge_id: number | null;
    label: string;
    outstanding: number;
    would_apply: number;
}

interface PreviewPayload {
    candidates: PreviewCandidate[];
    leftover: number;
    settlement_position?: { valid?: boolean };
}

const props = defineProps<{ open: boolean; studentId: number }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const sheetContentRef = ref<HTMLElement | null>(null);
const paymentMethods = Object.entries(PAYMENT_METHOD_LABELS) as [PaymentMethod, string][];
const step = ref<'amount' | 'preview'>('amount');
const previewing = ref(false);
const candidates = ref<PreviewCandidate[]>([]);
const leftover = ref(0);
const previewError = ref('');
const api = useApi();

const form = useForm({
    amount: '',
    method: 'cash' as PaymentMethod,
    paid_at: '',
    external_ref: '',
    notes: '',
    idempotency_key: crypto.randomUUID(),
    allocations: [] as { charge_id: number; amount: number }[],
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.reset();
            form.idempotency_key = crypto.randomUUID();
            form.method = 'cash';
            step.value = 'amount';
            candidates.value = [];
            leftover.value = 0;
            previewError.value = '';
        }
    },
);

const formatVnd = (value: number): string =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value);

const editedLeftover = computed(() => {
    const amount = Number(form.amount) || 0;
    const applied = candidates.value.reduce((sum, row) => sum + (Number(row.would_apply) || 0), 0);

    return Math.max(0, amount - applied);
});

const loadPreview = async (): Promise<void> => {
    previewing.value = true;
    previewError.value = '';
    try {
        const response = await api.post<PreviewPayload>(financeRoutes.student360.recordPaymentPreview(props.studentId), {
            amount: Number(form.amount),
        });
        const raw = (response.data as { value?: { success?: boolean; data?: PreviewPayload } })?.value ?? response.data ?? response;
        const payload = (raw && 'candidates' in raw ? raw : (raw as { data?: PreviewPayload })?.data) as PreviewPayload | null;
        if (!payload || payload.settlement_position?.valid === false) {
            previewError.value = 'Không xem trước được phân bổ. Kiểm tra vị thế thanh toán của sinh viên.';

            return;
        }
        candidates.value = payload.candidates ?? [];
        leftover.value = payload.leftover ?? 0;
        step.value = 'preview';
    } finally {
        previewing.value = false;
    }
};

const submit = (): void => {
    form.allocations = candidates.value
        .filter((row) => row.charge_id !== null && Number(row.would_apply) > 0)
        .map((row) => ({ charge_id: row.charge_id as number, amount: Number(row.would_apply) }));

    form.post(financeRoutes.student360.recordPayment(props.studentId), {
        preserveScroll: true,
        onSuccess: (page) => {
            const printUrl = (page.flash as { payment_voucher_print_url?: string } | undefined)?.payment_voucher_print_url;
            if (printUrl) {
                window.open(printUrl, '_blank');
            }
            form.reset();
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="w-full overflow-y-auto p-4 sm:max-w-lg">
            <div ref="sheetContentRef">
                <SheetHeader>
                    <SheetTitle>Ghi nhận thanh toán</SheetTitle>
                </SheetHeader>
                <p class="text-muted-foreground mt-1 text-sm">{{ step === 'amount' ? 'Bước 1/2 — số tiền' : 'Bước 2/2 — phân bổ và phiếu thu' }}</p>

                <form v-if="step === 'amount'" class="mt-4 space-y-3" @submit.prevent="loadPreview">
                    <div class="space-y-1">
                        <Label for="record-amount">Số tiền (VND)</Label>
                        <Input id="record-amount" v-model="form.amount" type="number" min="1" step="1" required />
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
                        <DatePicker id="record-paid-at" v-model="form.paid_at" placeholder="Chọn ngày thu" :portal-to="sheetContentRef ?? undefined" />
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
                    <p v-if="previewError" class="text-sm text-red-600">{{ previewError }}</p>
                    <Button type="submit" class="w-full" :disabled="previewing || !form.amount">
                        {{ previewing ? 'Đang xem trước…' : 'Xem phân bổ' }}
                    </Button>
                </form>

                <div v-else class="mt-4 space-y-3">
                    <p class="text-sm">Sửa số tiền cấn trừ nếu cần. Phần không phân bổ được ghi Còn dư — không tịch thu.</p>
                    <div v-if="candidates.length === 0" class="text-muted-foreground text-sm">Không có khoản còn phải thu. Toàn bộ sẽ vào Còn dư.</div>
                    <div v-for="row in candidates" :key="row.invoice_line_id" class="space-y-1 rounded-md border p-2">
                        <Label>{{ row.label }} · còn {{ formatVnd(row.outstanding) }}</Label>
                        <Input v-model.number="row.would_apply" type="number" min="0" :max="row.outstanding" step="1" />
                    </div>
                    <p class="text-sm">Còn dư dự kiến: {{ formatVnd(editedLeftover || leftover) }}</p>
                    <p v-if="form.errors.allocations" class="text-sm text-red-600">{{ form.errors.allocations }}</p>
                    <p v-if="form.errors.idempotency_key" class="text-sm text-red-600">{{ form.errors.idempotency_key }}</p>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" class="flex-1" @click="step = 'amount'">Quay lại</Button>
                        <Button type="button" class="flex-1" :disabled="form.processing" @click="submit">
                            {{ form.processing ? 'Đang ghi nhận…' : 'Xác nhận & in phiếu thu' }}
                        </Button>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
