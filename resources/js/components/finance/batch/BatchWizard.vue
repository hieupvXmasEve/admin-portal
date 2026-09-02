<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    summaryText?: string;
    nextLabel?: string;
    nextDisabled?: boolean;
    canBack?: boolean;
}>();

const emit = defineEmits<{ (e: 'next'): void; (e: 'back'): void }>();
</script>

<template>
    <div class="space-y-5">
        <Card class="overflow-hidden">
            <CardContent class="min-h-96 p-5 sm:p-6">
                <slot />
            </CardContent>
        </Card>

        <div class="bg-background/95 sticky bottom-0 z-10 -mx-1 flex flex-col gap-3 border-t px-1 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
            <p class="text-muted-foreground text-sm leading-relaxed tabular-nums">{{ summaryText }}</p>
            <div class="flex shrink-0 gap-2">
                <Button v-if="canBack" variant="outline" @click="emit('back')">Quay lại</Button>
                <Button :disabled="nextDisabled" @click="emit('next')">{{ nextLabel ?? 'Tiếp' }}</Button>
            </div>
        </div>
    </div>
</template>
