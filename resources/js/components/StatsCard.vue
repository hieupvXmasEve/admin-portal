<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { computed } from 'vue';

interface Props {
    title: string;
    value: number | string;
    subItems?: { label: string; value: number | string }[];
}

const props = defineProps<Props>();

const hasSub = computed(() => (props.subItems?.length ?? 0) > 0);
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="text-muted-foreground text-sm">{{ title }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="text-3xl font-bold">{{ value }}</div>
            <div v-if="hasSub" class="text-muted-foreground mt-3 grid grid-cols-2 gap-2 text-sm">
                <div v-for="(item, idx) in subItems" :key="idx" class="flex items-center justify-between">
                    <span>{{ item.label }}</span>
                    <span class="text-foreground font-medium">{{ item.value }}</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
