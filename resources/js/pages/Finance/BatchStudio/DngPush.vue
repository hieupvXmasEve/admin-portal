<script setup lang="ts">
import BatchResultPanel from '@/components/finance/batch/BatchResultPanel.vue';
import BatchWizard from '@/components/finance/batch/BatchWizard.vue';
import PreviewDiffTable from '@/components/finance/batch/PreviewDiffTable.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useBatchStudio } from '@/composables/useBatchStudio';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import type { BatchResult } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Send } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

const props = defineProps<{ dngFeeTypeOptions?: { value: string; label: string }[] }>();

const { selectedId: semesterId, labelFor: semesterLabelFor } = useFinanceSemester();

const amountOverrides = reactive<Record<string, number>>({});

const wizard = useBatchStudio({
    previewUrl: financeRoutes.batchStudio.dngPreview(),
    commitUrl: financeRoutes.batchStudio.dngCommit(),
    defaultSetup: {
        semester_id: null as number | null,
        dng_fee_type: 'tuition',
        student_ids: [] as number[],
        due_date: '',
        description: 'Học phí kỳ',
        estimate_time: '3d',
    },
    commitExtras: () => ({
        due_date: wizard.setup.due_date,
        description: wizard.setup.description,
        estimate_time: wizard.setup.estimate_time,
        amount_overrides: Object.keys(amountOverrides).length ? { ...amountOverrides } : undefined,
    }),
});

const ack = computed({
    get: () => Boolean(wizard.form.acknowledged),
    set: (v: boolean) => {
        wizard.form.acknowledged = v;
    },
});

const needsAck = computed(() => wizard.selected.value.size > 50);
const nextDisabled = computed(() => wizard.step.value === 3 && needsAck.value && !ack.value);

const rerunCancelsCount = computed(
    () => wizard.lines.value.filter((l) => l.display.warning_codes?.includes('rerun_cancels_old_dng')).length,
);

const overrideDriftCount = computed(() => {
    let count = 0;
    for (const line of wizard.lines.value) {
        if (!wizard.selected.value.has(line.key)) continue;
        const studentId = line.key.split(':')[2];
        const override = amountOverrides[studentId];
        if (override === undefined) continue;
        const total = line.display.installment_aware_total ?? line.display.net;
        if (Math.abs(override - total) >= 1) count++;
    }
    return count;
});

const hasOverrideDrift = computed(() => overrideDriftCount.value > 0);

const summaryText = computed(() => {
    if (wizard.step.value === 1) return 'Điền thông tin DNG trước khi xem trước danh sách SV';
    const c = wizard.counts.value;
    return `${wizard.selected.value.size} đã chọn · 🟢 ${c.create} · 🔵 ${c.update} · ⚪ ${c.skip} · 🟠 ${c.warning}`;
});

function onNext() {
    if (wizard.step.value === 1) {
        wizard.setup.semester_id = semesterId.value;
        return void wizard.runPreview();
    }
    if (wizard.step.value === 2) {
        wizard.step.value = 3;
        return;
    }
    if (wizard.step.value === 3) {
        wizard.commit();
    }
}

function restart() {
    wizard.step.value = 1;
    Object.keys(amountOverrides).forEach((k) => delete amountOverrides[k]);
}

function retryFailedSubset() {
    wizard.step.value = 1;
    wizard.driftMessage.value = null;
}
</script>

