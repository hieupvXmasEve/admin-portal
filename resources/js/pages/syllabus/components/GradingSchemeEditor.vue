<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { GradingScheme, GradingSchemeEngine } from '@/types/grading-scheme';
import { CheckCircle2, Code2, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    modelValue: GradingScheme | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: GradingScheme | null];
}>();

const ENGINE_OPTIONS: Array<{ value: GradingSchemeEngine; label: string }> = [
    { value: 'default', label: 'Default weighted %' },
    { value: 'metropolia_v1', label: 'Metropolia v1' },
    { value: 'metropolia_v2', label: 'Metropolia v2 (formula)' },
];

const SAMPLE_SCHEMES: Record<Exclude<GradingSchemeEngine, 'default'>, GradingScheme> = {
    metropolia_v1: {
        engine: 'metropolia_v1',
        version: 1,
        scale: '0-5',
        components: [
            { code: 'ASSIGNMENT', label: 'Assignments', gate: { min_pct: 40 } },
            {
                code: 'EXAM',
                label: 'Exam',
                gate: { min_pct: 40 },
                conversion: { type: 'linear', min_pct: 40, max_pct: 88, min_grade: 1, max_grade: 5 },
            },
        ],
    },
    metropolia_v2: {
        engine: 'metropolia_v2',
        version: 1,
        scale: '0-5',
        formula: '(LAB + QUIZ + EXAM / 2 - 40) / 10',
        rounding_stage: 'after_total',
        clamp_min: 0,
        clamp_max: 5,
        components: [
            { code: 'LAB', label: 'Labs' },
            { code: 'QUIZ', label: 'Quizzes' },
            { code: 'EXAM', label: 'Final Exam' },
        ],
    },
};

const selectedEngine = ref<GradingSchemeEngine>(props.modelValue?.engine ?? 'default');
const jsonText = ref(props.modelValue ? JSON.stringify(props.modelValue, null, 2) : '');
const parseError = ref<string | null>(null);
const isApplied = ref(props.modelValue !== null);

watch(
    () => props.modelValue,
    (value) => {
        selectedEngine.value = value?.engine ?? 'default';
        jsonText.value = value ? JSON.stringify(value, null, 2) : '';
        isApplied.value = value !== null;
    },
);

function selectEngine(engine: GradingSchemeEngine): void {
    selectedEngine.value = engine;
    parseError.value = null;

    if (engine === 'default') {
        jsonText.value = '';
        isApplied.value = true;
        emit('update:modelValue', null);
        return;
    }

    // Seed the editor with a sample only when there is no JSON to preserve.
    if (jsonText.value.trim() === '') {
        jsonText.value = JSON.stringify(SAMPLE_SCHEMES[engine], null, 2);
    }
    isApplied.value = false;
}

function applyJson(): void {
    parseError.value = null;

    if (selectedEngine.value === 'default') {
        isApplied.value = true;
        emit('update:modelValue', null);
        return;
    }

    try {
        const parsed = JSON.parse(jsonText.value) as GradingScheme;
        isApplied.value = true;
        emit('update:modelValue', parsed);
    } catch (error) {
        isApplied.value = false;
        parseError.value = error instanceof Error ? error.message : 'Invalid JSON';
    }
}

function loadSample(): void {
    if (selectedEngine.value === 'default') {
        return;
    }
    jsonText.value = JSON.stringify(SAMPLE_SCHEMES[selectedEngine.value], null, 2);
    parseError.value = null;
    isApplied.value = false;
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <Label class="mb-2 block">Grading engine</Label>
            <div class="flex flex-wrap gap-2">
                <Button v-for="option in ENGINE_OPTIONS" :key="option.value" type="button" size="sm" :variant="selectedEngine === option.value ? 'default' : 'outline'" @click="selectEngine(option.value)">
                    {{ option.label }}
                </Button>
            </div>
            <p class="text-muted-foreground mt-2 text-sm">Default uses the existing weighted-percentage path (saved as no scheme). Metropolia engines run the configured rule scheme.</p>
        </div>

        <div v-if="selectedEngine !== 'default'" class="space-y-2">
            <div class="flex items-center justify-between">
                <Label for="grading-scheme-json" class="flex items-center gap-2"> <Code2 class="h-4 w-4" /> Scheme JSON </Label>
                <Button type="button" size="sm" variant="ghost" @click="loadSample">Load sample</Button>
            </div>

            <Textarea id="grading-scheme-json" v-model="jsonText" rows="14" spellcheck="false" class="font-mono text-xs" placeholder="Paste or edit the grading scheme JSON" />

            <div class="flex items-center gap-3">
                <Button type="button" size="sm" variant="secondary" @click="applyJson">Apply JSON</Button>
                <span v-if="parseError" class="text-destructive flex items-center gap-1 text-sm"> <XCircle class="h-4 w-4" /> {{ parseError }} </span>
                <span v-else-if="isApplied" class="flex items-center gap-1 text-sm text-emerald-600"> <CheckCircle2 class="h-4 w-4" /> Applied </span>
            </div>
        </div>
    </div>
</template>
