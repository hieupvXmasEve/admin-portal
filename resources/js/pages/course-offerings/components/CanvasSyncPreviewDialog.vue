<script setup lang="ts">
/**
 * Preview/apply dialog for the cockpit Scores tab "Sync from Canvas" action
 * (issue 10). Fetches a dry-run diff on open, then re-fetches Canvas at
 * apply time (never echoes the preview payload back) — matching ADR 0014.
 * Kept generic (courseOfferingId + studentIds in, "synced" event out) so
 * issue 11's Recalculate preview can reuse the diff-table layout.
 *
 * Rows are grouped by student (one accordion item each) instead of a flat
 * cell list — easier to scan when a student has several changed details.
 * The course total is always shown per student (even unchanged) so staff
 * can see the value Canvas is reporting, not just what would change.
 */
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { AlertTriangle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
    changed: boolean;
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

interface StudentGroup {
    student_id: number;
    student_code: string;
    student_name: string;
    cellChanges: CellChange[];
    courseTotal: CourseTotalChange | null;
    hasChanges: boolean;
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

// One accordion row per matched student — cell changes and the course total
// both key off student_id, so they always merge onto the same group.
const studentGroups = computed<StudentGroup[]>(() => {
    if (!preview.value) return [];

    const groups = new Map<number, StudentGroup>();
    const groupFor = (student_id: number, student_code: string, student_name: string): StudentGroup => {
        let group = groups.get(student_id);
        if (!group) {
            group = { student_id, student_code, student_name, cellChanges: [], courseTotal: null, hasChanges: false };
            groups.set(student_id, group);
        }
        return group;
    };

    for (const change of preview.value.changes) {
        const group = groupFor(change.student_id, change.student_code, change.student_name);
        group.cellChanges.push(change);
        group.hasChanges = true;
    }
    for (const total of preview.value.course_totals) {
        const group = groupFor(total.student_id, total.student_code, total.student_name);
        group.courseTotal = total;
        if (total.changed) group.hasChanges = true;
    }

    // Students with changes first, so the ones staff need to act on surface immediately.
    return Array.from(groups.values()).sort((a, b) => Number(b.hasChanges) - Number(a.hasChanges) || a.student_name.localeCompare(b.student_name));
});

// Accordion items for students with at least one change open by default.
const defaultOpenGroups = computed(() => studentGroups.value.filter((g) => g.hasChanges).map((g) => `student-${g.student_id}`));

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
        <DialogContent class="!max-h-[85vh] !max-w-4xl overflow-y-auto">
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

                <Accordion v-if="studentGroups.length" type="multiple" :default-value="defaultOpenGroups" class="space-y-2">
                    <AccordionItem
                        v-for="group in studentGroups"
                        :key="group.student_id"
                        :value="`student-${group.student_id}`"
                        class="rounded-md border px-3"
                        :class="{ 'bg-muted/30': !group.hasChanges }"
                    >
                        <AccordionTrigger class="py-3 hover:no-underline">
                            <div class="mr-4 flex flex-1 flex-wrap items-center justify-between gap-2 text-left">
                                <div>
                                    <span class="font-medium">{{ group.student_name }}</span>
                                    <span class="text-muted-foreground ml-1.5 text-xs">{{ group.student_code }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge v-if="group.cellChanges.length" variant="secondary">{{ group.cellChanges.length }} cell change(s)</Badge>
                                    <Badge v-if="group.courseTotal?.changed" variant="default"> Total: {{ fmt(group.courseTotal.old_percentage) }}% → {{ fmt(group.courseTotal.new_percentage) }}% </Badge>
                                    <Badge v-else-if="group.courseTotal" variant="outline"> Total: {{ fmt(group.courseTotal.new_percentage) }}% (no change) </Badge>
                                    <Badge v-if="!group.hasChanges" variant="outline">Unchanged</Badge>
                                </div>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent class="pb-3">
                            <div v-if="group.cellChanges.length" class="overflow-hidden rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Component / Detail</TableHead>
                                            <TableHead class="text-right">Old</TableHead>
                                            <TableHead class="text-right">New</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(change, index) in group.cellChanges" :key="`${change.detail_name}-${index}`">
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
                            <p v-else class="text-muted-foreground text-sm">No component score changes for this student.</p>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>

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

                <p v-if="!studentGroups.length && !preview.unmatched.length" class="text-muted-foreground py-6 text-center text-sm">No changes to sync for the selected students.</p>
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
