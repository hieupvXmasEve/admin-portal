# Component Templates

Copy-paste ready Vue 3 filter components.

## FilterPanel.vue

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { X } from 'lucide-vue-next';

withDefaults(defineProps<{ hasActiveFilters?: boolean; columns?: 2 | 3 | 4 | 5 | 6 }>(), {
    hasActiveFilters: false, columns: 4
});
const emit = defineEmits<{ clear: [] }>();
</script>

<template>
    <div class="grid gap-3" :class="{
        'grid-cols-1 md:grid-cols-2': columns === 2,
        'grid-cols-1 md:grid-cols-3': columns === 3,
        'grid-cols-1 md:grid-cols-4': columns === 4,
        'grid-cols-1 md:grid-cols-5': columns === 5,
        'grid-cols-1 md:grid-cols-6': columns === 6,
    }">
        <slot />
        <Button v-if="hasActiveFilters" variant="ghost" @click="emit('clear')">
            <X class="mr-2 h-4 w-4" /> Clear
        </Button>
    </div>
</template>
```

## FilterSearchInput.vue

```vue
<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    modelValue: string; placeholder?: string; debounce?: number;
}>(), { placeholder: 'Search...', debounce: 300 });

const emit = defineEmits<{ 'update:modelValue': [string]; search: [string] }>();
const localValue = ref(props.modelValue);

watch(() => props.modelValue, (v) => { localValue.value = v; });

const debouncedSearch = useDebounceFn((val: string) => emit('search', val), props.debounce);

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
```

## FilterDateRange.vue

```vue
<script setup lang="ts">
import DatePicker from '@/components/ui/DatePicker.vue';

const props = withDefaults(defineProps<{
    fromValue: string; toValue: string;
    fromPlaceholder?: string; toPlaceholder?: string;
}>(), { fromPlaceholder: 'From date', toPlaceholder: 'To date' });

const emit = defineEmits<{
    'update:fromValue': [string]; 'update:toValue': [string];
    change: [from: string, to: string];
}>();

const updateFrom = (val: string) => { emit('update:fromValue', val); emit('change', val, props.toValue); };
const updateTo = (val: string) => { emit('update:toValue', val); emit('change', props.fromValue, val); };
</script>

<template>
    <DatePicker :model-value="fromValue" :placeholder="fromPlaceholder" @update:model-value="updateFrom" />
    <DatePicker :model-value="toValue" :placeholder="toPlaceholder" @update:model-value="updateTo" />
</template>
```

## FilterSelect.vue

```vue
<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const props = withDefaults(defineProps<{
    modelValue: string; options: { value: string; label: string }[];
    placeholder?: string; allLabel?: string;
}>(), { placeholder: 'Select...', allLabel: 'All' });

const emit = defineEmits<{ 'update:modelValue': [string]; change: [string] }>();
const onChange = (val: string) => { emit('update:modelValue', val); emit('change', val); };
</script>

<template>
    <Select :model-value="modelValue" @update:model-value="onChange">
        <SelectTrigger><SelectValue :placeholder="placeholder" /></SelectTrigger>
        <SelectContent>
            <SelectItem value="all">{{ allLabel }}</SelectItem>
            <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
        </SelectContent>
    </Select>
</template>
```
