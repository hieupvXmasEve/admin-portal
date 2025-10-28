<script lang="ts" setup>
import { refDebounced } from '@vueuse/core';
import { watch } from 'vue';
import { Input } from './ui/input';

const props = defineProps<{
    debounce?: number;
}>();

const model = defineModel<string | number>({
    required: true,
});

// emit custom event
const emit = defineEmits<{
    (e: 'debounced', value: string | number): void;
}>();

const debounced = refDebounced(model, props.debounce ?? 300);

// emit khi debounce xong
watch(debounced, (value) => {
    model.value = value;
    emit('debounced', value);
});
</script>

<template>
    <Input v-model="model" v-bind="$attrs" />
</template>
