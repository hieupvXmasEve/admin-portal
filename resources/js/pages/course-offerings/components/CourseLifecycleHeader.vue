<script setup lang="ts">
/**
 * CourseLifecycleHeader — renders the backend-derived operational state for
 * the Course Offering Cockpit (ADR 0013): lifecycle stage, readiness
 * blockers, and permission-gated actions. The frontend never infers
 * operational state; it only renders the `operational_state` contract.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { LifecycleStage, OperationalState } from '@/types/operational-state';
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    operationalState: OperationalState;
}

const props = defineProps<Props>();

const stages: { key: LifecycleStage; label: string }[] = [
    { key: 'setup', label: 'Setup' },
    { key: 'registration', label: 'Registration' },
    { key: 'teaching', label: 'Teaching' },
    { key: 'grading', label: 'Grading' },
    { key: 'completed', label: 'Completed' },
];

const stageOrder: Record<LifecycleStage, number> = {
    setup: 0,
    registration: 1,
    teaching: 2,
    grading: 3,
    completed: 4,
    cancelled: -1,
};

const lifecycleStage = computed<LifecycleStage>(() => props.operationalState.lifecycle_stage);

const currentStageIndex = computed(() => stageOrder[lifecycleStage.value] ?? 0);

const getStageState = (stageKey: LifecycleStage): 'completed' | 'active' | 'pending' => {
    if (lifecycleStage.value === 'cancelled') return 'pending';
    const idx = stageOrder[stageKey];
    const current = currentStageIndex.value;
    if (idx < current) return 'completed';
    if (idx === current) return 'active';
    return 'pending';
};

const sessionProgressText = computed(() => {
    const { total, completed } = props.operationalState.session_progress;
    if (total === 0) return null;
    if (lifecycleStage.value === 'teaching' || lifecycleStage.value === 'grading') {
        return `${completed}/${total} sessions completed`;
    }
    return null;
});

const blockers = computed(() => props.operationalState.readiness_blockers);

// Backend omits actions the user lacks permission for, so a missing
// finalize action means the button must not render at all.
const finalizeAction = computed(() => props.operationalState.available_actions.find((action) => action.action === 'finalize'));

const finalizeBlockedExplanation = computed(() => {
    if (!finalizeAction.value || finalizeAction.value.allowed) return null;
    const messages = blockers.value
        .filter((blocker) => finalizeAction.value!.blocked_by.includes(blocker.code))
        .map((blocker) => blocker.message);
    return messages.join(' · ');
});
</script>

<template>
    <div class="space-y-3">
        <!-- Lifecycle Progress Bar -->
        <div class="rounded-lg border p-4">
            <!-- Cancelled state -->
            <div v-if="lifecycleStage === 'cancelled'" class="flex items-center gap-3">
                <Badge variant="destructive" class="text-sm">Cancelled</Badge>
                <div class="flex flex-1 items-center gap-1">
                    <div v-for="stage in stages" :key="stage.key" class="flex flex-1 flex-col items-center gap-1">
                        <div class="h-2 w-full rounded-full bg-gray-200"></div>
                        <span class="text-xs text-gray-400">{{ stage.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Normal state -->
            <div v-else class="space-y-3">
                <div class="flex items-center">
                    <template v-for="(stage, index) in stages" :key="stage.key">
                        <!-- Stage indicator -->
                        <div class="flex flex-1 flex-col items-center gap-1.5">
                            <!-- Circle indicator -->
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-all"
                                :class="{
                                    'bg-primary text-primary-foreground ring-primary/30 ring-2 ring-offset-1': getStageState(stage.key) === 'active',
                                    'bg-green-500 text-white': getStageState(stage.key) === 'completed',
                                    'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400': getStageState(stage.key) === 'pending',
                                }"
                            >
                                <span v-if="getStageState(stage.key) === 'completed'">✓</span>
                                <span v-else>{{ index + 1 }}</span>
                            </div>
                            <span
                                class="text-xs font-medium"
                                :class="{
                                    'text-primary': getStageState(stage.key) === 'active',
                                    'text-green-600': getStageState(stage.key) === 'completed',
                                    'text-muted-foreground': getStageState(stage.key) === 'pending',
                                }"
                            >
                                {{ stage.label }}
                            </span>
                        </div>
                        <!-- Connector line between stages -->
                        <div
                            v-if="index < stages.length - 1"
                            class="mb-6 h-0.5 flex-1 rounded-full transition-all duration-300"
                            :class="{
                                'bg-green-500': currentStageIndex > stageOrder[stage.key],
                                'bg-primary/30': currentStageIndex === stageOrder[stage.key],
                                'bg-gray-200 dark:bg-gray-700': currentStageIndex < stageOrder[stage.key],
                            }"
                        ></div>
                    </template>
                </div>

                <!-- Progress text -->
                <p v-if="sessionProgressText" class="text-muted-foreground text-center text-xs">
                    {{ sessionProgressText }}
                </p>
            </div>
        </div>

        <!-- Readiness Blockers + Finalize (blocked) -->
        <div v-if="blockers.length > 0" class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-900/20">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="flex min-w-0 flex-col gap-2.5">
                    <div v-for="blocker in blockers" :key="blocker.code" class="text-sm text-yellow-800 dark:text-yellow-200">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4 shrink-0 text-yellow-600" />
                            <span>{{ blocker.message }}</span>
                        </div>
                        <div v-if="blocker.references.length > 0" class="mt-1.5 ml-6 flex flex-wrap gap-1.5">
                            <Badge
                                v-for="reference in blocker.references"
                                :key="`${reference.type}-${reference.id}`"
                                variant="outline"
                                class="border-yellow-300 bg-yellow-100/60 text-xs font-normal text-yellow-800 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-200"
                            >
                                {{ reference.label }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div v-if="finalizeAction" class="flex shrink-0 flex-col items-start gap-1 md:items-end">
                    <Button size="sm" :disabled="!finalizeAction.allowed" :title="finalizeBlockedExplanation ?? undefined">
                        {{ finalizeAction.label }}
                    </Button>
                    <p v-if="!finalizeAction.allowed" class="max-w-60 text-right text-xs text-yellow-700 dark:text-yellow-300">
                        Resolve the readiness blockers to finalize this course.
                    </p>
                </div>
            </div>
        </div>

        <!-- Ready to finalize -->
        <div
            v-else-if="finalizeAction"
            class="flex items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-900/20"
        >
            <div class="flex items-center gap-2 text-sm text-green-800 dark:text-green-200">
                <CheckCircle2 class="h-4 w-4 shrink-0 text-green-600" />
                <span>No readiness blockers — this course can be finalized.</span>
            </div>
            <Button size="sm" :disabled="!finalizeAction.allowed">
                {{ finalizeAction.label }}
            </Button>
        </div>
    </div>
</template>
