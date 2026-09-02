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
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useBatchStudio } from '@/composables/useBatchStudio';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import type { BatchResult } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowLeft, CalendarIcon, Send } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface DngPrefill {
    dng_fee_type: string;
    semester_id: number | null;
    campus_id: number | null;
    student_ids: number[];
}

const props = defineProps<{
    dngFeeTypeOptions?: { value: string; label: string }[];
    prefill?: DngPrefill;
}>();

const { selectedId: semesterId, context: semesterContext } = useFinanceSemester();

const now = new Date();
const estimateTimePickerOpen = ref(false);
const estimateMonth = ref(String(now.getMonth() + 1).padStart(2, '0'));
const estimateYear = ref(String(now.getFullYear()));
const defaultEstimateTime = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getFullYear()).slice(-2)}`;
const confirmOpen = ref(false);

const setupErrors = ref<Record<string, string>>({});

const estimateMonthOptions = Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1).padStart(2, '0'),
    label: `Tháng ${String(i + 1).padStart(2, '0')}`,
}));

const estimateYearOptions = Array.from({ length: 6 }, (_, i) => {
    const year = now.getFullYear() - 1 + i;
    return { value: String(year), label: `Năm ${year}` };
});

const semesterOptions = computed(() => semesterContext.value?.options ?? []);

const semesterSelection = computed({
    get: () => (wizard.setup.semester_id ?? semesterId.value)?.toString() ?? '',
    set: (value: string) => {
        wizard.setup.semester_id = value ? Number(value) : null;
    },
});

const wizard = useBatchStudio({
    previewUrl: financeRoutes.batchStudio.dngPreview(),
    commitUrl: financeRoutes.batchStudio.dngCommit(),
    defaultSetup: {
        semester_id: props.prefill?.semester_id ?? null,
        dng_fee_type: props.prefill?.dng_fee_type ?? 'HP',
        student_ids: props.prefill?.student_ids ?? ([] as number[]),
        due_date: '',
        description: '',
        estimate_time: defaultEstimateTime,
    },
    commitExtras: () => ({
        due_date: wizard.setup.due_date,
        description: wizard.setup.description,
        estimate_time: wizard.setup.estimate_time,
    }),
    // 'warning' rows (coverage unknown / replacement disabled) can never
    // commit (H14 fail-closed) — never pre-select them, so the operator has
    // to make a deliberate choice instead of tripping the block on submit.
    defaultInclude: (line) => line.display.diff === 'create' || line.display.diff === 'update',
});

function applyEstimateTimeSelection(): void {
    wizard.setup.estimate_time = `${estimateMonth.value}/${estimateYear.value.slice(-2)}`;
    estimateTimePickerOpen.value = false;
    setupErrors.value.estimate_time = '';
}

function validateSetup(): boolean {
    const errors: Record<string, string> = {};

    if (!wizard.setup.description?.trim()) {
        errors.description = 'Vui lòng nhập mô tả khoản phí.';
    }
    if (!wizard.setup.due_date) {
        errors.due_date = 'Vui lòng chọn hạn thanh toán.';
    }
    if (!wizard.setup.estimate_time?.trim()) {
        errors.estimate_time = 'Vui lòng chọn thời hạn thanh toán (MM/YY).';
    }

    setupErrors.value = errors;
    return Object.keys(errors).length === 0;
}

const ack = computed({
    get: () => Boolean(wizard.form.acknowledged),
    set: (v: boolean) => {
        wizard.form.acknowledged = v;
    },
});

// H16: replacing a live DNG collection cancels a real provider record — needs
// explicit acknowledgement of exactly how many, same as the existing
// large-batch (>50) guard.
const replacementCount = computed(() => [...wizard.selected.value].filter((key) => wizard.lines.value.find((l) => l.key === key)?.display.diff === 'update').length);
const needsAck = computed(() => wizard.selected.value.size > 50 || replacementCount.value > 0);
const nextDisabled = computed(() => {
    if (!semesterSelection.value) return true;
    if (!wizard.previewToken.value) return true;
    if (confirmOpen.value && needsAck.value && !ack.value) return true;

    return false;
});

const fieldError = (field: 'due_date' | 'description' | 'estimate_time'): string | undefined => setupErrors.value[field] ?? (wizard.form.errors[field] as string | undefined);

const summaryText = computed(() => {
    const c = wizard.counts.value;
    const total = wizard.summary.value.total_students ?? wizard.lines.value.length;
    return `${wizard.selected.value.size} đã chọn · ${total} SV · 🟢 ${c.create} · 🔵 ${c.update} · ⚪ ${c.skip} · 🟠 ${c.warning}`;
});

function requestPreview(): void {
    wizard.setup.semester_id = wizard.setup.semester_id ?? semesterId.value;
    if (!semesterSelection.value) return;
    confirmOpen.value = false;
    void wizard.runPreview();
}

const debouncedPreview = useDebounceFn(requestPreview, 400);

function onNext() {
    if (!confirmOpen.value) {
        confirmOpen.value = true;
        return;
    }
    if (!validateSetup()) {
        return;
    }
    wizard.commit();
}

function restart() {
    confirmOpen.value = false;
    wizard.resetToInspect();
    requestPreview();
}

function retryFailedSubset() {
    confirmOpen.value = false;
    wizard.resetToInspect();
    wizard.driftMessage.value = null;
    requestPreview();
}

watch(
    () => [wizard.setup.dng_fee_type, wizard.setup.semester_id],
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
    <Head title="Lập yêu cầu thanh toán DNG" />

    <div class="space-y-6">
        <div class="space-y-2">
            <Link :href="financeRoutes.batchStudio.hub()" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm">
                <ArrowLeft class="h-4 w-4" />
                Sinh phí & lệnh thu
            </Link>
            <div class="flex items-center gap-3">
                <div class="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-lg">
                    <Send class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Lập yêu cầu thanh toán DNG</h1>
                    <p class="text-muted-foreground text-sm">Tối đa 100 SV mỗi lần chạy</p>
                </div>
            </div>
        </div>

        <Alert v-if="wizard.driftMessage.value" variant="destructive">
            <AlertDescription>{{ wizard.driftMessage.value }}</AlertDescription>
        </Alert>
        <Alert v-if="wizard.summary.value.truncated">
            <AlertDescription>Danh sách vượt quá 200 dòng — một số sinh viên có thể chưa hiển thị. Thu hẹp kỳ trước khi chạy lô.</AlertDescription>
        </Alert>

        <div v-if="wizard.mode.value === 'result' && wizard.result.value">
            <BatchResultPanel :result="wizard.result.value as BatchResult" @retry-failed="retryFailedSubset" @restart="restart" />
        </div>

        <BatchWizard v-else :summary-text="summaryText" :next-disabled="nextDisabled || wizard.previewing.value || wizard.form.processing" :can-back="confirmOpen" next-label="Tạo lệnh thu" @next="onNext" @back="confirmOpen = false">
            <div class="space-y-6">
                <Card class="border-0 shadow-none">
                    <CardHeader>
                        <CardTitle class="text-base">Phạm vi</CardTitle>
                        <CardDescription>Chọn kỳ và loại phí — danh sách hiện ra bên dưới. Hạn và mô tả điền khi tạo lệnh thu.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Loại phí DNG</Label>
                            <Select v-model="wizard.setup.dng_fee_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Chọn loại phí" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in props.dngFeeTypeOptions ?? []" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                    <SelectItem v-if="!props.dngFeeTypeOptions?.length" value="HP"> Học phí </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-2">
                            <Label>Kỳ</Label>
                            <Select v-model="semesterSelection">
                                <SelectTrigger>
                                    <SelectValue placeholder="Chọn kỳ" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in semesterOptions" :key="option.id" :value="option.id.toString()"> {{ option.name }} ({{ option.code }})<template v-if="option.is_active"> · hiện tại</template> </SelectItem>
                                </SelectContent>
                            </Select>
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
                    @toggle="wizard.toggle"
                    @select-all="(keys) => wizard.setSelection(keys, true)"
                    @deselect-all="(keys) => wizard.setSelection(keys, false)"
                />

                <Card v-if="confirmOpen">
                    <CardHeader>
                        <CardTitle class="text-base">Tạo lệnh thu</CardTitle>
                        <CardDescription>{{ summaryText }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="dng-description"> Mô tả khoản phí <span class="text-red-500">*</span> </Label>
                            <Input id="dng-description" v-model="wizard.setup.description" placeholder="VD: Học phí kỳ 1 năm học 2025-2026" :class="{ 'border-red-400': fieldError('description') }" @update:model-value="setupErrors.description = ''" />
                            <p v-if="fieldError('description')" class="text-xs text-red-500">{{ fieldError('description') }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label> Hạn thanh toán nội bộ <span class="text-red-500">*</span> </Label>
                            <DatePicker v-model="wizard.setup.due_date" placeholder="Chọn hạn thanh toán" @update:model-value="setupErrors.due_date = ''" />
                            <p v-if="fieldError('due_date')" class="text-xs text-red-500">{{ fieldError('due_date') }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Thời hạn thanh toán (MM/YY) <span class="text-red-500">*</span></Label>
                            <Popover v-model:open="estimateTimePickerOpen">
                                <PopoverTrigger as-child>
                                    <Button variant="outline" class="w-full justify-start font-normal" :class="{ 'border-red-400': fieldError('estimate_time') }">
                                        <CalendarIcon class="mr-2 h-4 w-4" />
                                        {{ wizard.setup.estimate_time || 'Chọn tháng/năm' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-64 space-y-3 p-4" align="start">
                                    <div class="space-y-1.5">
                                        <Label>Tháng</Label>
                                        <Select v-model="estimateMonth">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="m in estimateMonthOptions" :key="m.value" :value="m.value">
                                                    {{ m.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label>Năm</Label>
                                        <Select v-model="estimateYear">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="y in estimateYearOptions" :key="y.value" :value="y.value">
                                                    {{ y.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button type="button" class="w-full" @click="applyEstimateTimeSelection">Áp dụng</Button>
                                </PopoverContent>
                            </Popover>
                            <p class="text-muted-foreground text-xs">Chuỗi MM/YY gửi lên hệ thống DNG.</p>
                            <p v-if="fieldError('estimate_time')" class="text-xs text-red-500">{{ fieldError('estimate_time') }}</p>
                        </div>

                        <div class="space-y-4 sm:col-span-2">
                            <Button type="button" variant="link" class="h-auto p-0" @click="wizard.excludeWarnings()"> Loại trừ tất cả dòng 🟠 cần kiểm tra </Button>
                            <Alert v-if="wizard.form.errors.selected_keys" variant="destructive">
                                <AlertDescription>{{ wizard.form.errors.selected_keys }}</AlertDescription>
                            </Alert>
                            <label v-if="needsAck" class="flex items-start gap-3 text-sm leading-relaxed">
                                <Checkbox v-model="ack" class="mt-0.5" />
                                <span v-if="replacementCount > 0"> Tôi xác nhận hủy {{ replacementCount }} lệnh thu DNG hiện có và đẩy lệnh mới thay thế gồm toàn bộ khoản phải thu. </span>
                                <span v-else>Tôi đã rà soát danh sách trước khi chạy lô lớn.</span>
                            </label>
                            <p v-if="wizard.form.errors.acknowledged" class="text-xs text-red-500">{{ wizard.form.errors.acknowledged }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </BatchWizard>
    </div>
</template>
