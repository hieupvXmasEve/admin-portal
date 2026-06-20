<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    attempt: {
        id: number;
        student: { id: number; full_name: string; student_id: string };
        unit: { id: number; code: string; name: string };
        hq_fee_status: string;
        allow_unpaid_sitting: boolean;
        current_final_percentage: string | null;
        current_letter_grade: string | null;
    };
}

const props = defineProps<Props>();

const isPaid = computed(() => props.attempt.hq_fee_status === 'paid');
const needsUnpaidReason = computed(() => !isPaid.value && props.attempt.allow_unpaid_sitting);

const form = useForm({
    resit_score: null as number | null,
    resit_grade: '',
    sat_at: '',
    unpaid_sitting_reason: '',
    notes: '',
});

const currentScore = computed(() => (props.attempt.current_final_percentage !== null ? Number(props.attempt.current_final_percentage).toFixed(1) : '—'));

const higherScoreHint = computed(() => {
    if (form.resit_score === null || props.attempt.current_final_percentage === null) return null;
    const current = Number(props.attempt.current_final_percentage);
    return form.resit_score > current ? `Điểm thi lại (${form.resit_score}) cao hơn — sẽ cập nhật điểm chốt.` : `Điểm thi lại (${form.resit_score}) không cao hơn (${current.toFixed(1)}) — giữ điểm cũ nhưng vẫn ghi nhận lượt thi.`;
});

const submit = () => {
    form.post(route('academic.exam-resit.complete.store', props.attempt.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Nhập kết quả thi lại" />
    <div class="mb-6 flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="router.visit(route('academic.exam-resit.index'))">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Nhập kết quả thi lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ attempt.student.full_name }} ({{ attempt.student.student_id }}) — {{ attempt.unit.code }}</p>
        </div>
    </div>

    <Card class="max-w-2xl">
        <CardHeader>
            <CardTitle>Kết quả</CardTitle>
            <CardDescription>Áp dụng quy tắc điểm cao hơn; điểm cũ được giữ trong lịch sử.</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="bg-muted/40 mb-4 flex items-center gap-2 rounded-md border p-3 text-sm">
                <TrendingUp class="text-muted-foreground h-4 w-4" />
                <span class="text-muted-foreground">Điểm hiện tại:</span>
                <span class="font-mono font-medium">{{ currentScore }}</span>
                <span v-if="attempt.current_letter_grade" class="text-muted-foreground">({{ attempt.current_letter_grade }})</span>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <Label>Điểm thi lại (0–100) <span class="text-destructive">*</span></Label>
                        <Input v-model.number="form.resit_score" type="number" min="0" max="100" step="0.1" placeholder="VD: 75" />
                        <p v-if="form.errors.resit_score" class="text-destructive text-xs">{{ form.errors.resit_score }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Điểm chữ (không bắt buộc)</Label>
                        <Input v-model="form.resit_grade" placeholder="VD: C" />
                    </div>
                </div>

                <p v-if="higherScoreHint" class="text-muted-foreground text-xs">{{ higherScoreHint }}</p>

                <div class="space-y-1.5">
                    <Label>Thời điểm thi (không bắt buộc)</Label>
                    <DatePicker v-model="form.sat_at" placeholder="Chọn ngày thi" />
                </div>

                <div v-if="needsUnpaidReason" class="space-y-1.5">
                    <Label>Lý do cho thi trước khi thu phí <span class="text-destructive">*</span></Label>
                    <Textarea v-model="form.unpaid_sitting_reason" rows="2" placeholder="Bắt buộc khi sinh viên chưa thanh toán..." />
                    <p v-if="form.errors.unpaid_sitting_reason" class="text-destructive text-xs">{{ form.errors.unpaid_sitting_reason }}</p>
                </div>

                <div class="space-y-1.5">
                    <Label>Ghi chú</Label>
                    <Textarea v-model="form.notes" rows="2" placeholder="Ghi chú thêm (không bắt buộc)" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing || form.resit_score === null">
                        {{ form.processing ? 'Đang xử lý...' : 'Lưu kết quả' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
