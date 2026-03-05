<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { AcceptableValue } from 'reka-ui';

interface Option {
    value: string;
    label: string;
}

interface FilterSelectProps {
    modelValue: string;
    options: Option[];
    placeholder?: string;
    allLabel?: string;
}

const props = withDefaults(defineProps<FilterSelectProps>(), {
    placeholder: 'Select...',
    allLabel: 'All',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    change: [value: string];
}>();

const onChange = (val: AcceptableValue) => {
    const strVal = String(val ?? '');
    const actualValue = strVal === '__all__' ? '' : strVal;
    emit('update:modelValue', actualValue);
    emit('change', actualValue);
};
</script>

<template>
    <Select :model-value="modelValue || '__all__'" @update:model-value="onChange">
        <SelectTrigger>
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="__all__">{{ allLabel }}</SelectItem>
            <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
        </SelectContent>
    </Select>
</template>
