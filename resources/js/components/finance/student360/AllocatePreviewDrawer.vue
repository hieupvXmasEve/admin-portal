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
    outstanding: number;
    would_apply: number;
}

const props = defineProps<{ open: boolean; paymentId: number | null }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const loading = ref(false);
const applyingLineId = ref<number | null>(null);
const preview = ref<{ unapplied: number; candidates: Candidate[] } | null>(null);
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
            const response = await get<{ unapplied: number; candidates: Candidate[] }>(financeRoutes.student360.allocatePreview(paymentId));
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
        financeRoutes.student360.allocate(props.paymentId),
        { charge_id: candidate.charge_id, amount: candidate.would_apply },
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
