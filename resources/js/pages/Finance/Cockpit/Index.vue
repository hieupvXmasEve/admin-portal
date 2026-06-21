<script setup lang="ts">
import StatsCard from '@/components/StatsCard.vue';
import ActionPanel from '@/components/finance/cockpit/ActionPanel.vue';
import CriticalBanner from '@/components/finance/cockpit/CriticalBanner.vue';
import DataHealthPanel from '@/components/finance/cockpit/DataHealthPanel.vue';
import PhaseShortcuts from '@/components/finance/cockpit/PhaseShortcuts.vue';
import QueueCard from '@/components/finance/cockpit/QueueCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CockpitDataHealth, CockpitKpi, CockpitPhase, CockpitQueue } from '@/types/finance';
import { formatCurrency } from '@/types/finance';
import { Head, router, usePoll } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    kpi: CockpitKpi;
    queues: CockpitQueue[];
    phase: CockpitPhase;
    data_health?: CockpitDataHealth;
}>();

usePoll(90000, { only: ['kpi', 'queues', 'phase', 'data_health'] });

const { can } = usePermissions();
const visibleQueues = computed(() => props.queues.filter((q) => can(q.permission)));

const refreshing = ref(false);
const refreshAll = (): void => {
    refreshing.value = true;
    router.reload({ only: ['kpi', 'queues', 'phase', 'data_health'], onFinish: () => (refreshing.value = false) });
};

const activeQueue = ref<CockpitQueue | null>(null);
const openQueue = (q: CockpitQueue): void => {
    activeQueue.value = q;
};

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Finance · Hôm nay" />
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold tracking-tight">Hôm nay</h1>
            <Button size="sm" variant="outline" :disabled="refreshing" @click="refreshAll"> <RefreshCw class="mr-1 size-4" :class="{ 'animate-spin': refreshing }" /> Làm mới </Button>
        </div>

        <CriticalBanner :data-health="props.data_health" />

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <StatsCard title="Tổng phải thu" :value="formatCurrency(props.kpi.total_receivable)" />
            <StatsCard title="Đã thu" :value="`${props.kpi.collected_pct}%`" :sub-items="[{ label: 'Số tiền', value: formatCurrency(props.kpi.total_collected) }]" />
            <StatsCard title="SV chưa sinh phí" :value="props.kpi.uncharged_count" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <section class="space-y-3 lg:col-span-2">
                <h2 class="text-muted-foreground text-sm font-semibold uppercase">Cần xử lý</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <QueueCard v-for="q in visibleQueues" :key="q.key" :queue="q" @open="openQueue" />
                </div>
            </section>
            <aside class="space-y-3">
                <DataHealthPanel :data-health="props.data_health" />
                <PhaseShortcuts :phase="props.phase" />
            </aside>
        </div>

        <ActionPanel :queue="activeQueue" @close="activeQueue = null" />
    </div>
</template>
