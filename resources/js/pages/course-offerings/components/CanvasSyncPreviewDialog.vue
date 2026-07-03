<script setup lang="ts">
/**
 * Preview/apply dialog for the cockpit Scores tab "Sync from Canvas" action
 * (issue 10). Fetches a dry-run diff on open, then re-fetches Canvas at
 * apply time (never echoes the preview payload back) — matching ADR 0014.
 * The diff-table layout itself lives in CanvasScoreDiffTable.vue, shared
 * with issue 11's Recalculate preview.
 */
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApiRequest';
import { AlertTriangle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';
import CanvasScoreDiffTable, { type CanvasScoreDiff } from './CanvasScoreDiffTable.vue';

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
const preview = ref<CanvasScoreDiff | null>(null);

const close = () => emit('update:open', false);

const loadPreview = async () => {
    isLoading.value = true;
    error.value = null;
    preview.value = null;

    const { data } = await api.post<CanvasScoreDiff>(route('api.course-offerings.sync-grades.preview', { courseOffering: props.courseOfferingId }), {
        student_ids: props.studentIds,
    });

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

            <CanvasScoreDiffTable v-else-if="preview" :diff="preview" />

            <DialogFooter>
                <Button variant="outline" :disabled="isApplying" @click="close">Cancel</Button>
                <Button :disabled="isLoading || isApplying || !preview || preview.summary.total_changes === 0" @click="applySync">
                    {{ isApplying ? 'Syncing…' : `Sync ${preview?.summary.total_changes ?? 0} changes for ${preview?.summary.students_changed ?? 0} students` }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
