<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Student360Actions, Student360StatusCards } from '@/types/finance';
import { formatCurrency } from '@/utils/format';
import { computed } from 'vue';
import DngStateStepper from './DngStateStepper.vue';

const props = defineProps<{
    cards: Student360StatusCards;
    actions: Student360Actions;
    focus?: { type: string; id: number } | null;
}>();

const focusRing = computed(() => 'ring-2 ring-orange-400 ring-offset-2 ring-offset-background rounded-md transition-shadow');

const isBalanceFocused = computed(
    () => props.focus?.type === 'payment' && props.focus.id === props.cards.balance.unapplied_payment_id,
);
const isDngFocused = computed(
    () => props.focus?.type === 'dng' && props.cards.dng.request?.id === props.focus?.id,
);
const isInstallmentFocused = computed(
    () => props.focus?.type === 'installment' && props.cards.installments.next?.id === props.focus?.id,
);
const isExceptionFocused = computed(
    () =>
        props.focus?.type === 'dng' &&
        props.cards.exception.dng_request_id === props.focus?.id &&
        props.cards.dng.request?.id !== props.focus?.id,
);

const emit = defineEmits<{
    (e: 'allocate'): void;
    (e: 'cancelDng', id: number): void;
    (e: 'pushInstallment', payload: { chargeId: number; installmentId: number }): void;
    (e: 'review', dngId: number): void;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <Card
            data-focus-section="balance"
            :class="cn(isBalanceFocused && focusRing)"
        >
            <CardHeader class="pb-1">
                <CardTitle class="text-xs font-medium">Số dư & phân bổ</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <p class="text-lg font-semibold tabular-nums">{{ formatCurrency(cards.balance.balance) }}</p>
                <p v-if="cards.balance.has_unapplied" class="text-sm text-blue-700 dark:text-blue-300">
                    Dư chưa khớp: {{ formatCurrency(cards.balance.unapplied_credit) }}
                </p>
                <Button
                    v-if="cards.balance.has_unapplied && actions.can_allocate"
                    size="sm"
                    variant="outline"
                    @click="emit('allocate')"
                >
                    Phân bổ ngay
                </Button>
            </CardContent>
        </Card>

        <Card
            data-focus-section="dng"
            :class="cn(isDngFocused && focusRing)"
        >
            <CardHeader class="pb-1">
                <CardTitle class="text-xs font-medium">DNG hiện tại</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <template v-if="cards.dng.has_active && cards.dng.request">
                    <DngStateStepper :status="cards.dng.request.status" />
                    <p v-if="cards.dng.request.error_message" class="text-sm text-red-600 dark:text-red-400">
                        {{ cards.dng.request.error_message }}
                    </p>
                    <Button
                        v-if="actions.can_cancel_dng && ['pending', 'pushed_to_dng'].includes(cards.dng.request.status)"
                        size="sm"
                        variant="destructive"
                        @click="emit('cancelDng', cards.dng.request.id)"
                    >
                        Hủy DNG
                    </Button>
                </template>
                <p v-else class="text-muted-foreground text-sm">Không có DNG.</p>
            </CardContent>
        </Card>

        <Card
            data-focus-section="installments"
            :class="cn(isInstallmentFocused && focusRing)"
        >
            <CardHeader class="pb-1">
                <CardTitle class="text-xs font-medium">Trả góp</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <template v-if="cards.installments.total > 0">
                    <p class="text-sm">{{ cards.installments.paid }} / {{ cards.installments.total }} kỳ đã trả</p>
                    <div v-if="cards.installments.next" class="flex flex-wrap items-center gap-2">
                        <Badge :variant="cards.installments.next.has_push_error ? 'destructive' : 'secondary'">
                            Kỳ {{ cards.installments.next.installment_no }} · {{ cards.installments.next.due_date ?? '—' }}
                        </Badge>
                        <Button
                            v-if="actions.can_record_payment"
                            size="sm"
                            variant="outline"
                            @click="
                                emit('pushInstallment', {
                                    chargeId: cards.installments.next.charge_id,
                                    installmentId: cards.installments.next.id,
                                })
                            "
                        >
                            Đẩy kỳ tới
                        </Button>
                    </div>
                </template>
                <p v-else class="text-muted-foreground text-sm">Không chia trả góp.</p>
            </CardContent>
        </Card>

        <Card
            data-focus-section="exception"
            :class="cn(isExceptionFocused && focusRing)"
        >
            <CardHeader class="pb-1">
                <CardTitle class="text-xs font-medium">Ngoại lệ</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <template v-if="cards.exception.needs_review">
                    <Badge variant="outline" class="text-orange-700 dark:text-orange-300">Cần review</Badge>
                    <Button
                        v-if="actions.can_cancel_dng && cards.exception.dng_request_id"
                        size="sm"
                        variant="outline"
                        @click="emit('review', cards.exception.dng_request_id)"
                    >
                        Review
                    </Button>
                </template>
                <template v-else-if="cards.exception.blocking_reasons.length">
                    <ul class="text-muted-foreground space-y-1 text-xs">
                        <li v-for="(reason, idx) in cards.exception.blocking_reasons" :key="idx">{{ reason }}</li>
                    </ul>
                </template>
                <p v-else class="text-muted-foreground text-sm">Không có ca cần thận trọng.</p>
            </CardContent>
        </Card>
    </div>
</template>