<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Check } from 'lucide-vue-next';

defineProps<{
    step: 1 | 2 | 3 | 4;
    summaryText?: string;
    nextLabel?: string;
    nextDisabled?: boolean;
    canBack?: boolean;
}>();

const emit = defineEmits<{ (e: 'next'): void; (e: 'back'): void }>();

const steps = [
    { id: 1, label: 'Thiết lập' },
    { id: 2, label: 'Xem trước' },
    { id: 3, label: 'Xác nhận' },
    { id: 4, label: 'Kết quả' },
] as const;
</script>

<template>
    <div class="space-y-5">
        <nav aria-label="Tiến trình Batch Studio">
            <ol class="flex flex-wrap items-center gap-2">
                <li v-for="(item, index) in steps" :key="item.id" class="flex items-center gap-2">
                    <div
                        class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                        :class="
                            step === item.id
                                ? 'border-primary bg-primary/5 font-semibold text-foreground'
                                : step > item.id
                                  ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                  : 'border-border text-muted-foreground'
                        "
                    >
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            :class="
                                step > item.id
                                    ? 'bg-emerald-600 text-white'
                                    : step === item.id
                                      ? 'bg-primary text-primary-foreground'
                                      : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Check v-if="step > item.id" class="h-3.5 w-3.5" />
                            <span v-else>{{ item.id }}</span>
                        </span>
                        <span>{{ item.label }}</span>
                    </div>
                    <span v-if="index < steps.length - 1" class="text-muted-foreground hidden px-1 sm:inline">→</span>
                </li>
            </ol>
        </nav>

        <Card class="overflow-hidden">
            <CardContent class="min-h-96 p-5 sm:p-6">
                <slot :step="step" />
            </CardContent>
        </Card>

        <div
            class="bg-background/95 sticky bottom-0 z-10 -mx-1 flex flex-col gap-3 border-t px-1 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-muted-foreground text-sm leading-relaxed tabular-nums">{{ summaryText }}</p>
            <div class="flex shrink-0 gap-2">
                <Button v-if="canBack" variant="outline" @click="emit('back')">Quay lại</Button>
                <Button v-if="step < 4" :disabled="nextDisabled" @click="emit('next')">{{ nextLabel ?? 'Tiếp' }}</Button>
            </div>
        </div>
    </div>
</template>