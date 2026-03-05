<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { X } from 'lucide-vue-next';

interface FilterPanelProps {
    hasActiveFilters?: boolean;
    columns?: 2 | 3 | 4 | 5 | 6;
}

withDefaults(defineProps<FilterPanelProps>(), {
    hasActiveFilters: false,
    columns: 4,
});

const emit = defineEmits<{
    clear: [];
}>();
</script>

<template>
    <div
        class="grid gap-3"
        :class="{
            'grid-cols-1 md:grid-cols-2': columns === 2,
            'grid-cols-1 md:grid-cols-3': columns === 3,
            'grid-cols-1 md:grid-cols-4': columns === 4,
            'grid-cols-1 md:grid-cols-5': columns === 5,
            'grid-cols-1 md:grid-cols-6': columns === 6,
        }"
    >
        <slot />
        <Button v-if="hasActiveFilters" variant="ghost" @click="emit('clear')">
            <X class="mr-2 h-4 w-4" />
            Clear
        </Button>
    </div>
</template>
