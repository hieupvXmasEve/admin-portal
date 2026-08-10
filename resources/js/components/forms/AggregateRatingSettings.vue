<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AggregateConfig, FormSection, Question } from '@/types/forms';
import { router } from '@inertiajs/vue3';
import { Settings2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    formId: number;
    sections: FormSection[];
    questions: Question[];
    aggregateConfig?: AggregateConfig | null;
    canConfigure: boolean;
}

const props = defineProps<Props>();

const UNGROUPED_KEY = '__ungrouped__';

const isCustom = computed(() => !!props.aggregateConfig);

// Rating questions grouped by section (sectionless -> "Ungrouped questions").
const ratingGroups = computed(() => {
    const bySection = new Map<string | number, { title: string; questions: Question[] }>();

    for (const question of props.questions) {
        if (question.type !== 'rating') continue;

        const key = question.section_id ?? UNGROUPED_KEY;
        if (!bySection.has(key)) {
            const section = props.sections.find((s) => s.id === question.section_id);
            bySection.set(key, { title: section?.title ?? 'Ungrouped questions', questions: [] });
        }
        bySection.get(key)!.questions.push(question);
    }

    return Array.from(bySection.values());
});

const editing = ref(isCustom.value);
const selectedCodes = ref<Set<string>>(new Set(props.aggregateConfig?.overall.question_codes ?? []));
const positiveMin = ref(props.aggregateConfig?.overall.thresholds.positive_min ?? 4);
const negativeMax = ref(props.aggregateConfig?.overall.thresholds.negative_max ?? 2);
const saving = ref(false);
const errors = ref<Record<string, string>>({});

const thresholdHelper = computed(() => {
    const p = positiveMin.value;
    const n = negativeMax.value;
    const neutralLabel = n + 1 === p - 1 ? `${n + 1}` : `${n + 1}..${p - 1}`;
    return `≥${p} Positive, ${neutralLabel} Neutral, ≤${n} Negative`;
});

const isSectionFullySelected = (group: { questions: Question[] }) => group.questions.every((q) => selectedCodes.value.has(q.code));
const isSectionPartiallySelected = (group: { questions: Question[] }) => group.questions.some((q) => selectedCodes.value.has(q.code)) && !isSectionFullySelected(group);
const sectionCheckboxState = (group: { questions: Question[] }): boolean | 'indeterminate' =>
    isSectionFullySelected(group) ? true : isSectionPartiallySelected(group) ? 'indeterminate' : false;

const toggleSection = (group: { questions: Question[] }, checked: boolean) => {
    for (const q of group.questions) {
        if (checked) selectedCodes.value.add(q.code);
        else selectedCodes.value.delete(q.code);
    }
    selectedCodes.value = new Set(selectedCodes.value);
};

const toggleQuestion = (code: string, checked: boolean) => {
    if (checked) selectedCodes.value.add(code);
    else selectedCodes.value.delete(code);
    selectedCodes.value = new Set(selectedCodes.value);
};

const canSave = computed(() => selectedCodes.value.size > 0 && positiveMin.value > negativeMax.value);

const startCustomizing = () => {
    editing.value = true;
    if (selectedCodes.value.size === 0) {
        // Default to all rating questions when opening for the first time.
        for (const group of ratingGroups.value) {
            for (const q of group.questions) selectedCodes.value.add(q.code);
        }
        selectedCodes.value = new Set(selectedCodes.value);
    }
};

const save = () => {
    if (!canSave.value) return;
    saving.value = true;
    errors.value = {};

    router.put(
        route('forms.admin.aggregate-config.update', props.formId),
        {
            overall: {
                question_codes: Array.from(selectedCodes.value),
                thresholds: { positive_min: positiveMin.value, negative_max: negativeMax.value },
            },
        },
        {
            preserveScroll: true,
            onError: (e) => {
                errors.value = e as Record<string, string>;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
};

const resetToDefault = () => {
    if (!confirm('Reset to the default Overall Rating (average of all rating questions, 4/2 thresholds)?')) return;

    router.delete(route('forms.admin.aggregate-config.destroy', props.formId), {
        preserveScroll: true,
        onSuccess: () => {
            selectedCodes.value = new Set();
            positiveMin.value = 4;
            negativeMax.value = 2;
            editing.value = false;
        },
    });
};
</script>

<template>
    <Card v-if="canConfigure">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Settings2 class="h-4 w-4" />
                Overall Rating Formula
                <Badge v-if="isCustom" variant="secondary">Custom</Badge>
            </CardTitle>
            <CardDescription>Choose which rating questions feed the Overall Rating KPI on the results page, and its positive/negative thresholds.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <div v-if="!editing" class="flex items-center justify-between rounded-lg border p-4">
                <p class="text-muted-foreground text-sm">Using default: average of all rating questions (≥4 Positive, 3 Neutral, ≤2 Negative).</p>
                <Button variant="outline" size="sm" @click="startCustomizing">Customize</Button>
            </div>

            <template v-else>
                <div class="space-y-4">
                    <div v-for="group in ratingGroups" :key="group.title" class="space-y-2 rounded-lg border p-3">
                        <div class="flex items-center gap-2 border-b pb-2">
                            <Checkbox :model-value="sectionCheckboxState(group)" @update:model-value="(v) => toggleSection(group, v === true)" />
                            <Label class="font-medium">{{ group.title }}</Label>
                        </div>
                        <div v-for="q in group.questions" :key="q.code" class="ml-6 flex items-center gap-2">
                            <Checkbox :model-value="selectedCodes.has(q.code)" @update:model-value="(v) => toggleQuestion(q.code, Boolean(v))" />
                            <Label class="text-sm font-normal">{{ q.text }}</Label>
                        </div>
                    </div>
                    <div v-if="ratingGroups.length === 0" class="text-muted-foreground py-4 text-center text-sm">No rating questions in the current published version.</div>
                    <p v-if="errors['overall.question_codes']" class="text-destructive text-sm">{{ errors['overall.question_codes'] }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Positive threshold (≥)</Label>
                        <Input v-model.number="positiveMin" type="number" min="1" max="5" />
                    </div>
                    <div class="space-y-2">
                        <Label>Negative threshold (≤)</Label>
                        <Input v-model.number="negativeMax" type="number" min="1" max="5" />
                    </div>
                </div>
                <p v-if="errors['overall.thresholds.positive_min']" class="text-destructive text-sm">{{ errors['overall.thresholds.positive_min'] }}</p>
                <p class="text-muted-foreground text-sm">{{ thresholdHelper }}</p>

                <div class="flex items-center justify-between">
                    <Button variant="ghost" size="sm" @click="resetToDefault">Reset to default</Button>
                    <Button size="sm" :disabled="!canSave || saving" @click="save">{{ saving ? 'Saving...' : 'Save configuration' }}</Button>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
