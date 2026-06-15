<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BatchResult } from '@/types/finance';
import { AlertTriangle, CheckCircle2, MinusCircle, RotateCcw, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ result: BatchResult }>();
const emit = defineEmits<{ (e: 'retryFailed'): void; (e: 'restart'): void }>();

const s = computed(() => props.result.summary as Record<string, number | string[]>);
const errors = computed(() => (props.result.summary.errors as string[] | undefined) ?? []);
const canRetry = computed(() => props.result.job !== 'charge_generation' && errors.value.length > 0);

const successCount = computed(() => Number(s.value.created ?? s.value.created_count ?? s.value.sent_count ?? 0));
const skipCount = computed(() =>
    Number(s.value.skipped ?? s.value.skipped_count ?? s.value.skipped_no_debt_count ?? 0),
);
const failedCount = computed(() => Number(s.value.failed ?? s.value.failed_count ?? 0));
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <Card class="border-emerald-200/80 bg-emerald-50/30">
                <CardContent class="flex items-center gap-4 pt-6">
                    <CheckCircle2 class="h-8 w-8 text-emerald-600" />
                    <div>
                        <p class="text-2xl font-semibold text-emerald-700 tabular-nums">{{ successCount }}</p>
                        <p class="text-muted-foreground text-sm">Thành công</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 pt-6">
                    <MinusCircle class="text-muted-foreground h-8 w-8" />
                    <div>
                        <p class="text-2xl font-semibold tabular-nums">{{ skipCount }}</p>
                        <p class="text-muted-foreground text-sm">Bỏ qua</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="border-red-200/80 bg-red-50/30">
                <CardContent class="flex items-center gap-4 pt-6">
                    <XCircle class="h-8 w-8 text-red-600" />
                    <div>
                        <p class="text-2xl font-semibold text-red-700 tabular-nums">{{ failedCount }}</p>
                        <p class="text-muted-foreground text-sm">Lỗi</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card
            v-if="result.job === 'dng_push' && Number(s.cancelled_old) > 0"
            class="border-amber-200 bg-amber-50/50"
        >
            <CardContent class="flex items-start gap-3 pt-6 text-sm text-amber-900">
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                <p>Đã hủy {{ s.cancelled_old }} DNG cũ (cùng SV + loại phí) trước khi tạo mới.</p>
            </CardContent>
        </Card>

        <Card v-if="errors.length" class="border-red-200">
            <CardHeader class="pb-2">
                <CardTitle class="text-base text-red-700">Các lỗi từng sinh viên</CardTitle>
            </CardHeader>
            <CardContent>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="(err, i) in errors"
                        :key="i"
                        class="text-muted-foreground border-b border-dashed pb-2 last:border-0 last:pb-0"
                    >
                        {{ err }}
                    </li>
                </ul>
            </CardContent>
        </Card>

        <div class="flex flex-wrap gap-2">
            <Button v-if="canRetry" variant="outline" @click="emit('retryFailed')">
                <RotateCcw class="mr-2 h-4 w-4" />
                Thử lại các dòng lỗi
            </Button>
            <Button variant="secondary" @click="emit('restart')">Chạy lô mới</Button>
        </div>
    </div>
</template>