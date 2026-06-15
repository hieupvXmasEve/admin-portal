<script setup lang="ts">
import AllocatePreviewDrawer from '@/components/finance/student360/AllocatePreviewDrawer.vue';
import CancelDngDrawer from '@/components/finance/student360/CancelDngDrawer.vue';
import LedgerLens from '@/components/finance/student360/LedgerLens.vue';
import RecordPaymentDrawer from '@/components/finance/student360/RecordPaymentDrawer.vue';
import StatusCards from '@/components/finance/student360/StatusCards.vue';
import StudentActionMenu from '@/components/finance/student360/StudentActionMenu.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    LedgerEvent,
    LedgerGroup,
    Student360Actions,
    Student360Balances,
    Student360Identity,
    Student360StatusCards,
} from '@/types/finance';
import { formatCurrency } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { nextTick, onMounted, ref } from 'vue';

const props = defineProps<{
    student: Student360Identity;
    balances: Student360Balances;
    status_cards: Student360StatusCards;
    actions: Student360Actions;
    focus: { type: string; id: number } | null;
    links: { audit: string };
    ledger?: LedgerEvent[];
    ledger_groups?: LedgerGroup[];
}>();

const recordOpen = ref(false);
const allocateOpen = ref(false);
const cancelOpen = ref(false);
const allocatePaymentId = ref<number | null>(null);
const cancelDngId = ref<number | null>(null);
const balanceCards = [
    { key: 'net_charges', label: 'Phải thu', tone: 'text-foreground' },
    { key: 'total_paid', label: 'Đã thu', tone: 'text-green-700 dark:text-green-300' },
    { key: 'balance', label: 'Còn nợ', tone: 'text-orange-700 dark:text-orange-300' },
    { key: 'unapplied_credit', label: 'Dư chưa khớp', tone: 'text-blue-700 dark:text-blue-300' },
] as const;

const openAllocate = (): void => {
    allocatePaymentId.value = props.status_cards.balance.unapplied_payment_id;
    if (allocatePaymentId.value == null) return;
    allocateOpen.value = true;
};

const openCancel = (id: number): void => {
    cancelDngId.value = id;
    cancelOpen.value = true;
};

const pushInstallment = (payload: { chargeId: number; installmentId: number }): void => {
    router.post(
        financeRoutes.student360.retryInstallmentPush(payload.chargeId, payload.installmentId),
        {},
        { preserveScroll: true },
    );
};

const scrollToFocus = async (): Promise<void> => {
    if (!props.focus) return;

    await nextTick();

    const sectionByType: Record<string, string> = {
        payment: 'balance',
        dng: props.status_cards.dng.request?.id === props.focus.id ? 'dng' : 'exception',
        installment: 'installments',
    };

    const section = sectionByType[props.focus.type];
    if (!section) return;

    document.querySelector(`[data-focus-section="${section}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

onMounted(() => {
    void scrollToFocus();
});
</script>

<template>
    <Head :title="`Finance · ${props.student.full_name}`" />

    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ props.student.full_name }}</h1>
                <p class="text-muted-foreground text-sm tabular-nums">{{ props.student.student_code }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Badge variant="secondary">{{ props.student.academic_status ?? props.student.status }}</Badge>
                    <Badge variant="outline">{{ props.student.lifecycle_label }}</Badge>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <StudentActionMenu
                    :actions="props.actions"
                    :has-unapplied="props.status_cards.balance.has_unapplied"
                    @record-payment="recordOpen = true"
                    @allocate="openAllocate"
                />
                <Button as-child variant="outline" size="sm">
                    <Link :href="props.links.audit">
                        <ExternalLink class="mr-1 size-4" />
                        Mở Audit Workspace
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <Card v-for="card in balanceCards" :key="card.key">
                <CardHeader class="pb-1">
                    <CardTitle class="text-muted-foreground text-xs font-medium">{{ card.label }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-lg font-semibold tabular-nums" :class="card.tone">
                        {{ formatCurrency(props.balances[card.key]) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <StatusCards
            :cards="props.status_cards"
            :actions="props.actions"
            :focus="props.focus"
            @allocate="openAllocate"
            @cancel-dng="openCancel"
            @push-installment="pushInstallment"
            @review="openCancel"
        />

        <LedgerLens :timeline="props.ledger" :groups="props.ledger_groups" />

        <RecordPaymentDrawer v-model:open="recordOpen" :student-id="props.student.id" />
        <AllocatePreviewDrawer v-model:open="allocateOpen" :payment-id="allocatePaymentId" />
        <CancelDngDrawer
            v-model:open="cancelOpen"
            :dng-id="cancelDngId"
            :can-void-charges="props.actions.can_void_charges"
        />
    </div>
</template>