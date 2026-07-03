<script setup lang="ts">
/**
 * Preview/apply dialog for the cockpit Scores tab "Sync from Canvas" action
 * (issue 10). Fetches a dry-run diff on open, then re-fetches Canvas at
 * apply time (never echoes the preview payload back) — matching ADR 0014.
 * Kept generic (courseOfferingId + studentIds in, "synced" event out) so
 * issue 11's Recalculate preview can reuse the diff-table layout.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { AlertTriangle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface CellChange {
    student_id: number;
    student_code: string;
    student_name: string;
    component_name: string;
    detail_name: string;
    old_points: number | null;
    new_points: number;
    old_percentage: number | null;
    new_percentage: number;
    is_disputed: boolean;
}

interface CourseTotalChange {
    student_id: number;
    student_code: string;
    student_name: string;
    old_percentage: number | null;
    new_percentage: number;
}

interface UnmatchedStudent {
    student_id: number;
    student_code: string;
    student_name: string;
    reason: string;
}

interface PreviewResult {
    summary: { total_changes: number; students_changed: number; students_unchanged: number; students_unmatched: number };
    changes: CellChange[];
    course_totals: CourseTotalChange[];
    unmatched: UnmatchedStudent[];
}

interface Props {
    open: boolean;
    courseOfferingId: number;
    studentIds: number[];
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean]; synced: [] }>();

const api = useApi();
const isLoading = ref(false);
const isApplying = ref(false);
const error = ref<string | null>(null);
const preview = ref<PreviewResult | null>(null);

const close = () => emit('update:open', false);

const fmt = (value: number | null): string => (value === null ? '—' : value.toFixed(1));

const loadPreview = async () => {
    isLoading.value = true;
    error.value = null;
    preview.value = null;

    const { data } = await api.post<PreviewResult>(
        route('api.course-offerings.sync-grades.preview', { courseOffering: props.courseOfferingId }),
        { student_ids: props.studentIds },
    );

    if (data.value?.success && data.value.data) {
        preview.value = data.value.data;
    } else {
        error.value = data.value?.message || 'Failed to preview Canvas grade sync.';
    }

    isLoading.value = false;
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) loadPreview();
    },
);

const applySync = async () => {
    isApplying.value = true;
    error.value = null;

    const { data } = await api.post(route('api.course-offerings.sync-grades', { courseOffering: props.courseOfferingId }), {
        student_ids: props.studentIds,
    });

    isApplying.value = false;

    if (data.value?.success) {
        emit('synced');
        close();
    } else {
        error.value = data.value?.message || 'Failed to sync Canvas grades.';
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Sync from Canvas — preview</DialogTitle>
                <DialogDescription> Canvas is re-fetched when you apply; this preview commits nothing. </DialogDescription>
            </DialogHeader>

            <div v-if="isLoading" class="space-y-3 py-4">
                <Skeleton class="h-6 w-64" />
                <Skeleton class="h-40 w-full" />
            </div>

            <div v-else-if="error" class="text-destructive flex items-center gap-2 py-6 text-sm">
                <AlertTriangle class="h-4 w-4 shrink-0" />
                {{ error }}
            </div>

            <div v-else-if="preview" class="space-y-4">
                <p class="text-muted-foreground text-sm">
                    <span class="text-foreground font-semibold">{{ preview.summary.total_changes }}</span> change(s) across
                    <span class="text-foreground font-semibold">{{ preview.summary.students_changed }}</span> student(s) ·
                    {{ preview.summary.students_unchanged }} unchanged · {{ preview.summary.students_unmatched }} unmatched
                </p>

                <div v-if="preview.changes.length" class="space-y-2">
                    <h4 class="text-sm font-semibold">Component score changes</h4>
                    <div class="max-h-64 overflow-y-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Student</TableHead>
                                    <TableHead>Component / Detail</TableHead>
                                    <TableHead class="text-right">Old</TableHead>
                                    <TableHead class="text-right">New</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(change, index) in preview.changes" :key="`${change.student_id}-${change.detail_name}-${index}`">
                                    <TableCell>
                                        <div class="font-medium">{{ change.student_name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ change.student_code }}</div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="text-sm">{{ change.component_name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ change.detail_name }}</div>
                                        <Badge v-if="change.is_disputed" variant="destructive" class="mt-1 text-[10px]">Disputed</Badge>
                                    </TableCell>
                                    <TableCell class="text-right">{{ fmt(change.old_points) }} pts ({{ fmt(change.old_percentage) }}%)</TableCell>
                                    <TableCell class="text-right font-semibold">{{ fmt(change.new_points) }} pts ({{ fmt(change.new_percentage) }}%)</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div v-if="preview.course_totals.length" class="space-y-2">
                    <h4 class="text-sm font-semibold">Course total changes</h4>
                    <div class="max-h-48 overflow-y-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Student</TableHead>
                                    <TableHead class="text-right">Old total</TableHead>
                                    <TableHead class="text-right">New total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="total in preview.course_totals" :key="total.student_id">
                                    <TableCell>
                                        <div class="font-medium">{{ total.student_name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ total.student_code }}</div>
                                    </TableCell>
                                    <TableCell class="text-right">{{ fmt(total.old_percentage) }}%</TableCell>
                                    <TableCell class="text-right font-semibold">{{ fmt(total.new_percentage) }}%</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div v-if="preview.unmatched.length" class="space-y-2">
                    <h4 class="text-sm font-semibold">Unmatched students</h4>
                    <div class="space-y-1">
                        <div v-for="student in preview.unmatched" :key="student.student_id" class="flex items-center justify-between rounded-md border p-2 text-sm">
                            <div>
                                <span class="font-medium">{{ student.student_name }}</span>
                                <span class="text-muted-foreground ml-1 text-xs">{{ student.student_code }}</span>
                            </div>
                            <Badge variant="outline">{{ student.reason }}</Badge>
                        </div>
                    </div>
                </div>

                <p v-if="preview.summary.total_changes === 0" class="text-muted-foreground py-6 text-center text-sm">No changes to sync for the selected students.</p>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isApplying" @click="close">Cancel</Button>
                <Button :disabled="isLoading || isApplying || !preview || preview.summary.total_changes === 0" @click="applySync">
                    {{ isApplying ? 'Syncing…' : `Sync ${preview?.summary.total_changes ?? 0} changes for ${preview?.summary.students_changed ?? 0} students` }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
