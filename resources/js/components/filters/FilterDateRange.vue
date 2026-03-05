<script setup lang="ts">
import DatePicker from '@/components/ui/DatePicker.vue';

interface FilterDateRangeProps {
    fromValue: string;
    toValue: string;
    fromPlaceholder?: string;
    toPlaceholder?: string;
}

const props = withDefaults(defineProps<FilterDateRangeProps>(), {
    fromPlaceholder: 'From date',
    toPlaceholder: 'To date',
});

const emit = defineEmits<{
    'update:fromValue': [value: string];
    'update:toValue': [value: string];
    change: [from: string, to: string];
}>();

const updateFrom = (val: string) => {
    emit('update:fromValue', val);
    emit('change', val, props.toValue);
};

const updateTo = (val: string) => {
    emit('update:toValue', val);
    emit('change', props.fromValue, val);
};
</script>

<template>
    <DatePicker :model-value="fromValue" :placeholder="fromPlaceholder" @update:model-value="updateFrom" />
    <DatePicker :model-value="toValue" :placeholder="toPlaceholder" @update:model-value="updateTo" />
</template>
