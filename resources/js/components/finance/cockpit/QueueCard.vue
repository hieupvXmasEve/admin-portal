<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CockpitQueue } from '@/types/finance';

defineProps<{ queue: CockpitQueue }>();
const emit = defineEmits<{ (e: 'open', queue: CockpitQueue): void }>();

const dotClass: Record<string, string> = {
    critical: 'bg-red-500',
    action: 'bg-orange-500',
    normal: 'bg-slate-300',
};
const badgeLabel: Record<string, string> = { campus: 'Campus hiện tại', semester: 'Theo kỳ', multi: 'Đa kỳ' };
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-1">
            <CardTitle class="flex items-center gap-2 text-sm">
                <span class="size-2 rounded-full" :class="dotClass[queue.severity]" />
                {{ queue.label }}
            </CardTitle>
            <Badge variant="outline" class="text-xs">{{ badgeLabel[queue.scope_badge] }}</Badge>
        </CardHeader>
        <CardContent class="flex items-center justify-between">
            <span class="text-2xl font-bold tabular-nums">{{ queue.count }}</span>
            <Button size="sm" variant="outline" @click="emit('open', queue)">Xử lý</Button>
        </CardContent>
    </Card>
</template>
