<script setup lang="ts">
import AllocatePreviewDrawer from '@/components/finance/student360/AllocatePreviewDrawer.vue';
import CancelDngDrawer from '@/components/finance/student360/CancelDngDrawer.vue';
import LedgerLens from '@/components/finance/student360/LedgerLens.vue';
import RecordPaymentDrawer from '@/components/finance/student360/RecordPaymentDrawer.vue';
import StatusCards from '@/components/finance/student360/StatusCards.vue';
import StudentActionMenu from '@/components/finance/student360/StudentActionMenu.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    LedgerEvent,
    LedgerGroup,
    Student360Actions,
    Student360Balances,
    Student360Identity,
    Student360StatusCards,
    StudentFinanceOverviewKpi,
    StudentFinancePaymentHistoryRow,
    StudentFinanceReviewSignal,
    StudentFinanceTuitionOverview,
} from '@/types/finance';
import { formatCurrency, formatDate } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, CircleAlert, ExternalLink, Receipt } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps<{
    student: Student360Identity;
    balances: Student360Balances;
    tuition_overview: StudentFinanceTuitionOverview;
    payment_history: StudentFinancePaymentHistoryRow[];
    review_signals: StudentFinanceReviewSignal[];
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
const tuitionKpis = computed<Array<StudentFinanceOverviewKpi & { key: string; tone: string }>>(() => [
    {
        ...props.tuition_overview.kpis.collectible_due,
        key: 'collectible_due',
        tone: props.tuition_overview.kpis.collectible_due.amount > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-primary',
    },
    {
        ...props.tuition_overview.kpis.total_paid,
        key: 'total_paid',
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        ...props.tuition_overview.kpis.collected,
        key: 'collected',
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        ...props.tuition_overview.kpis.surplus,
        key: 'surplus',
        tone: props.tuition_overview.kpis.surplus.amount > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-muted-foreground',
    },
]);

const openAllocate = (): void => {
    allocatePaymentId.value = props.status_cards.balance.unapplied_payment_id;
    if (allocatePaymentId.value == null) return;
    allocateOpen.value = true;
};

const openAllocatePayment = (paymentId: number): void => {
    allocatePaymentId.value = paymentId;
    allocateOpen.value = true;
};

const openCancel = (id: number): void => {
    cancelDngId.value = id;
    cancelOpen.value = true;
};

const pushInstallment = (payload: { chargeId: number; installmentId: number }): void => {
    router.post(financeRoutes.student360.retryInstallmentPush(payload.chargeId, payload.installmentId), {}, { preserveScroll: true });
};

const scrollToReviewTarget = async (target: string): Promise<void> => {
    await nextTick();

    document.querySelector(`[data-review-target="${target}"], [data-focus-section="${target}"], #${target}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
    <Head :title="`${props.tuition_overview.title} · ${props.student.full_name}`" />

    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ props.tuition_overview.title }}</h1>
                <p class="text-muted-foreground text-sm">
                    {{ props.student.full_name }} · <span class="tabular-nums">{{ props.student.student_code }}</span>
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Badge variant="secondary">{{ props.student.academic_status ?? props.student.status }}</Badge>
                    <Badge variant="outline">{{ props.student.lifecycle_label }}</Badge>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <StudentActionMenu :actions="props.actions" :has-unapplied="props.status_cards.balance.has_unapplied" @record-payment="recordOpen = true" @allocate="openAllocate" />
                <Button as-child variant="outline" size="sm">
                    <Link :href="props.links.audit">
                        <ExternalLink class="mr-1 size-4" />
                        Mở Audit Workspace
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <Card v-for="card in tuitionKpis" :key="card.key" :class="card.primary ? 'border-primary/40 bg-primary/5 shadow-sm' : ''">
                <CardHeader class="pb-1">
                    <CardTitle class="text-xs font-medium" :class="card.primary ? 'text-primary' : 'text-muted-foreground'">
                        {{ card.label }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="font-semibold tabular-nums" :class="[card.primary ? 'text-2xl md:text-3xl' : 'text-lg', card.tone]">
                        {{ formatCurrency(card.amount) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <p v-if="props.tuition_overview.surplus_message" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200">
            {{ props.tuition_overview.surplus_message }}
        </p>

        <Card v-if="props.review_signals.length" class="border-amber-200 bg-amber-50/50 shadow-none dark:border-amber-900/70 dark:bg-amber-950/20">
            <CardHeader class="pb-2">
                <div class="flex items-start gap-2">
                    <div class="mt-0.5 rounded-md bg-amber-100 p-2 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200">
                        <AlertTriangle class="size-4" />
                    </div>
                    <div>
                        <CardTitle class="text-sm text-amber-950 dark:text-amber-100">Cần kiểm tra</CardTitle>
                        <CardDescription class="mt-0.5 text-amber-800 dark:text-amber-200">Tín hiệu rà soát dữ liệu, không phải số tiền cần thu thêm.</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="pt-0">
                <ul class="divide-y divide-amber-200/70 dark:divide-amber-900/70">
                    <li v-for="signal in props.review_signals" :key="signal.id" class="flex flex-wrap items-start justify-between gap-2 py-2 first:pt-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-amber-950 dark:text-amber-100">{{ signal.title }}</p>
                            <p class="text-sm text-amber-800 dark:text-amber-200">{{ signal.message }}</p>
                        </div>
                        <Button size="sm" variant="ghost" class="h-8 shrink-0 text-amber-900 hover:bg-amber-100 hover:text-amber-950 dark:text-amber-100 dark:hover:bg-amber-950/60" @click="scrollToReviewTarget(signal.target)">
                            {{ signal.target_label }}
                        </Button>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card id="payment-history" data-review-target="payment-history" class="overflow-hidden">
            <CardHeader class="bg-muted/30 border-b pb-3">
                <div class="flex items-start gap-2">
                    <div class="bg-primary/10 text-primary mt-0.5 rounded-md p-2">
                        <Receipt class="size-4" />
                    </div>
                    <div>
                        <CardTitle class="text-base">Các khoản đã nộp</CardTitle>
                        <CardDescription class="mt-0.5">Lịch sử tiền đã nộp, phần đã thu và phần còn dư</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="props.payment_history.length" class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr class="text-muted-foreground bg-muted/20 border-b text-left text-xs">
                                <th class="px-4 py-2.5 font-medium">Ngày nộp</th>
                                <th class="px-4 py-2.5 font-medium">Nguồn</th>
                                <th class="px-4 py-2.5 font-medium">Tham chiếu</th>
                                <th class="px-4 py-2.5 text-right font-medium">Tổng nộp</th>
                                <th class="px-4 py-2.5 text-right font-medium">Đã thu</th>
                                <th class="px-4 py-2.5 text-right font-medium">Còn dư</th>
                                <th class="px-4 py-2.5 font-medium">Trạng thái</th>
                                <th class="px-4 py-2.5 text-right font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in props.payment_history" :key="row.id" class="bg-card align-top">
                                <td class="px-4 py-3 whitespace-nowrap tabular-nums">
                                    {{ formatDate(row.paid_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline" class="whitespace-nowrap">
                                        {{ row.source_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <Link :href="financeRoutes.collect.paymentDetail(row.id)" class="hover:text-primary inline-flex max-w-[220px] items-center gap-1 truncate font-medium transition-colors">
                                        <span class="truncate">{{ row.reference }}</span>
                                        <ExternalLink class="size-3 shrink-0" />
                                    </Link>
                                    <p v-if="row.dng_request_reference && row.dng_request_reference !== row.reference" class="text-muted-foreground mt-1 text-xs">
                                        {{ row.dng_request_reference }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                    {{ formatCurrency(row.amount_paid) }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap text-emerald-700 tabular-nums dark:text-emerald-300">
                                    {{ formatCurrency(row.collected_amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums" :class="row.surplus_amount > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-muted-foreground'">
                                    {{ formatCurrency(row.surplus_amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        variant="outline"
                                        class="whitespace-nowrap"
                                        :class="
                                            row.status === 'surplus'
                                                ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200'
                                                : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200'
                                        "
                                    >
                                        <CircleAlert v-if="row.status === 'surplus'" class="mr-1 size-3" />
                                        <CheckCircle2 v-else class="mr-1 size-3" />
                                        {{ row.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button v-if="row.action.can_allocate && props.actions.can_allocate" size="sm" variant="outline" @click="openAllocatePayment(row.id)"> Phân bổ </Button>
                                    <p v-else-if="row.action.message" class="text-muted-foreground ml-auto max-w-[220px] text-xs">
                                        {{ row.action.message }}
                                    </p>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
                    <Receipt class="text-muted-foreground size-8 opacity-40" />
                    <p class="text-muted-foreground text-sm">Chưa có khoản đã nộp.</p>
                </div>
            </CardContent>
        </Card>

        <StatusCards :cards="props.status_cards" :actions="props.actions" :focus="props.focus" @allocate="openAllocate" @cancel-dng="openCancel" @push-installment="pushInstallment" @review="openCancel" />

        <LedgerLens :timeline="props.ledger" :groups="props.ledger_groups" />

        <RecordPaymentDrawer v-model:open="recordOpen" :student-id="props.student.id" />
        <AllocatePreviewDrawer v-model:open="allocateOpen" :payment-id="allocatePaymentId" />
        <CancelDngDrawer v-model:open="cancelOpen" :dng-id="cancelDngId" :can-void-charges="props.actions.can_void_charges" />
    </div>
</template>
