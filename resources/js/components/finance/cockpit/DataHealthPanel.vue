<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { CockpitDataHealth } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Deferred, Link } from '@inertiajs/vue3';

defineProps<{ dataHealth?: CockpitDataHealth }>();

const drilldown = (code: string): string => {
    const params = new URLSearchParams({
        finding_code: code,
        scope: 'campus',
    });

    return `${financeRoutes.audit()}?${params.toString()}`;
};

const sev = (s: string): string =>
    s === 'CRITICAL' ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' : s === 'ERROR' ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' : 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200';
</script>

<template>
    <Card>
        <CardHeader class="pb-1"><CardTitle class="text-sm">Sức khỏe dữ liệu</CardTitle></CardHeader>
        <CardContent>
            <Deferred data="data_health">
                <template #fallback><Skeleton class="h-32 w-full" /></template>
                <div v-if="dataHealth" class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span :title="`Mẫu số = ${dataHealth.balance_match.denominator} hóa đơn active trong phạm vi`">Số dư khớp</span>
                        <strong class="tabular-nums" :class="dataHealth.balance_match.match_pct >= 100 ? 'text-green-700 dark:text-green-300' : 'text-orange-700 dark:text-orange-300'"> {{ dataHealth.balance_match.match_pct }}% </strong>
                    </div>
                    <ul class="space-y-1">
                        <li v-for="inv in dataHealth.invariants" :key="inv.code" class="flex items-center justify-between rounded-md border p-2 text-sm">
                            <span>
                                <Badge :class="sev(inv.severity)" class="mr-2">{{ inv.code }}</Badge>
                                {{ inv.label }}
                            </span>
                            <Link :href="drilldown(inv.code)" class="text-xs font-medium underline"> {{ inv.error ? 'lỗi kiểm tra' : `${inv.count} mẫu` }} ↗ </Link>
                        </li>
                    </ul>
                    <p v-if="dataHealth.invariants.length === 0" class="text-muted-foreground text-sm">Không có vi phạm.</p>
                </div>
            </Deferred>
        </CardContent>
    </Card>
</template>
