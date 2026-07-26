<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { GRADING_SCHEME_EXAMPLES, type GradingSchemeExample } from './grading-scheme-examples';

defineProps<{ open: boolean }>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    apply: [example: GradingSchemeExample];
}>();

function applyExample(example: GradingSchemeExample): void {
    // Deep clone so the builder owns an independent copy.
    emit('apply', JSON.parse(JSON.stringify(example)) as GradingSchemeExample);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogScrollContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Hướng dẫn cấu hình chấm điểm Metropolia</DialogTitle>
                <DialogDescription>Chọn loại kết quả và cách tính, rồi điền các thành phần. Có thể nạp một ví dụ bên dưới để bắt đầu nhanh.</DialogDescription>
            </DialogHeader>

            <div class="space-y-6 text-sm">
                <section class="space-y-2">
                    <h3 class="font-semibold">1. Loại kết quả</h3>
                    <ul class="text-muted-foreground list-inside list-disc space-y-1">
                        <li><strong>Điểm số (0–5):</strong> môn cho điểm 0–5.</li>
                        <li><strong>Đạt / Không đạt:</strong> môn chỉ kết luận P/F (ví dụ tổng bài tập ≥ 80%).</li>
                    </ul>
                </section>

                <section class="space-y-2">
                    <h3 class="font-semibold">2. Cách tính — chọn đúng theo hình dạng quy tắc</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-md border p-3">
                            <p class="font-medium">Cộng điểm quy đổi từng phần</p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Quy đổi mỗi phần thành điểm rồi cộng. Dùng khi có <strong>bậc thang</strong> (55% → 1, 70% → 2) hoặc <strong>tuyến tính có chặn</strong> (40% → 1 … 88% → 5), và FG = điểm phần A + điểm phần B.
                            </p>
                        </div>
                        <div class="rounded-md border p-3">
                            <p class="font-medium">Dùng công thức tổng</p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Một biểu thức số học (chỉ + − × ÷ và ngoặc) trên % thô. Dùng khi điểm cuối trộn các phần với offset/hệ số, ví dụ <span class="font-mono">(LAB + QUIZ + EXAM/2 − 40)/10</span>.
                            </p>
                        </div>
                    </div>
                    <p class="text-muted-foreground text-xs">Công thức không có if/min/max nên không làm được bậc thang — khi đó hãy dùng “Cộng điểm quy đổi”.</p>
                </section>

                <section class="space-y-2">
                    <h3 class="font-semibold">3. Các cách quy đổi (cho “Cộng điểm quy đổi”)</h3>
                    <ul class="text-muted-foreground list-inside list-disc space-y-1">
                        <li><strong>Tuyến tính:</strong> nội suy từ (từ%, điểm thấp) đến (đến%, điểm cao); dưới ngưỡng = 0.</li>
                        <li><strong>Mốc bậc thang:</strong> đạt mốc % nào thì lấy điểm của mốc cao nhất.</li>
                        <li><strong>Trực tiếp /5:</strong> điểm = % / 100 × điểm tối đa.</li>
                        <li><strong>Đạt/Không đạt:</strong> ≥ % tối thiểu thì đạt (dùng với loại Đạt/Không đạt).</li>
                        <li><strong>Điều kiện (gate):</strong> phần phải đạt % tối thiểu, nếu không thì trượt môn.</li>
                    </ul>
                </section>

                <section class="space-y-3">
                    <h3 class="font-semibold">4. Ví dụ mẫu — bấm để nạp vào form</h3>
                    <div class="space-y-2">
                        <div v-for="example in GRADING_SCHEME_EXAMPLES" :key="example.id" class="flex items-start justify-between gap-3 rounded-md border p-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium">{{ example.title }}</p>
                                    <Badge variant="secondary" class="text-xs">{{ example.engineLabel }}</Badge>
                                </div>
                                <p class="text-muted-foreground text-xs">{{ example.description }}</p>
                            </div>
                            <Button type="button" size="sm" variant="outline" class="shrink-0" @click="applyExample(example)"> Áp dụng ví dụ </Button>
                        </div>
                    </div>
                </section>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
