<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { LedgerEvent, Student360Balances, Student360Identity } from '@/types/finance';
import { formatCurrency, formatDate } from '@/utils/format';
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';

const props = defineProps<{
    student: Student360Identity;
    balances: Student360Balances;
    focus: { type: string; id: number } | null;
    links: { audit: string };
    ledger?: LedgerEvent[];
}>();

const balanceCards = [
    { key: 'net_charges', label: 'Phải thu', tone: 'text-foreground' },
    { key: 'total_paid', label: 'Đã thu', tone: 'text-green-700 dark:text-green-300' },
    { key: 'balance', label: 'Còn nợ', tone: 'text-orange-700 dark:text-orange-300' },
    { key: 'unapplied_credit', label: 'Dư chưa khớp', tone: 'text-blue-700 dark:text-blue-300' },
] as const;
</script>

<template>
    <Head :title="`Finance · ${props.student.full_name}`" />

    <div class="space-y-4">
        <!-- Header: dual status chip + identity + audit deep-link -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ props.student.full_name }}</h1>
                <p class="text-muted-foreground text-sm tabular-nums">{{ props.student.student_code }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Badge variant="secondary">{{ props.student.academic_status ?? props.student.status }}</Badge>
                    <Badge variant="outline">{{ props.student.lifecycle_label }}</Badge>
                </div>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link :href="props.links.audit"> <ExternalLink class="mr-1 size-4" /> Mở Audit Workspace </Link>
            </Button>
        </div>

        <!-- Four core balances from SettlementService (via GetStudentBalanceQuery) -->
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

        <!-- Basic signed ledger (deferred) -->
        <Card>
            <CardHeader>
                <CardTitle class="text-sm">Sổ cái (dòng thời gian)</CardTitle>
            </CardHeader>
            <CardContent>
                <Deferred data="ledger">
                    <template #fallback>
                        <div class="space-y-2">
                            <Skeleton class="h-8 w-full" v-for="n in 4" :key="n" />
                        </div>
                    </template>

                    <div v-if="props.ledger && props.ledger.length > 0" class="divide-y">
                        <div v-for="(event, idx) in props.ledger" :key="idx" class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <span class="font-medium">{{ event.label }}</span>
                                <span class="text-muted-foreground ml-2 text-xs">{{ event.at ? formatDate(event.at) : '—' }}</span>
                            </div>
                            <span class="tabular-nums" :class="event.signed_amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'">
                                {{ formatCurrency(event.signed_amount) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground py-6 text-center text-sm">Chưa có hoạt động sổ cái.</p>
                </Deferred>
            </CardContent>
        </Card>
    </div>
</template>
