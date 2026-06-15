<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ status: string }>();

const steps = [
    { key: 'pushed_to_dng', label: 'Đẩy DNG' },
    { key: 'paid_uninvoiced', label: 'Đã trả (chưa h.đơn)' },
    { key: 'paid_invoiced', label: 'Đã trả (có h.đơn)' },
    { key: 'reconciled', label: 'Đối soát' },
] as const;

const order: Record<string, number> = {
    pending: 0,
    pushed_to_dng: 1,
    paid_uninvoiced: 2,
    paid_invoiced: 3,
    reconciled: 4,
};

const currentRank = computed(() => order[props.status] ?? 0);
const isTerminalCancel = computed(() => props.status === 'cancelled' || props.status === 'cancel_pushed_to_dng');
const isFailed = computed(() => props.status === 'failed');

const stepState = (stepKey: string): 'done' | 'current' | 'todo' => {
    const rank = order[stepKey] ?? 0;
    if (rank < currentRank.value) {
        return 'done';
    }
    if (rank === currentRank.value) {
        return 'current';
    }

    return 'todo';
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-1 text-xs" :class="{ 'opacity-60': isTerminalCancel }">
        <template v-for="(step, idx) in steps" :key="step.key">
            <span
                class="rounded px-2 py-1"
                :class="{
                    'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200': stepState(step.key) === 'done',
                    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200': stepState(step.key) === 'current' && !isFailed,
                    'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200': stepState(step.key) === 'current' && isFailed,
                    'bg-muted text-muted-foreground': stepState(step.key) === 'todo',
                }"
            >
                {{ step.label }}
            </span>
            <span v-if="idx < steps.length - 1" class="text-muted-foreground">─►</span>
        </template>
        <span
            v-if="isTerminalCancel"
            class="ml-2 rounded bg-slate-200 px-2 py-1 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
        >
            Đã hủy
        </span>
    </div>
</template>