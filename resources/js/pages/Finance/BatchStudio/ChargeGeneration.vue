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
import { Skeleton } from '@/components/ui/skeleton';
import { useBatchStudio } from '@/composables/useBatchStudio';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import { usePermission } from '@/composables/usePermission';
import type { BatchResult } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowLeft, GraduationCap } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

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

const { selectedId: semesterId, context: semesterContext } = useFinanceSemester();
const permission = usePermission();
const confirmOpen = ref(false);

const semesterOptions = computed(() => semesterContext.value?.options ?? []);

const semesterSelection = computed({
    get: () => (wizard.setup.semester_id ?? semesterId.value)?.toString() ?? '',
    set: (value: string) => {
        wizard.setup.semester_id = value ? Number(value) : null;
    },
});

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
const showDngCta = computed(() => permission.can('create_finance_payments') && wizard.setup.fee_category !== 'non_academic');
const nextDisabled = computed(() => {
    if (!semesterSelection.value) return true;
    if (isNonAcademic.value && !nonAcademicReady.value) return true;
    if (!wizard.previewToken.value) return true;
    if (confirmOpen.value && needsAck.value && !ack.value) return true;

    return false;
});

const summaryText = computed(() => {
    const c = wizard.counts.value;
    const total = wizard.summary.value.total_students ?? wizard.lines.value.length;
    return `${wizard.selected.value.size} đã chọn · ${total} SV · 🟢 ${c.create} · 🔵 ${c.update} · ⚪ ${c.skip} · 🟠 ${c.warning}`;
});

function requestPreview(): void {
    wizard.setup.semester_id = wizard.setup.semester_id ?? semesterId.value;
    if (!semesterSelection.value) return;
    if (isNonAcademic.value && !nonAcademicReady.value) {
        wizard.driftMessage.value = null;
        wizard.clearPreview();
        return;
    }
    if (!prepareScopeForCategory()) return;
    Object.keys(blockOverrides).forEach((key) => delete blockOverrides[key]);
    confirmOpen.value = false;
    void wizard.runPreview();
}

const debouncedPreview = useDebounceFn(requestPreview, 400);

function onNext() {
    if (!confirmOpen.value) {
        confirmOpen.value = true;
        return;
    }
    wizard.commit();
}

function setBlockOverride(key: string, count: number) {
    blockOverrides[key] = count;
}

function exportPreview() {
    if (!wizard.previewToken.value) {
        wizard.driftMessage.value = 'Phiên xem trước không hợp lệ. Vui lòng xem trước lại.';
        return;
    }

    window.location.assign(
        financeRoutes.batchStudio.chargesExport({
            preview_token: wizard.previewToken.value,
        }),
    );
}

function prepareScopeForCategory(): boolean {
    if (isNonAcademic.value) {
        return prepareNonAcademicScope();
    }

    wizard.setup.scope.filters = wizard.setup.scope.filters ?? {};

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

function restart() {
    confirmOpen.value = false;
    wizard.resetToInspect();
    requestPreview();
}

watch(
    () => [wizard.setup.fee_category, wizard.setup.semester_id, wizard.setup.scope.fee_type, wizard.setup.scope.amount, wizard.setup.scope.note],
    () => {
        confirmOpen.value = false;
        void debouncedPreview();
    },
);

watch(semesterId, (id) => {
    if (wizard.setup.semester_id == null && id) {
        wizard.setup.semester_id = id;
    }
});

onMounted(() => {
    wizard.setup.semester_id = wizard.setup.semester_id ?? semesterId.value;
    requestPreview();
});
</script>

<template>
    <Head title="Sinh phí hàng loạt" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <Link :href="financeRoutes.batchStudio.hub()" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm">
                    <ArrowLeft class="h-4 w-4" />
                    Sinh phí & lệnh thu
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

        <div v-if="wizard.mode.value === 'result' && wizard.result.value" class="space-y-4">
            <BatchResultPanel :result="wizard.result.value as BatchResult" @restart="restart" />
            <Link v-if="showDngCta" :href="financeRoutes.batchStudio.dng()" class="text-primary inline-flex items-center gap-1.5 text-sm font-medium hover:underline"> Xem / lập lệnh thu kỳ này </Link>
        </div>

        <BatchWizard v-else :summary-text="summaryText" :next-disabled="nextDisabled || wizard.previewing.value || wizard.form.processing" :can-back="confirmOpen" next-label="Sinh phí" @next="onNext" @back="confirmOpen = false">
            <div class="space-y-6">
                <Card class="border-0 shadow-none">
                    <CardHeader>
                        <CardTitle class="text-base">Phạm vi</CardTitle>
                        <CardDescription>Chọn loại phí và kỳ — danh sách hiện ra bên dưới.</CardDescription>
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
                        <div class="space-y-2">
                            <Label>Kỳ sinh phí</Label>
                            <Select v-model="semesterSelection">
                                <SelectTrigger>
                                    <SelectValue placeholder="Chọn kỳ" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in semesterOptions" :key="option.id" :value="option.id.toString()"> {{ option.name }} ({{ option.code }})<template v-if="option.is_active"> · hiện tại</template> </SelectItem>
                                </SelectContent>
                            </Select>
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

                <div v-if="wizard.previewing.value" class="space-y-3">
                    <Skeleton class="h-20 w-full" />
                    <Skeleton class="h-64 w-full" />
                </div>
                <PreviewDiffTable
                    v-else
                    :lines="wizard.lines.value"
                    :selected="wizard.selected.value"
                    :counts="wizard.counts.value"
                    :block-count-controls="isEgc"
                    :block-counts="blockOverrides"
                    :major-details="wizard.setup.fee_category === 'major'"
                    :major-context="wizard.summary.value.major_context"
                    :credit-offset-column="wizard.setup.fee_category === 'major' || isEgc"
                    exportable
                    @toggle="wizard.toggle"
                    @select-all="(keys) => wizard.setSelection(keys, true)"
                    @deselect-all="(keys) => wizard.setSelection(keys, false)"
                    @export="exportPreview"
                    @update-block-count="setBlockOverride"
                />

                <Card v-if="confirmOpen">
                    <CardHeader>
                        <CardTitle class="text-base">Xác nhận trước khi chạy</CardTitle>
                        <CardDescription>{{ summaryText }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Button type="button" variant="link" class="h-auto p-0" @click="wizard.excludeWarnings()"> Loại trừ tất cả dòng 🟠 cần kiểm tra </Button>
                        <label v-if="needsAck" class="flex items-start gap-3 text-sm leading-relaxed">
                            <Checkbox v-model="ack" class="mt-0.5" />
                            <span>Tôi đã rà soát danh sách và xác nhận chạy lô lớn (&gt;50 dòng).</span>
                        </label>
                    </CardContent>
                </Card>
            </div>
        </BatchWizard>
    </div>
</template>
