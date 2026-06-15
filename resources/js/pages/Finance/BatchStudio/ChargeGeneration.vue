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
import { ArrowLeft, GraduationCap } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ feeTypeOptions?: { value: string; label: string }[] }>();

const { selectedId: semesterId, labelFor: semesterLabelFor } = useFinanceSemester();

const wizard = useBatchStudio({
    previewUrl: financeRoutes.batchStudio.chargesPreview(),
    commitUrl: financeRoutes.batchStudio.chargesCommit(),
    defaultSetup: {
        fee_category: 'major' as 'major' | 'egc' | 'non_academic',
        semester_id: null as number | null,
        scope: { filters: {} },
    },
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
    if (wizard.step.value === 1) return 'Chọn loại phí và phạm vi trước khi xem trước';
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
</script>

<template>
    <Head title="Sinh phí hàng loạt" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
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
                        <GraduationCap class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Sinh phí hàng loạt</h1>
                        <p class="text-muted-foreground text-sm">HP · EGC · Phí phi học vụ</p>
                    </div>
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
            :next-label="wizard.step.value === 3 ? 'Chạy sinh phí' : wizard.step.value === 1 ? 'Xem trước' : 'Tiếp'"
            @next="onNext"
            @back="wizard.step.value--"
        >
            <template #default="{ step }">
                <Card v-if="step === 1" class="border-0 shadow-none">
                    <CardHeader>
                        <CardTitle class="text-base">Thiết lập phạm vi</CardTitle>
                        <CardDescription>Chọn loại phí. Kỳ học lấy từ thanh trên cùng.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-5">
                        <div class="space-y-2">
                            <Label>Loại phí</Label>
                            <Select v-model="wizard.setup.fee_category">
                                <SelectTrigger>
                                    <SelectValue placeholder="Chọn loại phí" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="major">HP (Tuition)</SelectItem>
                                    <SelectItem value="egc">EGC</SelectItem>
                                    <SelectItem value="non_academic">Phí phi học vụ</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="bg-muted/40 rounded-lg border px-4 py-3 text-sm">
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
                    exportable
                    @toggle="wizard.toggle"
                />

                <div v-else-if="step === 3" class="mx-auto max-w-lg space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Xác nhận trước khi chạy</CardTitle>
                            <CardDescription>{{ summaryText }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Button type="button" variant="link" class="h-auto p-0" @click="wizard.excludeWarnings()">
                                Loại trừ tất cả dòng 🟠 cảnh báo
                            </Button>
                            <label v-if="needsAck" class="flex items-start gap-3 text-sm leading-relaxed">
                                <Checkbox v-model="ack" class="mt-0.5" />
                                <span>Tôi đã rà soát preview và xác nhận chạy lô lớn (&gt;50 dòng).</span>
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