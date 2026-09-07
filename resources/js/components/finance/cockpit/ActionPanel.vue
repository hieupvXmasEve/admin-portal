<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApiRequest';
import type { CockpitQueue, CockpitQueueRow } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Link, router } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ queue: CockpitQueue | null }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const open = computed({
    get: () => props.queue !== null,
    set: (v) => {
        if (!v) emit('close');
    },
});
const rows = ref<CockpitQueueRow[]>([]);
const loading = ref(false);
const { get } = useApi();

watch(
    () => props.queue,
    async (queue) => {
        if (!queue) return;

        const hasPanelRows = ['webhook_errors', 'installment_failures', 'settlement_exceptions', 'unallocated', 'dng_due', 'lifecycle', 'cancellations', 'unresolved_surplus'].includes(queue.key);
        if (!hasPanelRows) {
            router.visit(queue.action_url);
            emit('close');
            return;
        }
        loading.value = true;
        try {
            const res = await get<{ queue: string; rows: CockpitQueueRow[] }>(financeRoutes.cockpit.queueRows(queue.key));
            const payload = res.data?.value;
            rows.value = payload?.success && payload.data ? payload.data.rows : [];
        } finally {
            loading.value = false;
        }
    },
);

const runAction = (row: CockpitQueueRow): void => {
    if (row.primary_action.kind.startsWith('retry_')) {
        router.post(
            row.primary_action.url,
            {},
            {
                preserveScroll: true,
                only: ['kpi', 'queues'],
                onSuccess: () => {
                    rows.value = rows.value.filter((r) => r.id !== row.id);
                },
            },
        );
        return;
    }

    router.visit(row.primary_action.url);
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto p-4 sm:max-w-lg">
            <SheetHeader
                ><SheetTitle>{{ queue?.label }}</SheetTitle></SheetHeader
            >
            <Skeleton v-if="loading" class="mt-4 h-32 w-full" />
            <div v-else class="mt-4 space-y-2">
                <div v-for="row in rows" :key="row.id" class="rounded-md border p-2 text-sm">
                    <p class="font-medium">{{ row.title }}</p>
                    <p class="text-muted-foreground text-xs">{{ row.subtitle }} · {{ row.age }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <Button size="sm" variant="outline" @click="runAction(row)">{{ row.primary_action.label }}</Button>
                        <Button v-if="row.student_id" as-child size="sm" variant="ghost">
                            <Link :href="financeRoutes.students.overview(row.student_id)"> <ExternalLink class="mr-1 size-4" /> Mở hồ sơ SV </Link>
                        </Button>
                    </div>
                </div>
                <p v-if="rows.length === 0" class="text-muted-foreground text-sm">Hàng đợi trống.</p>
            </div>
        </SheetContent>
    </Sheet>
</template>
