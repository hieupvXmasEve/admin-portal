<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { GradingScheme } from '@/types/grading-scheme';
import { BookOpen, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import GradingSchemeGuideModal from './GradingSchemeGuideModal.vue';
import { type BuilderState, type RowConversionType, createDefaultBuilderState, createRowConfig, deserializeScheme, serializeBuilderState } from './grading-scheme-builder';
import type { ExampleComponent, GradingSchemeExample } from './grading-scheme-examples';

const props = defineProps<{
    modelValue: GradingScheme | null;
    /** Assessment components defined on the template (single source of truth). */
    components: Array<{ code: string; label: string }>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: GradingScheme | null];
    'apply-components': [components: ExampleComponent[]];
}>();

const CONVERSION_OPTIONS: Array<{ value: RowConversionType; label: string }> = [
    { value: 'linear', label: 'Tuyến tính (linear)' },
    { value: 'threshold', label: 'Mốc bậc thang (threshold)' },
    { value: 'direct', label: 'Trực tiếp /5 (direct)' },
    { value: 'pass_fail', label: 'Đạt/Không đạt (pass_fail)' },
    { value: 'none', label: 'Chỉ điều kiện (không cộng điểm)' },
];

const state = reactive<BuilderState>(deserializeScheme(props.modelValue));
const showGuide = ref(false);

const codedComponents = computed(() => props.components.filter((component) => component.code !== ''));
const codelessCount = computed(() => props.components.length - codedComponents.value.length);
const declaredCodes = computed(() => codedComponents.value.map((component) => component.code));

// Ensure a conversion config exists for every coded component.
watch(
    () => codedComponents.value.map((component) => component.code).join('|'),
    () => {
        for (const component of codedComponents.value) {
            if (!state.configByCode[component.code]) {
                state.configByCode[component.code] = createRowConfig();
            }
        }
    },
    { immediate: true },
);

let lastEmitted = JSON.stringify(props.modelValue);

watch(
    [state, () => props.components],
    () => {
        const scheme = serializeBuilderState(state, props.components);
        const serialized = JSON.stringify(scheme);
        if (serialized !== lastEmitted) {
            lastEmitted = serialized;
            emit('update:modelValue', scheme);
        }
    },
    { deep: true },
);

watch(
    () => props.modelValue,
    (value) => {
        if (JSON.stringify(value) === lastEmitted) {
            return; // echo of our own emit
        }
        lastEmitted = JSON.stringify(value);
        Object.assign(state, deserializeScheme(value));
    },
);

function addStep(code: string): void {
    state.configByCode[code].steps.push({ min_pct: 0, grade: 1 });
}

function removeStep(code: string, stepIndex: number): void {
    const steps = state.configByCode[code].steps;
    steps.splice(stepIndex, 1);
    if (steps.length === 0) {
        steps.push({ min_pct: 50, grade: 1 });
    }
}

function insertVariable(code: string): void {
    state.formula = state.formula === '' ? code : `${state.formula} ${code}`;
}

function addPassRequirement(): void {
    state.passRequirements.push({ code: declaredCodes.value[0] ?? '', min_pct: 40 });
}

function removePassRequirement(index: number): void {
    state.passRequirements.splice(index, 1);
}

function applyExample(example: GradingSchemeExample): void {
    emit('apply-components', example.assessmentComponents);
    Object.assign(state, deserializeScheme(example.scheme));
    showGuide.value = false;
}

function resetToDefault(): void {
    Object.assign(state, createDefaultBuilderState());
}
</script>

