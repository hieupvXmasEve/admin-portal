<script setup lang="ts">
import { Button } from '@/components/ui/button';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { X } from 'lucide-vue-next';

interface DateRangeFilterModel {
    search: string;
    issued_from: string;
    issued_to: string;
}

interface ServerDateRangeFiltersProps {
    modelValue: DateRangeFilterModel;
    hasActiveFilters?: boolean;
    searchPlaceholder?: string;
}

const props = withDefaults(defineProps<ServerDateRangeFiltersProps>(), {
    hasActiveFilters: false,
    searchPlaceholder: 'Search...',
});

const emit = defineEmits<{
    'update:modelValue': [value: DateRangeFilterModel];
    search: [value: string];
    'date-change': [value: Pick<DateRangeFilterModel, 'issued_from' | 'issued_to'>];
    clear: [];
}>();

const updateField = (key: keyof DateRangeFilterModel, value: string) => {
    const nextValue = {
        ...props.modelValue,
        [key]: value,
    };

    emit('update:modelValue', {
        ...nextValue,
    });

    if (key === 'search') {
        emit('search', value);
        return;
    }

    if (key === 'issued_from' || key === 'issued_to') {
        emit('date-change', {
            issued_from: nextValue.issued_from,
            issued_to: nextValue.issued_to,
        });
    }
};

const handleClear = () => {
    emit('update:modelValue', {
        search: '',
        issued_from: '',
        issued_to: '',
    });
    emit('clear');
};
</script>

<template>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <Input :model-value="modelValue.search" :placeholder="searchPlaceholder" @update:model-value="(value) => updateField('search', String(value ?? ''))" />
        <DatePicker :model-value="modelValue.issued_from" placeholder="Issued from" @update:model-value="(value) => updateField('issued_from', String(value ?? ''))" />
        <DatePicker :model-value="modelValue.issued_to" placeholder="Issued to" @update:model-value="(value) => updateField('issued_to', String(value ?? ''))" />
        <Button v-if="hasActiveFilters" variant="ghost" @click="handleClear">
            <X class="mr-2 h-4 w-4" />
            Clear
        </Button>
    </div>
</template>
