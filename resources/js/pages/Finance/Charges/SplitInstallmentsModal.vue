<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/types/finance';
import { router } from '@inertiajs/vue3';
import { Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface InstallmentRow {
    installment_no: number;
    amount: number;
    due_date: string;
}

interface Props {
    open: boolean;
    chargeId: number;
    netSplitTarget: number;
    currentPlanCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    currentPlanCount: 0,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const isSubmitting = ref(false);

// Default Vietnamese date format YYYY-MM-DD for <input type=date>.
const today = new Date();
const inDays = (n: number): string => {
    const d = new Date(today);
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
};

const buildDefaultPlan = (): InstallmentRow[] => {
    const half = Math.floor(props.netSplitTarget / 2);
    const remainder = props.netSplitTarget - half;
    return [
        { installment_no: 1, amount: half, due_date: inDays(30) },
        { installment_no: 2, amount: remainder, due_date: inDays(60) },
    ];
};

const rows = ref<InstallmentRow[]>(buildDefaultPlan());

watch(
    () => props.open,
    (next) => {
        if (next) {
            // Reset to default 2x50% each time the modal opens.
            rows.value = buildDefaultPlan();
        }
    },
);

// Sum of current amounts as integer cents to avoid float drift in display.
const currentSum = computed(() =>
    rows.value.reduce((acc, r) => acc + (Number(r.amount) || 0), 0),
);

const remainder = computed(() => props.netSplitTarget - currentSum.value);

const sumMatchesTarget = computed(() => {
    const targetCents = Math.round(props.netSplitTarget * 100);
    const sumCents = Math.round(currentSum.value * 100);
    return sumCents === targetCents;
});

const validationErrors = computed<string[]>(() => {
    const errs: string[] = [];

    if (rows.value.length < 1) {
        errs.push('Phải có ít nhất 1 đợt.');
    }
    if (rows.value.length > 24) {
        errs.push('Tối đa 24 đợt.');
    }
    for (const [i, row] of rows.value.entries()) {
        if (!row.amount || row.amount <= 0) {
            errs.push(`Đợt ${i + 1}: số tiền phải lớn hơn 0.`);
        }
        if (!row.due_date) {
            errs.push(`Đợt ${i + 1}: chưa có hạn thanh toán.`);
        }
    }
    // Check ascending due_date.
    for (let i = 1; i < rows.value.length; i++) {
        if (rows.value[i].due_date < rows.value[i - 1].due_date) {
            errs.push(`Đợt ${i + 1}: hạn thanh toán phải sau đợt trước.`);
            break;
        }
    }
    if (!sumMatchesTarget.value) {
        const diff = formatCurrency(Math.abs(remainder.value));
        const sign = remainder.value > 0 ? 'thiếu' : 'thừa';
        errs.push(`Tổng đợt ${sign} ${diff} so với số cần thu (${formatCurrency(props.netSplitTarget)}).`);
    }
    return errs;
});

const canSubmit = computed(() => validationErrors.value.length === 0 && !isSubmitting.value);

const addRow = () => {
    if (rows.value.length >= 24) {
        return;
    }
    const lastDate = rows.value[rows.value.length - 1]?.due_date ?? inDays(30);
    const nextDate = (() => {
        const d = new Date(lastDate);
        d.setDate(d.getDate() + 30);
        return d.toISOString().slice(0, 10);
    })();
    rows.value.push({
        installment_no: rows.value.length + 1,
        amount: 0,
        due_date: nextDate,
    });
};

const removeRow = (index: number) => {
    if (rows.value.length <= 1) {
        return;
    }
    rows.value.splice(index, 1);
    // Re-number installment_no contiguously.
    rows.value.forEach((r, i) => {
        r.installment_no = i + 1;
    });
};

/**
 * Auto-balance: when user edits an amount, set the LAST row to absorb the remainder.
 * Skips if user is editing the last row (avoid infinite balance loop).
 */
const handleAmountChange = (index: number) => {
    if (index === rows.value.length - 1) {
        return;
    }
    const sumExceptLast = rows.value
        .slice(0, -1)
        .reduce((acc, r) => acc + (Number(r.amount) || 0), 0);
    const lastRow = rows.value[rows.value.length - 1];
    lastRow.amount = Math.max(0, props.netSplitTarget - sumExceptLast);
};

const close = () => {
    if (isSubmitting.value) {
        return;
    }
    emit('update:open', false);
};

const submit = () => {
    if (!canSubmit.value) {
        return;
    }
    isSubmitting.value = true;

    router.post(
        route('finance.charges.installments.split', props.chargeId),
        {
            installments: rows.value.map((r) => ({
                installment_no: r.installment_no,
                amount: r.amount,
                due_date: r.due_date,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('update:open', false);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(typeof firstError === 'string' ? firstError : 'Có lỗi xảy ra.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Tách đợt thanh toán</DialogTitle>
                <DialogDescription>
                    Mặc định 2 đợt × 50%. Bạn có thể chỉnh số lượng, số tiền, và hạn thanh toán.
                    Tổng các đợt phải bằng <strong>{{ formatCurrency(netSplitTarget) }}</strong>.
                    <span v-if="currentPlanCount > 0" class="block mt-1 text-amber-700">
                        Kế hoạch hiện có {{ currentPlanCount }} đợt sẽ bị thay thế.
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3 py-2">
                <div
                    v-for="(row, index) in rows"
                    :key="index"
                    class="grid grid-cols-[2rem_1fr_1fr_2.5rem] gap-3 items-end"
                >
                    <Label class="pb-2 text-sm font-medium">#{{ row.installment_no }}</Label>

                    <div class="space-y-1">
                        <Label :for="`amount-${index}`" class="text-xs text-muted-foreground">
                            Số tiền (VND)
                        </Label>
                        <Input
                            :id="`amount-${index}`"
                            v-model.number="row.amount"
                            type="number"
                            min="0"
                            step="1"
                            @change="handleAmountChange(index)"
                            :disabled="isSubmitting"
                        />
                    </div>

                    <div class="space-y-1">
                        <Label :for="`due-${index}`" class="text-xs text-muted-foreground">
                            Hạn thanh toán
                        </Label>
                        <Input
                            :id="`due-${index}`"
                            v-model="row.due_date"
                            type="date"
                            :disabled="isSubmitting"
                        />
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="rows.length <= 1 || isSubmitting"
                        @click="removeRow(index)"
                        title="Xoá đợt"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="rows.length >= 24 || isSubmitting"
                    @click="addRow"
                >
                    <Plus class="h-4 w-4 mr-1" /> Thêm đợt
                </Button>

                <div class="border-t pt-3 text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Tổng các đợt:</span>
                        <strong :class="sumMatchesTarget ? 'text-green-700' : 'text-amber-700'">
                            {{ formatCurrency(currentSum) }}
                        </strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Số cần thu (NET):</span>
                        <strong>{{ formatCurrency(netSplitTarget) }}</strong>
                    </div>
                    <div v-if="!sumMatchesTarget" class="flex justify-between">
                        <span class="text-muted-foreground">
                            Lệch ({{ remainder > 0 ? 'thiếu' : 'thừa' }}):
                        </span>
                        <strong class="text-amber-700">
                            {{ formatCurrency(Math.abs(remainder)) }}
                        </strong>
                    </div>
                </div>

                <ul v-if="validationErrors.length > 0" class="text-sm text-red-600 list-disc pl-5">
                    <li v-for="(err, i) in validationErrors" :key="i">{{ err }}</li>
                </ul>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" :disabled="isSubmitting" @click="close">
                    <X class="h-4 w-4 mr-1" /> Hủy
                </Button>
                <Button type="button" :disabled="!canSubmit" @click="submit">
                    {{ isSubmitting ? 'Đang lưu...' : 'Lưu kế hoạch' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
