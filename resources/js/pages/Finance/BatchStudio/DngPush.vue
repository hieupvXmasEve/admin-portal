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
import { useBatchStudio } from '@/composables/useBatchStudio';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import type { BatchResult } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarIcon, Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

const { selectedId: semesterId, labelFor: semesterLabelFor } = useFinanceSemester();

const now = new Date();
const estimateTimePickerOpen = ref(false);
const estimateMonth = ref(String(now.getMonth() + 1).padStart(2, '0'));
const estimateYear = ref(String(now.getFullYear()));
const defaultEstimateTime = `${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getFullYear()).slice(-2)}`;

const setupErrors = ref<Record<string, string>>({});

const estimateMonthOptions = Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1).padStart(2, '0'),
    label: `Tháng ${String(i + 1).padStart(2, '0')}`,
}));

const estimateYearOptions = Array.from({ length: 6 }, (_, i) => {
    const year = now.getFullYear() - 1 + i;
    return { value: String(year), label: `Năm ${year}` };
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
    onCommitError: (errors) => {
        if (errors.due_date || errors.description || errors.estimate_time) {
            wizard.step.value = 1;
        }
    },
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

const needsAck = computed(() => wizard.selected.value.size > 50);
const nextDisabled = computed(() => wizard.step.value === 3 && needsAck.value && !ack.value);

const rerunCancelsCount = computed(() => wizard.lines.value.filter((l) => l.display.warning_codes?.includes('rerun_cancels_old_dng')).length);

const fieldError = (field: 'due_date' | 'description' | 'estimate_time'): string | undefined =>
    setupErrors.value[field] ?? (wizard.form.errors[field] as string | undefined);

const summaryText = computed(() => {
    if (wizard.step.value === 1) return 'Điền thông tin yêu cầu thanh toán trước khi xem trước danh sách SV';
    const c = wizard.counts.value;
    return `${wizard.selected.value.size} đã chọn · 🟢 ${c.create} · 🔵 ${c.update} · ⚪ ${c.skip} · 🟠 ${c.warning}`;
});

function onNext() {
    if (wizard.step.value === 1) {
        if (!validateSetup()) return;
        wizard.setup.semester_id = wizard.setup.semester_id ?? semesterId.value;
        return void wizard.runPreview();
    }
    if (wizard.step.value === 2) {
        wizard.step.value = 3;
        return;
    }
    if (wizard.step.value === 3) {
        if (!validateSetup()) {
            wizard.step.value = 1;
            return;
        }
        wizard.commit();
    }
}

function restart() {
    wizard.step.value = 1;
}

function retryFailedSubset() {
    wizard.step.value = 1;
    wizard.driftMessage.value = null;
}
</script>

<template>
    <Head title="Lập yêu cầu thanh toán DNG" />

    <div class="space-y-6">
        <div class="space-y-2">
            <Link :href="financeRoutes.batchStudio.hub()" class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm">
                <ArrowLeft class="h-4 w-4" />
                Batch Studio
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

        <BatchWizard
            :step="wizard.step.value"
            :summary-text="summaryText"
            :next-disabled="nextDisabled || wizard.previewing.value || wizard.form.processing"
            :can-back="wizard.step.value > 1 && wizard.step.value < 4"
            :next-label="wizard.step.value === 3 ? 'Gửi yêu cầu sang DNG' : wizard.step.value === 1 ? 'Xem trước' : 'Tiếp'"
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
                                    <SelectItem v-for="opt in props.dngFeeTypeOptions ?? []" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                    <SelectItem v-if="!props.dngFeeTypeOptions?.length" value="HP"> Học phí </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="dng-description">
                                Mô tả khoản phí <span class="text-red-500">*</span>
                            </Label>
                            <Input
                                id="dng-description"
                                v-model="wizard.setup.description"
                                placeholder="VD: Học phí kỳ 1 năm học 2025-2026"
                                :class="{ 'border-red-400': fieldError('description') }"
                                @update:model-value="setupErrors.description = ''"
                            />
                            <p v-if="fieldError('description')" class="text-xs text-red-500">{{ fieldError('description') }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>
                                Hạn thanh toán nội bộ <span class="text-red-500">*</span>
                            </Label>
                            <DatePicker
                                v-model="wizard.setup.due_date"
                                placeholder="Chọn hạn thanh toán"
                                @update:model-value="setupErrors.due_date = ''"
                            />
                            <p v-if="fieldError('due_date')" class="text-xs text-red-500">{{ fieldError('due_date') }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Thời hạn thanh toán (MM/YY) <span class="text-red-500">*</span></Label>
                            <Popover v-model:open="estimateTimePickerOpen">
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        class="w-full justify-start font-normal"
                                        :class="{ 'border-red-400': fieldError('estimate_time') }"
                                    >
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

                        <div class="bg-muted/40 rounded-lg border px-4 py-3 text-sm sm:col-span-2">
                            <span class="text-muted-foreground">Kỳ đang chọn:</span>
                            <span class="ml-2 font-medium">{{ semesterLabelFor(wizard.setup.semester_id ?? semesterId) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <PreviewDiffTable v-else-if="step === 2" :lines="wizard.lines.value" :selected="wizard.selected.value" :counts="wizard.counts.value" @toggle="wizard.toggle" />

                <div v-else-if="step === 3" class="mx-auto max-w-2xl space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Xác nhận trước khi đẩy DNG</CardTitle>
                            <CardDescription>{{ summaryText }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="bg-muted/40 space-y-1 rounded-lg border px-4 py-3 text-sm">
                                <div><span class="text-muted-foreground">Mô tả:</span> {{ wizard.setup.description }}</div>
                                <div><span class="text-muted-foreground">Hạn thanh toán:</span> {{ wizard.setup.due_date }}</div>
                                <div><span class="text-muted-foreground">Thời hạn DNG:</span> {{ wizard.setup.estimate_time }}</div>
                            </div>
                            <Alert v-if="rerunCancelsCount > 0" variant="destructive">
                                <AlertDescription> {{ rerunCancelsCount }} SV đã có DNG đang chờ — chạy lại sẽ <strong>hủy DNG cũ</strong> rồi tạo mới. </AlertDescription>
                            </Alert>
                            <Button type="button" variant="link" class="h-auto p-0" @click="wizard.excludeWarnings()"> Loại trừ tất cả dòng 🟠 cảnh báo </Button>
                            <label v-if="needsAck" class="flex items-start gap-3 text-sm leading-relaxed">
                                <Checkbox v-model="ack" class="mt-0.5" />
                                <span>Tôi đã rà soát preview trước khi chạy lô lớn.</span>
                            </label>
                        </CardContent>
                    </Card>
                </div>

                <BatchResultPanel v-else-if="step === 4 && wizard.result.value" :result="wizard.result.value as BatchResult" @retry-failed="retryFailedSubset" @restart="restart" />
            </template>
        </BatchWizard>
    </div>
</template>
