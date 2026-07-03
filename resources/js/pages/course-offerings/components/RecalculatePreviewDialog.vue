<script setup lang="ts">
/**
 * Preview-first Recalculate flow for a completed cockpit offering (issue 11,
 * ADR 0014). Replaces the old bare confirm-dialog: shows result changes,
 * EGC level changes, and per-student notification tier before anything is
 * persisted, with an optional "pull latest grades from Canvas first" step
 * (student-selectable; the recalculation itself always covers the whole
 * offering). Reuses CanvasScoreDiffTable for the pull's score diff.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { AlertTriangle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import CanvasScoreDiffTable, { type CanvasScoreDiff } from './CanvasScoreDiffTable.vue';

interface RosterStudent {
    id: number;
    student_id: string;
    full_name: string;
}

interface RecordChange {
    student_id: number;
    old_final_percentage: number;
    new_final_percentage: number;
    old_letter_grade: string | null;
    new_letter_grade: string;
    old_is_passed: boolean;
    new_is_passed: boolean;
}

interface NotificationTier {
    student_id: number;
    tier: 'strong' | 'light' | 'none';
}

interface RecalculateResult {
    course_code: string;
    total_students: number;
    record_changes: RecordChange[];
    notification_tiers: NotificationTier[];
    egc_progression: { processed: boolean; progressed?: unknown[]; failed_students?: unknown[] };
    canvas_preview: CanvasScoreDiff | null;
}

interface Props {
    open: boolean;
    courseOfferingId: number;
    hasMappedCanvasCourse: boolean;
    students: RosterStudent[];
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean]; applied: [] }>();

const api = useApi();
const step = ref<'config' | 'result'>('config');
const pullFromCanvas = ref(false);
const selectedPullIds = ref<number[]>([]);
const isLoading = ref(false);
const isApplying = ref(false);
const error = ref<string | null>(null);
const result = ref<RecalculateResult | null>(null);

const close = () => emit('update:open', false);

const studentName = (id: number): string => props.students.find((s) => s.id === id)?.full_name ?? `#${id}`;
const studentCode = (id: number): string => props.students.find((s) => s.id === id)?.student_id ?? '';

const isAllSelected = computed(() => props.students.length > 0 && selectedPullIds.value.length === props.students.length);
const toggleSelectAll = (checked: boolean | 'indeterminate') => {
    selectedPullIds.value = checked === true ? props.students.map((s) => s.id) : [];
};
const toggleStudent = (id: number, checked: boolean) => {
    selectedPullIds.value = checked ? [...selectedPullIds.value, id] : selectedPullIds.value.filter((s) => s !== id);
};

const pullStudentIds = computed<number[] | null>(() => (pullFromCanvas.value && selectedPullIds.value.length > 0 ? selectedPullIds.value : null));

const tierBadge = (tier: NotificationTier['tier']): { variant: 'default' | 'secondary' | 'outline'; label: string } => {
    if (tier === 'strong') return { variant: 'default', label: 'Notified' };
    if (tier === 'light') return { variant: 'secondary', label: 'Score updated' };
    return { variant: 'outline', label: 'No notification' };
};

const tierFor = (studentId: number): NotificationTier['tier'] => result.value?.notification_tiers.find((t) => t.student_id === studentId)?.tier ?? 'none';

const generatePreview = async () => {
    isLoading.value = true;
    error.value = null;
    result.value = null;

    const { data } = await api.post<RecalculateResult>(route('api.course-offerings.recalculate.preview', { courseOffering: props.courseOfferingId }), {
        pull_student_ids: pullStudentIds.value,
    });

    isLoading.value = false;

    if (data.value?.success && data.value.data) {
        result.value = data.value.data;
        step.value = 'result';
    } else {
        error.value = data.value?.message || 'Failed to preview recalculate.';
    }
};

const applyRecalculate = async () => {
    isApplying.value = true;
    error.value = null;

    const { data } = await api.post(route('api.course-offerings.recalculate.apply', { courseOffering: props.courseOfferingId }), {
        pull_student_ids: pullStudentIds.value,
    });

    isApplying.value = false;

    if (data.value?.success) {
        emit('applied');
        close();
    } else {
        error.value = data.value?.message || 'Failed to recalculate course results.';
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            step.value = 'config';
            pullFromCanvas.value = false;
            selectedPullIds.value = [];
            result.value = null;
            error.value = null;
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="!max-h-[85vh] !max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Recalculate course results — preview</DialogTitle>
                <DialogDescription> Nothing is saved until you apply. Grade changes and notifications are shown below first. </DialogDescription>
            </DialogHeader>

            <div v-if="error" class="text-destructive flex items-center gap-2 py-2 text-sm">
                <AlertTriangle class="h-4 w-4 shrink-0" />
                {{ error }}
            </div>

            <!-- Step 1: optional Canvas pull configuration -->
            <div v-if="step === 'config'" class="space-y-4">
                <div v-if="hasMappedCanvasCourse" class="space-y-3 rounded-md border p-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <Checkbox :model-value="pullFromCanvas" @update:model-value="(v) => (pullFromCanvas = v === true)" />
                        Pull latest grades from Canvas first
                    </label>
                    <div v-if="pullFromCanvas" class="max-h-56 space-y-1 overflow-y-auto rounded border p-2">
                        <label class="flex items-center gap-2 border-b pb-1 text-xs font-medium">
                            <Checkbox :model-value="isAllSelected" @update:model-value="toggleSelectAll" />
                            Select all
                        </label>
                        <label v-for="student in students" :key="student.id" class="flex items-center gap-2 py-0.5 text-sm">
                            <Checkbox :model-value="selectedPullIds.includes(student.id)" @update:model-value="(v) => toggleStudent(student.id, v === true)" />
                            {{ student.full_name }} <span class="text-muted-foreground text-xs">{{ student.student_id }}</span>
                        </label>
                    </div>
                </div>
                <p v-else class="text-muted-foreground text-sm">This offering has no mapped Canvas course — recalculate will use current scores.</p>
            </div>

            <!-- Step 2: preview result -->
            <div v-else-if="isLoading" class="space-y-3 py-4">
                <Skeleton class="h-6 w-64" />
                <Skeleton class="h-40 w-full" />
            </div>

            <div v-else-if="result" class="space-y-6">
                <CanvasScoreDiffTable v-if="result.canvas_preview" :diff="result.canvas_preview" />

                <div v-if="result.record_changes.length" class="space-y-2">
                    <h4 class="text-sm font-semibold">Result changes</h4>
                    <div class="overflow-hidden rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Student</TableHead>
                                    <TableHead class="text-right">Grade</TableHead>
                                    <TableHead class="text-right">Status</TableHead>
                                    <TableHead class="text-right">Notification</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="change in result.record_changes" :key="change.student_id">
                                    <TableCell>
                                        <div class="text-sm">{{ studentName(change.student_id) }}</div>
                                        <div class="text-muted-foreground text-xs">{{ studentCode(change.student_id) }}</div>
                                    </TableCell>
                                    <TableCell class="text-right text-sm">
                                        {{ change.old_final_percentage.toFixed(1) }}% ({{ change.old_letter_grade ?? '—' }}) → {{ change.new_final_percentage.toFixed(1) }}% ({{ change.new_letter_grade }})
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Badge v-if="change.old_is_passed !== change.new_is_passed" variant="destructive">
                                            {{ change.old_is_passed ? 'Pass → Fail' : 'Fail → Pass' }}
                                        </Badge>
                                        <Badge v-else variant="outline">{{ change.new_is_passed ? 'Pass' : 'Fail' }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Badge :variant="tierBadge(tierFor(change.student_id)).variant">{{ tierBadge(tierFor(change.student_id)).label }}</Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
                <p v-else class="text-muted-foreground py-6 text-center text-sm">No result changes for any student.</p>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isApplying" @click="close">Cancel</Button>
                <Button v-if="step === 'config'" :disabled="isLoading" @click="generatePreview">
                    {{ isLoading ? 'Loading…' : 'Preview recalculate' }}
                </Button>
                <template v-else>
                    <Button variant="outline" :disabled="isApplying" @click="step = 'config'">Back</Button>
                    <Button :disabled="isApplying || !result" @click="applyRecalculate">
                        {{ isApplying ? 'Recalculating…' : 'Apply recalculate' }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
