<script setup lang="ts">
import BatchResultPanel from '@/components/finance/batch/BatchResultPanel.vue';
import BatchWizard from '@/components/finance/batch/BatchWizard.vue';
import PreviewDiffTable from '@/components/finance/batch/PreviewDiffTable.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useBatchStudio } from '@/composables/useBatchStudio';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import type { BatchResult } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, GraduationCap } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

type FeeCategory = 'major' | 'egc' | 'non_academic';

interface ChargeScope {
    filters: Record<string, unknown>;
    fee_type?: string;
    amount?: string | number;
    note?: string;
}

interface ChargePrefill {
    fee_category: FeeCategory;
    semester_id: number | null;
    scope: ChargeScope;
}

interface ChargeSetup extends Record<string, unknown> {
    fee_category: FeeCategory;
    semester_id: number | null;
    scope: ChargeScope;
}

const props = defineProps<{
    feeTypeOptions?: { value: string; label: string }[];
    prefill?: ChargePrefill;
}>();

const { selectedId: semesterId, labelFor: semesterLabelFor } = useFinanceSemester();

const defaultSetup: ChargeSetup = {
    fee_category: props.prefill?.fee_category ?? 'major',
    semester_id: props.prefill?.semester_id ?? null,
    scope: {
        filters: props.prefill?.scope?.filters ?? {},
        fee_type: props.prefill?.scope?.fee_type ?? props.feeTypeOptions?.[0]?.value ?? '',
        amount: props.prefill?.scope?.amount ?? '',
        note: props.prefill?.scope?.note ?? '',
    },
};

const wizard = useBatchStudio<ChargeSetup>({
    previewUrl: financeRoutes.batchStudio.chargesPreview(),
    commitUrl: financeRoutes.batchStudio.chargesCommit(),
    defaultSetup,
    commitExtras: () => {
        return {
            block_overrides: Object.keys(blockOverrides).length ? { ...blockOverrides } : undefined,
        };
    },
});

const blockOverrides = reactive<Record<string, number>>({});

const ack = computed({
    get: () => Boolean(wizard.form.acknowledged),
    set: (v: boolean) => {
        wizard.form.acknowledged = v;
    },
});

const needsAck = computed(() => wizard.selected.value.size > 50);
const isNonAcademic = computed(() => wizard.setup.fee_category === 'non_academic');
const isEgc = computed(() => wizard.setup.fee_category === 'egc');
const nonAcademicReady = computed(() => {
    if (!isNonAcademic.value) return true;

    return Boolean(wizard.setup.scope.fee_type && Number(wizard.setup.scope.amount) > 0);
});
const nextDisabled = computed(() => {
    if (wizard.step.value === 1 && isNonAcademic.value) {
        return !nonAcademicReady.value;
    }

    return wizard.step.value === 3 && needsAck.value && !ack.value;
});

const summaryText = computed(() => {
    if (wizard.step.value === 1) return 'Chọn loại phí và phạm vi trước khi xem trước';
    const c = wizard.counts.value;
    return `${wizard.selected.value.size} đã chọn · 🟢 ${c.create} · 🔵 ${c.update} · ⚪ ${c.skip} · 🟠 ${c.warning}`;
});

function onNext() {
    if (wizard.step.value === 1) {
        wizard.setup.semester_id = wizard.setup.semester_id ?? semesterId.value;
        if (!prepareScopeForCategory()) return;
        Object.keys(blockOverrides).forEach((key) => delete blockOverrides[key]);
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

function setBlockOverride(key: string, count: number) {
    blockOverrides[key] = count;
}

function exportPreview() {
    if (!wizard.setup.semester_id) {
        wizard.driftMessage.value = 'Vui lòng chọn kỳ học trước khi xuất Excel.';
        return;
    }

    window.location.assign(
        financeRoutes.batchStudio.chargesExport({
            fee_category: wizard.setup.fee_category,
            semester_id: wizard.setup.semester_id,
            scope: wizard.setup.scope,
        }),
    );
}

function prepareScopeForCategory(): boolean {
    if (isNonAcademic.value) {
        return prepareNonAcademicScope();
    }

    wizard.setup.scope = {
        filters: wizard.setup.scope.filters ?? {},
    };

    return true;
}

function prepareNonAcademicScope(): boolean {
    if (!isNonAcademic.value) return true;

    wizard.setup.scope.filters = wizard.setup.scope.filters ?? {};
    wizard.setup.scope.fee_type = wizard.setup.scope.fee_type || props.feeTypeOptions?.[0]?.value || '';
    wizard.setup.scope.amount = Number(wizard.setup.scope.amount ?? 0);
    wizard.setup.scope.note = String(wizard.setup.scope.note ?? '').slice(0, 255);

    if (!nonAcademicReady.value) {
        wizard.driftMessage.value = 'Vui lòng chọn loại phí và số tiền.';
        return false;
    }

    return true;
}
</script>

<template>
    <Head title="Sinh phí hàng loạt" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <Link :href="financeRoutes.batchStudio.hub()" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm">
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
                        <div v-if="isNonAcademic" class="bg-muted/20 grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Loại phí phi học vụ</Label>
                                <Select v-model="wizard.setup.scope.fee_type">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Chọn loại phí" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in feeTypeOptions ?? []" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Số tiền</Label>
                                <Input v-model="wizard.setup.scope.amount" type="number" min="1" step="1" placeholder="500000" />
                            </div>
                            <div class="space-y-2">
                                <Label>Ghi chú</Label>
                                <Input v-model="wizard.setup.scope.note" maxlength="255" placeholder="Tùy chọn" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <PreviewDiffTable
                    v-else-if="step === 2"
                    :lines="wizard.lines.value"
                    :selected="wizard.selected.value"
                    :counts="wizard.counts.value"
                    :block-count-controls="isEgc"
                    :block-counts="blockOverrides"
                    exportable
                    @toggle="wizard.toggle"
                    @export="exportPreview"
                    @update-block-count="setBlockOverride"
                />

                <div v-else-if="step === 3" class="mx-auto max-w-lg space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Xác nhận trước khi chạy</CardTitle>
                            <CardDescription>{{ summaryText }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Button type="button" variant="link" class="h-auto p-0" @click="wizard.excludeWarnings()"> Loại trừ tất cả dòng 🟠 cảnh báo </Button>
                            <label v-if="needsAck" class="flex items-start gap-3 text-sm leading-relaxed">
                                <Checkbox v-model="ack" class="mt-0.5" />
                                <span>Tôi đã rà soát preview và xác nhận chạy lô lớn (&gt;50 dòng).</span>
                            </label>
                        </CardContent>
                    </Card>
                </div>

                <BatchResultPanel v-else-if="step === 4 && wizard.result.value" :result="wizard.result.value as BatchResult" @restart="wizard.step.value = 1" />
            </template>
        </BatchWizard>
    </div>
</template>
