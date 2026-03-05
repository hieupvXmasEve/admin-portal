<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';

interface FilterSearchInputProps {
    modelValue: string;
    placeholder?: string;
    debounce?: number;
}

const props = withDefaults(defineProps<FilterSearchInputProps>(), {
    placeholder: 'Search...',
    debounce: 300,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    search: [value: string];
}>();

const localValue = ref(props.modelValue);

watch(
    () => props.modelValue,
    (v) => {
        localValue.value = v;
    },
);

const debouncedSearch = useDebounceFn((val: string) => {
    emit('search', val);
}, props.debounce);

const onInput = (val: string | number) => {
    const str = String(val ?? '');
    localValue.value = str;
    emit('update:modelValue', str);
    debouncedSearch(str);
};
</script>

<template>
    <Input :model-value="localValue" :placeholder="placeholder" @update:model-value="onInput" />
</template>
