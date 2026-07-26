<script setup lang="ts">
import { Badge, type BadgeVariants } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { StudentHubContext } from '@/types/models';
import { formatCurrency, formatDate } from '@/utils/format';
import { Head } from '@inertiajs/vue3';
import { Award, Coins, ExternalLink, Lock, Receipt } from 'lucide-vue-next';
import { computed } from 'vue';

/** Read-only finance summary, sourced from the Finance read contract (ADR-0007). */
interface FinanceFees {
    net_charges: number;
    total_paid: number;
    outstanding: number;
    unapplied_credit: number;
    status: string;
}

interface FinanceGold {
    balance: number;
}

interface FinanceScholarship {
    code: string;
    name: string | null;
    type: string | null;
    amount: number;
    awarded_at: string | null;
}

interface FinanceSummary {
    fees: FinanceFees;
    gold: FinanceGold;
    scholarships: FinanceScholarship[];
}

interface Props {
    student: StudentHubContext;
    finance: FinanceSummary;
    /** Deep link into the Finance Office surface that owns money operations. */
    financeOfficeUrl: string;
}

const props = defineProps<Props>();

const feeStatusVariant = computed<BadgeVariants['variant']>(() => {
    const variants: Record<string, BadgeVariants['variant']> = {
        paid: 'default',
        outstanding: 'destructive',
        overpaid: 'secondary',
        unknown: 'outline',
    };
    return variants[props.finance.fees.status] ?? 'outline';
});

const feeStatusLabel = computed(() => props.finance.fees.status.replace(/_/g, ' ').toUpperCase());

const scholarshipAmountLabel = (scholarship: FinanceScholarship): string => (scholarship.type === 'percentage' ? `${scholarship.amount}%` : formatCurrency(scholarship.amount));
</script>

<template>
    <div>
        <Head :title="`Finance — ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="finance">
            <div class="space-y-6">
                <!-- Read-only boundary: money is owned by the Finance Office (ADR-0007). -->
                <div class="bg-muted/40 flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <Lock class="text-muted-foreground mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p class="text-sm font-medium">Read-only finance summary</p>
                            <p class="text-muted-foreground text-sm">Fees, gold, and scholarships are shown for context. Payments, allocations, and adjustments are made in the Finance Office.</p>
                        </div>
                    </div>
                    <Button as-child variant="default" class="shrink-0">
                        <a :href="financeOfficeUrl">
                            <ExternalLink class="mr-2 h-4 w-4" />
                            Open in Finance Office
                        </a>
                    </Button>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Fees -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Receipt class="text-muted-foreground h-4 w-4" />
                                Fees
                            </CardTitle>
                            <Badge :variant="feeStatusVariant">{{ feeStatusLabel }}</Badge>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <p class="text-muted-foreground text-xs tracking-wide uppercase">Outstanding</p>
                                <p class="text-2xl font-bold" :class="finance.fees.outstanding > 0 ? 'text-destructive' : 'text-foreground'">
                                    {{ formatCurrency(finance.fees.outstanding) }}
                                </p>
                            </div>
                            <dl class="text-muted-foreground space-y-1.5 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt>Net charges</dt>
                                    <dd class="text-foreground font-medium">{{ formatCurrency(finance.fees.net_charges) }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Total paid</dt>
                                    <dd class="text-foreground font-medium">{{ formatCurrency(finance.fees.total_paid) }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt>Unapplied credit</dt>
                                    <dd class="text-foreground font-medium">{{ formatCurrency(finance.fees.unapplied_credit) }}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <!-- Gold wallet -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Coins class="text-muted-foreground h-4 w-4" />
                                Gold wallet
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-muted-foreground text-xs tracking-wide uppercase">Balance</p>
                            <p class="text-2xl font-bold">{{ finance.gold.balance.toLocaleString() }} <span class="text-base font-medium">gold</span></p>
                        </CardContent>
                    </Card>

                    <!-- Scholarships -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Award class="text-muted-foreground h-4 w-4" />
                                Scholarships
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p v-if="finance.scholarships.length === 0" class="text-muted-foreground text-sm">No scholarships awarded.</p>
                            <ul v-else class="divide-border divide-y">
                                <li v-for="scholarship in finance.scholarships" :key="scholarship.code" class="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ scholarship.name ?? scholarship.code }}</p>
                                        <p class="text-muted-foreground text-xs">
                                            <span>{{ scholarship.code }}</span>
                                            <template v-if="scholarship.awarded_at"> · {{ formatDate(scholarship.awarded_at) }}</template>
                                        </p>
                                    </div>
                                    <span class="text-foreground shrink-0 text-sm font-semibold">{{ scholarshipAmountLabel(scholarship) }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </StudentLayout>
    </div>
</template>
