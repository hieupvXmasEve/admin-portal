<script setup lang="ts">
import type { CockpitDataHealth } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Link } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ dataHealth?: CockpitDataHealth }>();

const criticalCount = computed(() => props.dataHealth?.critical_count ?? 0);
const criticalInvariants = computed(() => props.dataHealth?.invariants.filter((inv) => inv.severity === 'CRITICAL' && !inv.error && (inv.count ?? 0) > 0) ?? []);
const firstCriticalInvariant = computed(() => criticalInvariants.value[0] ?? null);
const criticalSummary = computed(() => criticalInvariants.value.map((inv) => `${inv.code}: ${inv.count ?? 0} mẫu`).join(' · '));

const detailsHref = computed(() => {
    if (!props.dataHealth || !firstCriticalInvariant.value) {
        return financeRoutes.audit();
    }

    const params = new URLSearchParams({
        finding_code: firstCriticalInvariant.value.code,
        scope: 'campus',
    });

    return `${financeRoutes.audit()}?${params.toString()}`;
});
</script>

<template>
    <div v-if="criticalCount > 0" class="flex items-center justify-between gap-3 rounded-md border border-red-300 bg-red-50 p-3 dark:bg-red-950">
        <div class="min-w-0 space-y-1 text-red-800 dark:text-red-200">
            <div class="flex items-center gap-2">
                <ShieldAlert class="size-5 shrink-0" />
                <span class="font-medium">{{ criticalCount }} vi phạm toàn vẹn tiền nghiêm trọng cần xử lý ngay.</span>
            </div>
            <p v-if="criticalSummary" class="text-xs text-red-700 dark:text-red-200">{{ criticalSummary }}</p>
        </div>
        <Link :href="detailsHref" class="shrink-0 text-sm font-medium text-red-700 underline dark:text-red-300">Xem chi tiết</Link>
    </div>
</template>
