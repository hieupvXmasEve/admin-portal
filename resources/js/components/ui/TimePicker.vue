<script setup lang="ts">
import { computed } from 'vue';
import { TimeFieldInput, TimeFieldRoot } from 'reka-ui';
import { parseTime } from '@internationalized/date';
import type { TimeValue } from 'reka-ui';
import { cn } from '@/lib/utils';

interface Props {
    modelValue?: string; // HH:mm (24-hour)
    disabled?: boolean;
    granularity?: 'hour' | 'minute' | 'second';
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    granularity: 'minute',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const timeValue = computed<TimeValue | undefined>(() => {
    if (!props.modelValue) return undefined;
    try {
        return parseTime(props.modelValue);
    } catch {
        return undefined;
    }
});

function onUpdate(val: TimeValue | undefined) {
    if (!val) {
        emit('update:modelValue', '');
        return;
    }
    const hh = String(val.hour).padStart(2, '0');
    const mm = String(val.minute).padStart(2, '0');
    emit('update:modelValue', `${hh}:${mm}`);
}
</script>

<template>
    <TimeFieldRoot
        :model-value="timeValue"
        :granularity="granularity"
        :disabled="disabled"
        :hide-time-zone="true"
        :hour-cycle="24"
        :class="cn(
            'border-input bg-background ring-offset-background focus-within:ring-ring flex h-9 w-full items-center rounded-md border px-3 py-1 text-sm shadow-sm focus-within:ring-1 focus-within:outline-none',
            disabled && 'cursor-not-allowed opacity-50',
            props.class,
        )"
        @update:model-value="onUpdate"
    >
        <template #default="{ segments }">
            <template v-for="seg in segments" :key="seg.part">
                <TimeFieldInput
                    :part="seg.part"
                    :class="cn(
                        'rounded p-0.5 text-center tabular-nums caret-transparent focus:bg-accent focus:text-accent-foreground focus:outline-none',
                        seg.part === 'literal' && 'text-muted-foreground select-none px-0',
                    )"
                >
                    {{ seg.value }}
                </TimeFieldInput>
            </template>
        </template>
    </TimeFieldRoot>
</template>
