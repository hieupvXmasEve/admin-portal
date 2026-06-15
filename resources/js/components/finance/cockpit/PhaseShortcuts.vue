<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CockpitPhase } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps<{ phase: CockpitPhase }>();

const shortcuts = {
    early: [
        { label: 'Sinh phí hàng loạt', href: financeRoutes.feeGeneration.batchCharges() },
        { label: 'Đẩy DNG hàng loạt', href: financeRoutes.collect.dngWorklist() },
    ],
    late: [
        { label: 'Nhắc nợ', href: financeRoutes.collect.dueReminders() },
        { label: 'Đối soát', href: financeRoutes.collect.settlement() },
    ],
    mid: [
        { label: 'Phân bổ', href: financeRoutes.collect.settlement() },
        { label: 'Nhắc nợ', href: financeRoutes.collect.dueReminders() },
    ],
} as const;

const phaseLabels: Record<CockpitPhase['key'], string> = {
    early: 'Đầu kỳ',
    mid: 'Giữa kỳ',
    late: 'Cuối kỳ',
};

const setPhase = (key: CockpitPhase['key']): void => {
    router.post(financeRoutes.cockpit.phase(), { phase: key }, { preserveScroll: true });
};
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-1">
            <CardTitle class="text-sm">Theo giai đoạn <Badge variant="secondary">{{ phase.label }}</Badge></CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
            <Button v-for="s in shortcuts[props.phase.key]" :key="s.label" as-child size="sm" variant="outline" class="w-full justify-start">
                <Link :href="s.href">{{ s.label }}</Link>
            </Button>
            <div class="flex gap-1 pt-1">
                <Button
                    v-for="k in (['early', 'mid', 'late'] as const)"
                    :key="k"
                    size="sm"
                    :variant="phase.key === k ? 'default' : 'ghost'"
                    @click="setPhase(k)"
                >
                    {{ phaseLabels[k] }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>