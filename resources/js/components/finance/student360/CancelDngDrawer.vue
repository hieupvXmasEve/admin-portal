<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import { formatCurrency } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Impact {
    dng_request_id: number;
    linked_charges: { id: number; charge_type: string; status: string; amount: number }[];
    blocking_reasons: string[];
    requires_void_permission: boolean;
}

const props = defineProps<{ open: boolean; dngId: number | null; canVoidCharges: boolean }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const loading = ref(false);
const submitting = ref(false);
const impact = ref<Impact | null>(null);
const reason = ref('');
const acknowledged = ref(false);
const { get } = useApi();

watch(
    () => [props.open, props.dngId] as const,
    async ([open, dngId]) => {
        if (!open || dngId == null) {
            impact.value = null;
            return;
        }

        reason.value = '';
        acknowledged.value = false;
        loading.value = true;
        try {
            const response = await get<Impact>(financeRoutes.student360.dngCancelImpact(dngId));
            const payload = response.data.value;
            impact.value = payload?.success && payload.data ? payload.data : null;
        } finally {
            loading.value = false;
        }
    },
);

const blocked = computed(() => {
    if (!impact.value) return true;
    if (impact.value.blocking_reasons.length > 0) return true;
    if (impact.value.requires_void_permission && !props.canVoidCharges) return true;

    return false;
});

const canSubmit = computed(() => !blocked.value && reason.value.trim().length >= 3 && acknowledged.value);

const submit = (): void => {
    if (props.dngId == null || !canSubmit.value) return;

    submitting.value = true;
    router.post(
        financeRoutes.student360.dngCancelReviewed(props.dngId),
        { reason: reason.value, acknowledged: acknowledged.value },
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="w-full overflow-y-auto p-4 sm:max-w-md">
            <SheetHeader>
                <SheetTitle>Hủy DNG</SheetTitle>
            </SheetHeader>
            <Skeleton v-if="loading" class="mt-4 h-32 w-full" />
            <div v-else-if="impact" class="mt-4 space-y-3">
                <div v-if="impact.linked_charges.length" class="rounded-md border border-orange-300 bg-orange-50 p-2 text-sm dark:bg-orange-950">
                    <p class="font-medium">Sẽ void {{ impact.linked_charges.length }} phí liên kết:</p>
                    <ul class="mt-1 space-y-0.5 text-xs">
                        <li v-for="charge in impact.linked_charges" :key="charge.id" class="flex justify-between gap-2">
                            <span>{{ charge.charge_type }}</span>
                            <span class="tabular-nums">−{{ formatCurrency(charge.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <ul v-if="impact.blocking_reasons.length" class="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                    <li v-for="(blockingReason, index) in impact.blocking_reasons" :key="index">{{ blockingReason }}</li>
                </ul>
                <p v-else-if="impact.requires_void_permission && !canVoidCharges" class="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                    Hủy DNG này sẽ void phí — bạn cần quyền void_finance_charges.
                </p>

                <div class="space-y-1">
                    <Label for="cancel-reason">Lý do (bắt buộc)</Label>
                    <Textarea id="cancel-reason" v-model="reason" :disabled="blocked" rows="3" placeholder="Mô tả lý do hủy…" />
                </div>

                <div class="flex items-start gap-2">
                    <Checkbox id="cancel-ack" v-model:model-value="acknowledged" :disabled="blocked" class="mt-0.5" />
                    <Label for="cancel-ack" class="cursor-pointer text-sm leading-snug font-normal">
                        Tôi hiểu thao tác này không hoàn tác và sẽ được ghi vào lịch sử.
                    </Label>
                </div>

                <Button variant="destructive" class="w-full" :disabled="!canSubmit || submitting" @click="submit">
                    {{ submitting ? 'Đang hủy…' : 'Hủy DNG' }}
                </Button>
            </div>
            <p v-else class="text-muted-foreground mt-4 text-sm">Không tải được ảnh hưởng hủy DNG.</p>
        </SheetContent>
    </Sheet>
</template>
