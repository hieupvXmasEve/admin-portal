<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApiRequest';
import type { GradingScheme, GradingSchemePreviewResult } from '@/types/grading-scheme';
import { FlaskConical, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    scheme: GradingScheme | null;
}>();

const api = useApi();

const scores = ref<Record<string, number | null>>({});
const result = ref<GradingSchemePreviewResult | null>(null);
const errorMessages = ref<string[]>([]);
const isLoading = ref(false);

const componentCodes = computed<string[]>(() => (props.scheme?.components ?? []).map((component) => component.code).filter(Boolean));

// Keep the score inputs in sync with the scheme's declared components.
watch(
    componentCodes,
    (codes) => {
        const next: Record<string, number | null> = {};
        for (const code of codes) {
            next[code] = scores.value[code] ?? null;
        }
        scores.value = next;
        result.value = null;
        errorMessages.value = [];
    },
    { immediate: true },
);

function componentLabel(code: string): string {
    const match = props.scheme?.components?.find((component) => component.code === code);
    return match?.label ? `${match.label} (${code})` : code;
}

async function runPreview(): Promise<void> {
    if (!props.scheme) {
        return;
    }

    isLoading.value = true;
    errorMessages.value = [];
    result.value = null;

    try {
        const response = await api.post<GradingSchemePreviewResult>(route('api.syllabus_templates.grading-scheme.preview'), {
            grading_scheme: props.scheme,
            component_scores: scores.value,
        });

        const body = response.data?.value;

        if (body?.success && body.data) {
            result.value = body.data;
        } else {
            errorMessages.value = body?.errors ?? [body?.message ?? 'Preview failed'];
        }
    } catch {
        errorMessages.value = ['Could not reach the preview endpoint.'];
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="!scheme" class="text-muted-foreground text-sm">Select a Metropolia engine and apply a scheme to preview sample grades.</div>

        <template v-else>
            <div>
                <Label class="mb-2 block">Sample component scores (%)</Label>
                <div v-if="componentCodes.length === 0" class="text-muted-foreground text-sm">This scheme declares no components.</div>
                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <div v-for="code in componentCodes" :key="code" class="space-y-1">
                        <Label :for="`preview-score-${code}`" class="text-xs">{{ componentLabel(code) }}</Label>
                        <Input :id="`preview-score-${code}`" v-model.number="scores[code]" type="number" min="0" max="100" step="1" placeholder="0–100" />
                    </div>
                </div>
            </div>

            <Button type="button" size="sm" :disabled="isLoading" @click="runPreview">
                <FlaskConical class="mr-2 h-4 w-4" />
                {{ isLoading ? 'Calculating…' : 'Preview grade' }}
            </Button>

            <div v-if="errorMessages.length" class="border-destructive/40 bg-destructive/5 space-y-1 rounded-md border p-3">
                <p class="text-destructive flex items-center gap-1 text-sm font-medium"><XCircle class="h-4 w-4" /> Invalid scheme</p>
                <ul class="text-destructive list-inside list-disc text-xs">
                    <li v-for="(message, index) in errorMessages" :key="index">{{ message }}</li>
                </ul>
            </div>

            <div v-else-if="result" class="bg-muted/30 rounded-md border p-4">
                <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                    <div>
                        <span class="text-muted-foreground text-xs">Final grade</span>
                        <p class="text-2xl font-bold">{{ result.final_grade }}</p>
                    </div>
                    <div>
                        <span class="text-muted-foreground text-xs">Outcome</span>
                        <p class="text-sm font-medium" :class="result.passed ? 'text-emerald-600' : 'text-destructive'">
                            {{ result.passed ? 'Passed' : 'Not passed' }}
                        </p>
                    </div>
                    <div v-if="result.final_percentage != null">
                        <span class="text-muted-foreground text-xs">Diagnostic %</span>
                        <p class="text-sm">{{ result.final_percentage }}</p>
                    </div>
                    <div>
                        <span class="text-muted-foreground text-xs">Scale</span>
                        <p class="text-sm">{{ result.scale }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