<template>
    <Head title="Đẩy DNG hàng loạt" />

    <div class="space-y-6">
        <div class="space-y-2">
            <Link
                :href="financeRoutes.batchStudio.hub()"
                class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm"
            >
                <ArrowLeft class="h-4 w-4" />
                Batch Studio
            </Link>
            <div class="flex items-center gap-3">
                <div class="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-lg">
                    <Send class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Đẩy DNG hàng loạt</h1>
                    <p class="text-muted-foreground text-sm">Tối đa 100 SV mỗi lần chạy</p>
                </div>
            </div>
        </div>

        <Alert v-if="wizard.driftMessage.value" variant="destructive">
            <AlertDescription>{{ wizard.driftMessage.value }}</AlertDescription>
        </Alert>

        <BatchWizard
            :step="wizard.step.value"
            :summary-text="summaryText"
            :next-disabled="nextDisabled || wizard.previewing.value || wizard.form.processing"
            :can-back="wizard.step.value > 1 && wizard.step.value < 4"
            :next-label="wizard.step.value === 3 ? 'Đẩy DNG' : wizard.step.value === 1 ? 'Xem trước' : 'Tiếp'"
            @next="onNext"
            @back="wizard.step.value--"
        >
            <template #default="{ step }">
                <Card v-if="step === 1" class="border-0 shadow-none">
                    <CardHeader>
                        <CardTitle class="text-base">Thông tin yêu cầu DNG</CardTitle>
                        <CardDescription>Các trường này áp dụng cho toàn bộ lô đã chọn ở bước xác nhận.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label>Loại phí DNG</Label>
                            <Select v-model="wizard.setup.dng_fee_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Chọn loại phí" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="opt in props.dngFeeTypeOptions ?? []"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </SelectItem>
                                    <SelectItem v-if="!(props.dngFeeTypeOptions?.length)" value="tuition">
                                        Học phí
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Hạn thanh toán</Label>
                            <DatePicker v-model="wizard.setup.due_date" />
                        </div>
                        <div class="space-y-2">
                            <Label>Thời gian ước tính</Label>
                            <Input v-model="wizard.setup.estimate_time" placeholder="3d" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label>Mô tả</Label>
                            <Input v-model="wizard.setup.description" />
                        </div>
                        <div class="bg-muted/40 rounded-lg border px-4 py-3 text-sm sm:col-span-2">
                            <span class="text-muted-foreground">Kỳ đang chọn:</span>
                            <span class="ml-2 font-medium">{{ semesterLabelFor(wizard.setup.semester_id ?? semesterId) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <PreviewDiffTable
                    v-else-if="step === 2"
                    :lines="wizard.lines.value"
                    :selected="wizard.selected.value"
                    :counts="wizard.counts.value"
                    @toggle="wizard.toggle"
                />

                <div v-else-if="step === 3" class="mx-auto max-w-2xl space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Xác nhận trước khi đẩy DNG</CardTitle>
                            <CardDescription>{{ summaryText }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Alert v-if="hasOverrideDrift" variant="destructive">
                                <AlertDescription>
                                    Số tiền ghi đè lệch tổng tính được (≥1 VND) ở {{ overrideDriftCount }} SV → khoản
                                    này thành <strong>ad-hoc</strong>, <strong>bỏ liên kết đợt</strong> và phải
                                    <strong>đối soát thủ công</strong>.
                                </AlertDescription>
                            </Alert>
                            <Alert v-if="rerunCancelsCount > 0" variant="destructive">
                                <AlertDescription>
                                    {{ rerunCancelsCount }} SV đã có DNG đang chờ — chạy lại sẽ
                                    <strong>hủy DNG cũ</strong> rồi tạo mới.
                                </AlertDescription>
                            </Alert>
                            <Button type="button" variant="link" class="h-auto p-0" @click="wizard.excludeWarnings()">
                                Loại trừ tất cả dòng 🟠 cảnh báo
                            </Button>
                            <label v-if="needsAck" class="flex items-start gap-3 text-sm leading-relaxed">
                                <Checkbox v-model="ack" class="mt-0.5" />
                                <span>Tôi đã rà soát preview trước khi chạy lô lớn.</span>
                            </label>
                        </CardContent>
                    </Card>
                </div>

                <BatchResultPanel
                    v-else-if="step === 4 && wizard.result.value"
                    :result="wizard.result.value as BatchResult"
                    @retry-failed="retryFailedSubset"
                    @restart="restart"
                />
            </template>
        </BatchWizard>
    </div>
</template>