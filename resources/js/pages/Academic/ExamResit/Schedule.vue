<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarClock, DoorOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface SessionOption {
    id: number;
    expected_candidates: number;
    actual_candidates: number;
    remaining: number;
    exam_date: string | null;
    start_time: string | null;
    end_time: string | null;
    room: { id: number; name: string; code: string } | null;
}

interface Props {
    attempt: {
        id: number;
        student: { id: number; full_name: string; student_id: string };
        unit: { id: number; code: string; name: string };
        hq_fee_status: string;
        allow_unpaid_sitting: boolean;
    };
    sessions: SessionOption[];
}

const props = defineProps<Props>();

const isPaid = computed(() => props.attempt.hq_fee_status === 'paid');
const needsUnpaidReason = computed(() => !isPaid.value && props.attempt.allow_unpaid_sitting);

const form = useForm({
    exam_resit_session_id: null as number | null,
    unpaid_sitting_reason: '',
    notes: '',
});

const formatDate = (value: string | null): string => (value ? new Date(value).toLocaleDateString('vi-VN') : '—');

const submit = () => {
    form.post(route('academic.exam-resit.schedule.store', props.attempt.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Xếp lịch thi lại" />
    <div class="mb-6 flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="router.visit(route('academic.exam-resit.index'))">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Xếp lịch thi lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ attempt.student.full_name }} ({{ attempt.student.student_id }}) — {{ attempt.unit.code }}</p>
        </div>
    </div>

    <Card class="max-w-3xl">
        <CardHeader>
            <CardTitle>Chọn ca thi</CardTitle>
            <CardDescription>Chỉ hiển thị các ca thi cùng môn, cùng cơ sở và còn trạng thái xếp lịch.</CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="sessions.length === 0" class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm">
                Chưa có ca thi phù hợp. Hãy tạo ca phòng thi và ca thi cho môn này trong
                <Button variant="link" class="px-1" @click="router.visit(route('academic.exam-schedule.index'))">Lịch thi lại</Button>
                trước.
            </div>
            <form v-else @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <label
                        v-for="session in sessions"
                        :key="session.id"
                        class="hover:bg-muted/50 flex cursor-pointer items-center justify-between rounded-lg border p-3 transition-colors"
                        :class="{ 'border-primary bg-primary/5 ring-1 ring-primary/30': form.exam_resit_session_id === session.id }"
                    >
                        <span class="flex items-center gap-3">
                            <input type="radio" :value="session.id" v-model="form.exam_resit_session_id" class="accent-primary" />
                            <span>
                                <span class="flex items-center gap-2 text-sm font-medium">
                                    <CalendarClock class="h-4 w-4" />
                                    {{ formatDate(session.exam_date) }} · {{ session.start_time }}–{{ session.end_time }}
                                </span>
                                <span v-if="session.room" class="text-muted-foreground mt-0.5 flex items-center gap-1 text-xs"> <DoorOpen class="h-3 w-3" /> {{ session.room.name }} </span>
                            </span>
                        </span>
                        <Badge :variant="session.remaining > 0 ? 'success' : 'destructive'">Còn {{ session.remaining }} chỗ</Badge>
                    </label>
                </div>
                <p v-if="form.errors.exam_resit_session_id" class="text-destructive text-xs">{{ form.errors.exam_resit_session_id }}</p>

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
                    <Button type="submit" :disabled="form.processing || !form.exam_resit_session_id">
                        {{ form.processing ? 'Đang xử lý...' : 'Xếp lịch' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