<template>
    <div class="space-y-5">
        <!-- Enable toggle + guide -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <Button type="button" size="sm" :variant="!state.enabled ? 'default' : 'outline'" @click="resetToDefault"> Mặc định (trung bình có trọng số) </Button>
                <Button type="button" size="sm" :variant="state.enabled ? 'default' : 'outline'" @click="state.enabled = true"> Chấm điểm Metropolia </Button>
            </div>
            <Button type="button" size="sm" variant="ghost" @click="showGuide = true">
                <BookOpen class="mr-2 h-4 w-4" />
                Hướng dẫn
            </Button>
        </div>

        <p v-if="!state.enabled" class="text-muted-foreground text-sm">Dùng đường tính trung bình có trọng số theo % của các Assessment Components (lưu không kèm scheme).</p>

        <template v-if="state.enabled">
            <!-- Scale -->
            <div>
                <Label class="mb-2 block">Loại kết quả</Label>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" size="sm" :variant="state.scale === '0-5' ? 'default' : 'outline'" @click="state.scale = '0-5'"> Điểm số (0–5) </Button>
                    <Button type="button" size="sm" :variant="state.scale === 'pass_fail' ? 'default' : 'outline'" @click="state.scale = 'pass_fail'"> Đạt / Không đạt </Button>
                </div>
            </div>

            <!-- Engine -->
            <div>
                <Label class="mb-2 block">Cách tính</Label>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" size="sm" :variant="state.engine === 'metropolia_v1' ? 'default' : 'outline'" @click="state.engine = 'metropolia_v1'"> Cộng điểm quy đổi từng phần </Button>
                    <Button type="button" size="sm" :variant="state.engine === 'metropolia_v2' ? 'default' : 'outline'" @click="state.engine = 'metropolia_v2'"> Dùng công thức tổng </Button>
                </div>
                <p class="text-muted-foreground mt-2 text-xs">
                    {{
                        state.engine === 'metropolia_v1'
                            ? 'Quy đổi mỗi phần thành điểm rồi cộng lại. Hỗ trợ bậc thang/tuyến tính — phù hợp “FG = điểm phần A + điểm phần B”.'
                            : 'Một biểu thức số học (+ − × ÷) trên % thô của từng phần — phù hợp công thức trộn như (0.5*LAB + 0.3*QUIZ + 0.1*EXAM − 40)/10.'
                    }}
                </p>
            </div>

            <!-- No components hint -->
            <div v-if="codedComponents.length === 0" class="border-muted-foreground/30 text-muted-foreground rounded-md border border-dashed p-4 text-sm">
                Chưa có Assessment Component nào có <strong>mã (code)</strong>. Hãy thêm component và đặt code ở mục “Assessment Components” bên dưới — builder sẽ tự dùng lại.
            </div>

            <p v-if="codelessCount > 0" class="flex items-center gap-1 text-xs text-amber-600"><TriangleAlert class="h-4 w-4" /> {{ codelessCount }} component chưa có code nên không tham gia chấm điểm Metropolia.</p>

            <!-- metropolia_v1: one conversion editor per component -->
            <div v-if="state.engine === 'metropolia_v1' && codedComponents.length > 0" class="space-y-3">
                <Label>Quy đổi theo từng thành phần</Label>
                <div v-for="component in codedComponents" :key="component.code" class="space-y-3 rounded-md border p-3">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ component.label || component.code }}</span>
                        <span class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">{{ component.code }}</span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <Label class="text-xs">Cách quy đổi</Label>
                            <Select :model-value="state.configByCode[component.code].conversionType" @update:model-value="(value) => (state.configByCode[component.code].conversionType = value as RowConversionType)">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in CONVERSION_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="flex items-center gap-2 text-xs">
                                <input v-model="state.configByCode[component.code].gateEnabled" type="checkbox" class="h-4 w-4" />
                                Điều kiện đạt môn (gate)
                            </label>
                            <Input v-if="state.configByCode[component.code].gateEnabled" v-model.number="state.configByCode[component.code].gateMinPct" type="number" min="0" max="100" class="w-24" placeholder="min %" />
                        </div>
                    </div>

                    <!-- linear -->
                    <div v-if="state.configByCode[component.code].conversionType === 'linear'" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="space-y-1"><Label class="text-xs">Từ %</Label><Input v-model.number="state.configByCode[component.code].linearMinPct" type="number" min="0" max="100" /></div>
                        <div class="space-y-1"><Label class="text-xs">Đến %</Label><Input v-model.number="state.configByCode[component.code].linearMaxPct" type="number" min="0" max="100" /></div>
                        <div class="space-y-1"><Label class="text-xs">Điểm thấp</Label><Input v-model.number="state.configByCode[component.code].linearMinGrade" type="number" step="0.1" /></div>
                        <div class="space-y-1"><Label class="text-xs">Điểm cao</Label><Input v-model.number="state.configByCode[component.code].linearMaxGrade" type="number" step="0.1" /></div>
                    </div>

                    <!-- threshold -->
                    <div v-else-if="state.configByCode[component.code].conversionType === 'threshold'" class="space-y-2">
                        <div v-for="(step, stepIndex) in state.configByCode[component.code].steps" :key="stepIndex" class="flex items-end gap-2">
                            <div class="space-y-1"><Label class="text-xs">Từ %</Label><Input v-model.number="step.min_pct" type="number" min="0" max="100" class="w-28" /></div>
                            <div class="space-y-1"><Label class="text-xs">= Điểm</Label><Input v-model.number="step.grade" type="number" step="0.1" class="w-24" /></div>
                            <Button type="button" size="icon" variant="ghost" @click="removeStep(component.code, stepIndex)"><Trash2 class="h-4 w-4" /></Button>
                        </div>
                        <Button type="button" size="sm" variant="outline" @click="addStep(component.code)"><Plus class="mr-1 h-4 w-4" /> Thêm mốc</Button>
                    </div>

                    <!-- direct -->
                    <div v-else-if="state.configByCode[component.code].conversionType === 'direct'" class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1"><Label class="text-xs">Điểm tối đa</Label><Input v-model.number="state.configByCode[component.code].directMaxGrade" type="number" step="0.1" /></div>
                    </div>

                    <!-- pass_fail -->
                    <div v-else-if="state.configByCode[component.code].conversionType === 'pass_fail'" class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1"><Label class="text-xs">% tối thiểu để đạt</Label><Input v-model.number="state.configByCode[component.code].passFailMinPct" type="number" min="0" max="100" /></div>
                    </div>
                </div>
            </div>

            <!-- metropolia_v2: formula over the component codes -->
            <template v-if="state.engine === 'metropolia_v2' && codedComponents.length > 0">
                <div class="space-y-2">
                    <Label for="grading-formula">Công thức điểm cuối</Label>
                    <Input id="grading-formula" v-model="state.formula" class="font-mono" placeholder="(0.5*LAB + 0.3*QUIZ + 0.1*EXAM - 40) / 10" />
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-muted-foreground text-xs">Chèn biến:</span>
                        <Button v-for="code in declaredCodes" :key="code" type="button" size="sm" variant="secondary" class="font-mono" @click="insertVariable(code)">{{ code }}</Button>
                    </div>
                    <p class="text-muted-foreground text-xs">
                        Biến là <strong>% thô 0–100</strong> của từng phần. Chỉ dùng + − × ÷ và dấu ngoặc. Nếu tài liệu viết theo điểm đã nhân trọng số, hãy nhân hệ số vào (ví dụ <span class="font-mono">0.5*LAB</span>).
                    </p>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Điều kiện đạt môn (tuỳ chọn)</Label>
                        <Button type="button" size="sm" variant="outline" @click="addPassRequirement"><Plus class="mr-1 h-4 w-4" /> Thêm</Button>
                    </div>
                    <div v-for="(requirement, index) in state.passRequirements" :key="index" class="flex items-end gap-2">
                        <div class="space-y-1">
                            <Label class="text-xs">Component</Label>
                            <Select :model-value="requirement.code" @update:model-value="(value) => (requirement.code = String(value))">
                                <SelectTrigger class="w-40"><SelectValue placeholder="Chọn mã" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="code in declaredCodes" :key="code" :value="code">{{ code }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1"><Label class="text-xs">% tối thiểu</Label><Input v-model.number="requirement.min_pct" type="number" min="0" max="100" class="w-28" /></div>
                        <Button type="button" size="icon" variant="ghost" @click="removePassRequirement(index)"><Trash2 class="h-4 w-4" /></Button>
                    </div>
                </div>
            </template>
        </template>

        <GradingSchemeGuideModal v-model:open="showGuide" @apply="applyExample" />
    </div>
</template>
