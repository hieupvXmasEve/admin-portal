<script setup lang="ts">
import BatchResultPanel from '@/components/finance/batch/BatchResultPanel.vue';
import BatchWizard from '@/components/finance/batch/BatchWizard.vue';
import PreviewDiffTable from '@/components/finance/batch/PreviewDiffTable.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useBatchStudio } from '@/composables/useBatchStudio';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import type { BatchResult } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bell, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { selectedId: semesterId, labelFor: semesterLabelFor } = useFinanceSemester();

const forceResend = ref(false);

const wizard = useBatchStudio({
    previewUrl: financeRoutes.batchStudio.remindersPreview(),
    commitUrl: financeRoutes.batchStudio.remindersCommit(),
    defaultSetup: {
        recipient: 'student' as 'student' | 'parent',
        semester_id: null as number | null,
    },
    commitExtras: () => ({ force_resend: forceResend.value }),
    defaultInclude: (l) => l.display.diff === 'create',
});

const ack = computed({
    get: () => Boolean(wizard.form.acknowledged),
    set: (v: boolean) => {
        wizard.form.acknowledged = v;
    },
});

const needsAck = computed(() => wizard.selected.value.size > 50);
const nextDisabled = computed(() => wizard.step.value === 3 && needsAck.value && !ack.value);

const summaryText = computed(() => {
    if (wizard.step.value === 1) return 'Chọn đối tượng nhận email trước khi xem trước';
    const c = wizard.counts.value;
    return `${wizard.selected.value.size} đã chọn · 🟢 ${c.create} · 🔵 ${c.update} · ⚪ ${c.skip} · 🟠 ${c.warning}`;
});

async function rerunPreviewWithForce() {
    forceResend.value = true;
    wizard.setup.semester_id = semesterId.value;
    await wizard.runPreview();
    wizard.selected.value = new Set(wizard.lines.value.map((l) => l.key));
}

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
</script>

<template>
    <Head title="Nhắc nợ hàng loạt" />

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
                    <Bell class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Nhắc nợ hàng loạt</h1>
                    <p class="text-muted-foreground text-sm">Gửi email nhắc thanh toán theo danh sách đến hạn</p>
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
            :next-label="wizard.step.value === 3 ? 'Gửi email' : wizard.step.value === 1 ? 'Xem trước' : 'Tiếp'"
            @next="onNext"
            @back="wizard.step.value--"
        >
            <template #default="{ step }">
                <Card v-if="step === 1" class="border-0 shadow-none">
                    <CardHeader>
                        <CardTitle class="text-base">Thiết lập người nhận</CardTitle>
                        <CardDescription>Dòng 🟠 (đã nhắc gần đây) sẽ không được chọn mặc định.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-5">
                        <div class="space-y-2">
                            <Label>Đối tượng</Label>
                            <Select v-model="wizard.setup.recipient">
                                <SelectTrigger>
                                    <SelectValue placeholder="Chọn đối tượng" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="student">Sinh viên</SelectItem>
                                    <SelectItem value="parent">Phụ huynh</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="bg-muted/40 rounded-lg border px-4 py-3 text-sm">
                            <span class="text-muted-foreground">Kỳ đang chọn:</span>
                            <span class="ml-2 font-medium">{{ semesterLabelFor(wizard.setup.semester_id ?? semesterId) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <div v-else-if="step === 2" class="space-y-4">
                    <Card class="border-amber-200/60 bg-amber-50/30">
                        <CardContent class="flex flex-col gap-3 pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <label class="flex items-start gap-3 text-sm leading-relaxed">
                                <Checkbox
                                    :model-value="forceResend"
                                    class="mt-0.5"
                                    @update:model-value="(v) => (forceResend = Boolean(v))"
                                />
                                <span>Buộc gửi lại — cần xem trước lại để cập nhật token an toàn</span>
                            </label>
                            <Button
                                v-if="forceResend"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="wizard.previewing.value"
                                @click="rerunPreviewWithForce"
                            >
                                <RefreshCw class="mr-2 h-4 w-4" :class="wizard.previewing.value ? 'animate-spin' : ''" />
                                Xem trước lại
                            </Button>
                        </CardContent>
                    </Card>
                    <PreviewDiffTable
                        :lines="wizard.lines.value"
                        :selected="wizard.selected.value"
                        :counts="wizard.counts.value"
                        @toggle="wizard.toggle"
                    />
                </div>

                <div v-else-if="step === 3" class="mx-auto max-w-lg">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Xác nhận gửi email</CardTitle>
                            <CardDescription>{{ summaryText }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <label v-if="needsAck" class="flex items-start gap-3 text-sm leading-relaxed">
                                <Checkbox v-model="ack" class="mt-0.5" />
                                <span>Tôi đã rà soát danh sách trước khi gửi lô lớn.</span>
                            </label>
                        </CardContent>
                    </Card>
                </div>

                <BatchResultPanel
                    v-else-if="step === 4 && wizard.result.value"
                    :result="wizard.result.value as BatchResult"
                    @restart="wizard.step.value = 1"
                />
            </template>
        </BatchWizard>
    </div>
</template>