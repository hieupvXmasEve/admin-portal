<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApiRequest';
import { formatCurrency } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Candidate {
    invoice_line_id: number;
    charge_id: number | null;
    label: string;
    gross: number;
    discount: number;
    cash_applied: number;
    credit_applied: number;
    remaining_collectible: number;
    outstanding: number;
    would_apply: number;
}

interface SettlementPositionReview {
    valid: boolean;
    issues: Array<{
        code: string;
        severity: string;
        blocking: boolean;
        evidence: Record<string, string | number>;
        finance_invariant_code: string | null;
    }>;
}

interface AllocationPreview {
    unapplied: number;
    candidates: Candidate[];
    settlement_position: SettlementPositionReview;
}

const props = defineProps<{ open: boolean; paymentId: number | null }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const loading = ref(false);
const applyingLineId = ref<number | null>(null);
const preview = ref<AllocationPreview | null>(null);
const { get } = useApi();

watch(
    () => [props.open, props.paymentId] as const,
    async ([open, paymentId]) => {
        if (!open || paymentId == null) {
            preview.value = null;
            return;
        }

        loading.value = true;
        try {
            const response = await get<AllocationPreview>(financeRoutes.student360.allocatePreview(paymentId));
            const payload = response.data.value;
            preview.value = payload?.success && payload.data ? payload.data : null;
        } finally {
            loading.value = false;
        }
    },
);

const apply = (candidate: Candidate): void => {
    if (props.paymentId == null || candidate.charge_id == null) return;

    applyingLineId.value = candidate.invoice_line_id;
    router.post(
        financeRoutes.student360.disposeSurplus(props.paymentId),
        { idempotency_key: crypto.randomUUID(), type: 'reallocate', invoice_line_id: candidate.invoice_line_id, amount: candidate.would_apply },
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
            onFinish: () => {
                applyingLineId.value = null;
            },
        },
    );
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="w-full overflow-y-auto p-4 sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Phân bổ — xem trước</SheetTitle>
            </SheetHeader>
            <Skeleton v-if="loading" class="mt-4 h-24 w-full" />
            <div v-else-if="preview" class="mt-4 space-y-3">
                <p class="text-sm">
                    Dư chưa khớp:
                    <strong class="tabular-nums">{{ formatCurrency(preview.unapplied) }}</strong>
                </p>
                <div v-if="!preview.settlement_position.valid" class="border-destructive/30 bg-destructive/5 text-destructive rounded-md border p-3 text-sm">
                    <p>Không thể đề xuất phân bổ. Cần kiểm tra:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="issue in preview.settlement_position.issues" :key="issue.code">
                            {{ issue.code }}
                            <span v-if="issue.finance_invariant_code">({{ issue.finance_invariant_code }})</span>
                            <span v-if="Object.keys(issue.evidence).length">— {{ Object.entries(issue.evidence).map(([key, value]) => `${key}: ${value}`).join(', ') }}</span>
                        </li>
                    </ul>
                </div>
                <div v-for="candidate in preview.candidates" :key="candidate.invoice_line_id" class="flex items-center justify-between gap-2 rounded-md border p-2 text-sm">
                    <div class="min-w-0">
                        {{ candidate.label }}
                        <span class="text-muted-foreground ml-2 text-xs"> áp {{ formatCurrency(candidate.would_apply) }} </span>
                    </div>
                    <Button size="sm" variant="outline" :disabled="candidate.charge_id == null || applyingLineId === candidate.invoice_line_id" @click="apply(candidate)"> Áp </Button>
                </div>
                <p v-if="!preview.candidates.length" class="text-muted-foreground text-sm">Không có dòng phí phù hợp.</p>
            </div>
            <p v-else class="text-muted-foreground mt-4 text-sm">Không tải được xem trước phân bổ.</p>
        </SheetContent>
    </Sheet>
</template>
