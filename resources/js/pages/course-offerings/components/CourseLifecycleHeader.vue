<script setup lang="ts">
/**
 * CourseLifecycleHeader — displays lifecycle progress bar (5 stages) and warnings bar.
 * Lifecycle stage is computed purely from frontend data — no extra API calls.
 */
import { Badge } from '@/components/ui/badge';
import type { ClassSession, CourseOffering } from '@/types/models';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    courseOffering: CourseOffering & {
        class_sessions?: ClassSession[];
    };
}

const props = defineProps<Props>();

// ---- Lifecycle stage computation ----
type LifecycleStage = 'setup' | 'registration' | 'teaching' | 'grading' | 'completed' | 'cancelled';

const lifecycleStage = computed<LifecycleStage>(() => {
    const status = props.courseOffering.course_status;
    if (status === 'cancelled') return 'cancelled';
    if (status === 'completed') return 'completed';

    const sessions = props.courseOffering.class_sessions || [];
    const allCompleted = sessions.length > 0 && sessions.every((s) => s.status === 'completed');
    if (allCompleted) return 'grading';

    const hasStarted = sessions.some((s) => s.status === 'in_progress' || s.status === 'completed');
    if (hasStarted) return 'teaching';

    // Has sessions scheduled but not started, or enrollment > 0
    if (sessions.length > 0 || props.courseOffering.current_enrollment > 0) return 'registration';

    return 'setup';
});

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
    const sessions = props.courseOffering.class_sessions || [];
    if (sessions.length === 0) return null;
    const completedCount = sessions.filter((s) => s.status === 'completed').length;
    if (lifecycleStage.value === 'teaching' || lifecycleStage.value === 'grading') {
        return `${completedCount}/${sessions.length} sessions completed`;
    }
    return null;
});

// ---- Warnings computation ----
interface Warning {
    id: string;
    message: string;
}

const warnings = computed<Warning[]>(() => {
    const result: Warning[] = [];
    const sessions = props.courseOffering.class_sessions || [];

    // Sessions without attendance
    const sessionsWithoutAttendance = sessions.filter((s) => s.status === 'completed' && (s.attendance_percentage === null || s.attendance_percentage === 0));
    if (sessionsWithoutAttendance.length > 0) {
        result.push({
            id: 'missing-attendance',
            message: `${sessionsWithoutAttendance.length} session(s) completed without attendance records`,
        });
    }

    // Survey not created (only warn during/after teaching)
    const hasStartedTeaching = sessions.some((s) => s.status === 'in_progress' || s.status === 'completed');
    const hasSurvey = props.courseOffering.form_targets && props.courseOffering.form_targets.length > 0;
    if (hasStartedTeaching && !hasSurvey) {
        result.push({
            id: 'no-survey',
            message: 'Course survey not yet created',
        });
    }

    // All sessions completed but course not finalized
    const allCompleted = sessions.length > 0 && sessions.every((s) => s.status === 'completed');
    if (allCompleted && props.courseOffering.course_status !== 'completed') {
        result.push({
            id: 'not-finalized',
            message: 'All sessions completed — ready to mark course as completed',
        });
    }

    return result;
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
            <div v-else class="space-y-2">
                <div class="flex items-center gap-1">
                    <template v-for="(stage, index) in stages" :key="stage.key">
                        <!-- Stage indicator -->
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <!-- Progress segment (bar before this stage, skip for first) -->
                            <div v-if="index > 0" class="mb-1 hidden"></div>
                            <!-- Circle indicator -->
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-all"
                                :class="{
                                    'bg-primary text-primary-foreground': getStageState(stage.key) === 'active',
                                    'bg-green-500 text-white': getStageState(stage.key) === 'completed',
                                    'bg-gray-200 text-gray-500': getStageState(stage.key) === 'pending',
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
                            class="mb-5 h-0.5 flex-1 rounded-full transition-all"
                            :class="{
                                'bg-green-500': currentStageIndex > stageOrder[stage.key],
                                'bg-primary/40': currentStageIndex === stageOrder[stage.key],
                                'bg-gray-200': currentStageIndex < stageOrder[stage.key],
                            }"
                        ></div>
                    </template>
                </div>

                <!-- Progress text -->
                <div v-if="sessionProgressText" class="text-muted-foreground text-center text-sm">
                    {{ sessionProgressText }}
                </div>
            </div>
        </div>

        <!-- Warnings Bar (hidden when no warnings) -->
        <div v-if="warnings.length > 0" class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-900/20">
            <div class="flex flex-col gap-2">
                <div v-for="warning in warnings" :key="warning.id" class="flex items-center gap-2 text-sm text-yellow-800 dark:text-yellow-200">
                    <AlertTriangle class="h-4 w-4 shrink-0 text-yellow-600" />
                    <span>{{ warning.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
