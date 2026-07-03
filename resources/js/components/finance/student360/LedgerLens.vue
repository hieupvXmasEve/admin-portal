<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type { LedgerEvent, LedgerGroup } from '@/types/finance';
import { getChargeTypeBadgeClass, getChargeTypeLabel, getInvoiceStatusBadgeClass, getInvoiceStatusLabel } from '@/types/finance';
import { formatCurrency, formatDate } from '@/utils/format';
import { financeRoutes } from '@/utils/routes';
import { Deferred, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight, BookOpen, Calendar, CheckCircle2, Clock, ExternalLink, Minus, Receipt } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ timeline?: LedgerEvent[]; groups?: LedgerGroup[] }>();

const timelineEventMeta = (event: LedgerEvent) => {
    const isCredit = event.signed_amount < 0;

    if (event.type === 'payment_application') {
        return {
            icon: ArrowDownLeft,
            tone: 'text-emerald-700 dark:text-emerald-300',
            bg: 'bg-emerald-50 dark:bg-emerald-950/40',
            label: event.label.replace(/^Payment /i, 'Thanh toán · '),
        };
    }

    if (event.type === 'discount_allocation') {
        return {
            icon: Minus,
            tone: 'text-violet-700 dark:text-violet-300',
            bg: 'bg-violet-50 dark:bg-violet-950/40',
            label: event.label.replace(/^Discount /i, 'Giảm giá · '),
        };
    }

    return {
        icon: isCredit ? ArrowDownLeft : ArrowUpRight,
        tone: isCredit ? 'text-emerald-700 dark:text-emerald-300' : 'text-orange-700 dark:text-orange-300',
        bg: isCredit ? 'bg-emerald-50 dark:bg-emerald-950/40' : 'bg-orange-50 dark:bg-orange-950/40',
        label: event.label,
    };
};

const invoiceBorderClass = (status: string): string => {
    const map: Record<string, string> = {
        paid: 'border-l-emerald-500',
        partial: 'border-l-amber-500',
        overdue: 'border-l-red-500',
        pending: 'border-l-blue-500',
        draft: 'border-l-slate-400',
        cancelled: 'border-l-gray-400',
        void: 'border-l-gray-400',
    };

    return map[status] ?? 'border-l-slate-300';
};

const semesterSummary = (group: LedgerGroup) => {
    return {
        count: group.invoices.length,
        remaining: group.collectible_remaining,
        net: group.collectible_total,
    };
};

const totalOutstanding = computed(() => {
    if (!props.groups?.length) return 0;

    return props.groups.reduce((sum, group) => sum + group.collectible_remaining, 0);
});

const formatLineAmount = (amount: number, isCredit: boolean): string => {
    const formatted = formatCurrency(Math.abs(amount));

    return isCredit ? `−${formatted}` : formatted;
};
</script>

<template>
    <Card id="ledger" data-review-target="ledger" class="overflow-hidden">
        <CardHeader class="bg-muted/30 border-b pb-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-2">
                    <div class="bg-primary/10 text-primary mt-0.5 rounded-md p-2">
                        <BookOpen class="size-4" />
                    </div>
                    <div>
                        <CardTitle class="text-base">Sổ cái</CardTitle>
                        <CardDescription class="mt-0.5">Hóa đơn theo kỳ học và dòng phí chi tiết</CardDescription>
                    </div>
                </div>
                <div v-if="groups?.length" class="text-right">
                    <p class="text-muted-foreground text-xs">Tổng còn phải thu</p>
                    <p class="text-lg font-semibold tabular-nums" :class="totalOutstanding > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-emerald-700 dark:text-emerald-300'">
                        {{ formatCurrency(totalOutstanding) }}
                    </p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-4">
            <Tabs default-value="ledger" class="w-full">
                <TabsList class="grid h-9 w-full max-w-sm grid-cols-2">
                    <TabsTrigger value="ledger" class="gap-1.5 text-xs sm:text-sm">
                        <Receipt class="size-3.5" />
                        Sổ cái
                    </TabsTrigger>
                    <TabsTrigger value="timeline" class="gap-1.5 text-xs sm:text-sm">
                        <Clock class="size-3.5" />
                        Dòng thời gian
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="ledger" class="mt-4">
                    <Deferred data="ledger_groups">
                        <template #fallback>
                            <div class="space-y-3">
                                <Skeleton class="h-10 w-full" />
                                <Skeleton class="h-32 w-full" />
                                <Skeleton class="h-32 w-full" />
                            </div>
                        </template>

                        <div v-if="groups && groups.length" class="space-y-5">
                            <section v-for="group in groups" :key="group.semester.id ?? 'none'" class="overflow-hidden rounded-lg border">
                                <div class="bg-muted/50 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-2.5">
                                    <div>
                                        <h3 class="text-sm font-semibold tracking-tight">
                                            {{ group.semester.name }}
                                        </h3>
                                        <p v-if="group.semester.code" class="text-muted-foreground text-xs">
                                            {{ group.semester.code }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-xs">
                                        <Badge variant="outline" class="tabular-nums"> {{ semesterSummary(group).count }} hóa đơn </Badge>
                                        <Badge variant="outline" class="tabular-nums" :class="semesterSummary(group).remaining > 0 ? 'border-orange-200 bg-orange-50 text-orange-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'">
                                            <template v-if="semesterSummary(group).remaining > 0">Còn phải thu {{ formatCurrency(semesterSummary(group).remaining) }}</template>
                                            <template v-else>{{ group.state_label }}</template>
                                        </Badge>
                                    </div>
                                </div>

                                <div class="divide-y">
                                    <article v-for="invoice in group.invoices" :key="invoice.id" class="bg-card border-l-4 px-4 py-3" :class="invoiceBorderClass(invoice.status)">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0 space-y-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <Link :href="financeRoutes.lookup.invoiceDetail(invoice.id)" class="hover:text-primary truncate font-medium tabular-nums transition-colors">
                                                        {{ invoice.invoice_number }}
                                                    </Link>
                                                    <Badge variant="outline" :class="cn('text-[11px] font-medium', getInvoiceStatusBadgeClass(invoice.status))">
                                                        <CheckCircle2 v-if="invoice.status === 'paid'" class="mr-1 size-3" />
                                                        {{ getInvoiceStatusLabel(invoice.status) }}
                                                    </Badge>
                                                    <Link :href="financeRoutes.lookup.invoiceDetail(invoice.id)" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-0.5 text-xs">
                                                        Chi tiết
                                                        <ExternalLink class="size-3" />
                                                    </Link>
                                                </div>

                                                <div class="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                                    <span v-if="invoice.due_date" class="inline-flex items-center gap-1">
                                                        <Calendar class="size-3" />
                                                        Hạn: {{ formatDate(invoice.due_date) }}
                                                    </span>
                                                    <span v-if="invoice.paid_at" class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-300">
                                                        <CheckCircle2 class="size-3" />
                                                        Thanh toán: {{ formatDate(invoice.paid_at) }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="grid shrink-0 grid-cols-2 gap-x-4 gap-y-1 text-right text-xs sm:grid-cols-4">
                                                <div>
                                                    <p class="text-muted-foreground">Tổng</p>
                                                    <p class="font-medium tabular-nums">{{ formatCurrency(invoice.net) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Giảm giá</p>
                                                    <p class="font-medium text-violet-700 tabular-nums dark:text-violet-300">
                                                        {{ invoice.discount > 0 ? `−${formatCurrency(invoice.discount)}` : '—' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Đã thu</p>
                                                    <p class="font-medium text-emerald-700 tabular-nums dark:text-emerald-300">
                                                        {{ formatCurrency(invoice.paid) }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Còn phải thu</p>
                                                    <p class="font-semibold tabular-nums" :class="invoice.remaining > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-emerald-700 dark:text-emerald-300'">
                                                        {{ formatCurrency(invoice.remaining) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="invoice.lines.length" class="bg-muted/20 mt-3 overflow-x-auto rounded-md border">
                                            <table class="w-full min-w-[520px] text-xs">
                                                <thead>
                                                    <tr class="text-muted-foreground border-b text-left">
                                                        <th class="px-3 py-2 font-medium">Loại phí</th>
                                                        <th class="px-3 py-2 font-medium">Mô tả</th>
                                                        <th class="px-3 py-2 text-right font-medium">Phải thu</th>
                                                        <th class="px-3 py-2 text-right font-medium">Đã thu</th>
                                                        <th class="px-3 py-2 text-right font-medium">Còn phải thu</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y">
                                                    <tr v-for="line in invoice.lines" :key="line.id" :class="line.status === 'void' ? 'bg-muted/30 text-muted-foreground' : ''">
                                                        <td class="px-3 py-2 align-top">
                                                            <Badge
                                                                variant="outline"
                                                                :class="cn('text-[10px] whitespace-nowrap', line.status === 'void' ? 'border-gray-200 bg-gray-100 text-gray-600' : getChargeTypeBadgeClass(line.charge_type ?? line.label))"
                                                            >
                                                                {{ getChargeTypeLabel(line.charge_type ?? line.label) }}
                                                            </Badge>
                                                            <Badge v-if="line.status === 'void'" variant="outline" class="mt-1 block w-fit border-gray-200 bg-gray-100 text-[10px] text-gray-600">
                                                                {{ line.status_label }}
                                                            </Badge>
                                                        </td>
                                                        <td class="max-w-[220px] px-3 py-2 align-top">
                                                            <p class="truncate" :class="line.status === 'void' ? 'text-muted-foreground' : ''">
                                                                {{ line.description || '—' }}
                                                            </p>
                                                            <p v-if="line.status === 'void' && line.void_reason" class="text-muted-foreground mt-1 truncate text-[11px]">
                                                                {{ line.void_reason }}
                                                            </p>
                                                        </td>
                                                        <td
                                                            class="px-3 py-2 text-right align-top tabular-nums"
                                                            :class="[line.is_credit ? 'text-emerald-700 dark:text-emerald-300' : '', line.status === 'void' ? 'text-muted-foreground line-through' : '']"
                                                        >
                                                            {{ formatLineAmount(line.amount, line.is_credit) }}
                                                        </td>
                                                        <td class="px-3 py-2 text-right align-top tabular-nums">
                                                            <p :class="line.paid > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'">
                                                                {{ line.paid > 0 ? formatCurrency(line.paid) : '—' }}
                                                            </p>
                                                            <div v-if="line.payment_applied > 0 || line.payment_reversed > 0" class="text-muted-foreground mt-1 space-y-0.5 text-[11px]">
                                                                <p v-if="line.payment_applied > 0">Đã áp dụng {{ formatCurrency(line.payment_applied) }}</p>
                                                                <p v-if="line.payment_reversed > 0">Đã đảo {{ formatCurrency(line.payment_reversed) }}</p>
                                                            </div>
                                                        </td>
                                                        <td class="px-3 py-2 text-right align-top font-medium tabular-nums" :class="line.outstanding > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-muted-foreground'">
                                                            {{ formatCurrency(line.outstanding) }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </article>
                                </div>
                            </section>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                            <Receipt class="text-muted-foreground size-8 opacity-40" />
                            <p class="text-muted-foreground text-sm">Chưa có hóa đơn cho sinh viên này.</p>
                        </div>
                    </Deferred>
                </TabsContent>

                <TabsContent value="timeline" class="mt-4">
                    <Deferred data="ledger">
                        <template #fallback>
                            <Skeleton class="h-24 w-full" />
                        </template>

                        <div v-if="timeline && timeline.length" class="divide-y rounded-lg border">
                            <div v-for="(event, idx) in timeline" :key="idx" class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div :class="cn('rounded-full p-2', timelineEventMeta(event).bg)">
                                        <component :is="timelineEventMeta(event).icon" :class="cn('size-3.5', timelineEventMeta(event).tone)" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ timelineEventMeta(event).label }}</p>
                                        <p class="text-muted-foreground text-xs">
                                            {{ event.at ? formatDate(event.at) : 'Không có ngày' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="shrink-0 text-sm font-semibold tabular-nums" :class="event.signed_amount < 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-orange-700 dark:text-orange-300'">
                                    {{ event.signed_amount < 0 ? '' : '+' }}{{ formatCurrency(event.signed_amount) }}
                                </span>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                            <Clock class="text-muted-foreground size-8 opacity-40" />
                            <p class="text-muted-foreground text-sm">Chưa có hoạt động tài chính.</p>
                        </div>
                    </Deferred>
                </TabsContent>
            </Tabs>
        </CardContent>
    </Card>
</template>
