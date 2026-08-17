<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import type { PlacementOptions, WorklistStudent } from './worklist-types';

interface Props {
    student: WorklistStudent | null;
    placementOptions: PlacementOptions;
}

const props = defineProps<Props>();
const emit = defineEmits<{ close: [] }>();

const isOpen = computed({
    get: () => props.student !== null,
    set: (open: boolean) => {
        if (!open) emit('close');
    },
});

// Same field set and threshold logic as the lifecycle-tab initialize form
// (EgcControls.vue); kept as a small standalone dialog on purpose — see plan
// decision against extracting the 26K component.
const form = useForm({
    student_id: null as number | null,
    semester_id: '',
    has_ielts: false,
    ielts_score: '',
    issue_date: '',
    ielts_notes: '',
    missing_documents: false,
    english_level: '',
    trigger_source: 'manual_admin',
    notes: '',
});

watch(
    () => props.student,
    (student) => {
        if (student) {
            form.reset();
            form.clearErrors();
            form.student_id = student.id;
        }
    },
);

const usesPlacementTest = computed(() => !form.has_ielts || Number(form.ielts_score) < props.placementOptions.ieltsScoreThreshold);

const submit = (): void => {
    if (!props.student) return;

    form.post(route('students.placement.initialize', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Student classified successfully');
            emit('close');
        },
        onError: () => {
            toast.error('Failed to classify student. Please check the form.');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Classify student</DialogTitle>
                <DialogDescription>
                    Set the initial course stage and English level for {{ student?.full_name }} ({{ student?.student_id }}).
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="worklist_semester">Semester *</Label>
                    <Select v-model="form.semester_id">
                        <SelectTrigger id="worklist_semester">
                            <SelectValue placeholder="Select semester" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="semester in placementOptions.semesters" :key="semester.id" :value="String(semester.id)">
                                {{ semester.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.semester_id" class="text-sm text-red-500">{{ form.errors.semester_id }}</p>
                </div>

                <div class="flex items-center space-x-2">
                    <Switch id="worklist_has_ielts" v-model:model-value="form.has_ielts" />
                    <Label for="worklist_has_ielts">Student has IELTS certificate</Label>
                </div>

                <template v-if="form.has_ielts">
                    <div class="space-y-2">
                        <Label for="worklist_ielts_score">IELTS Overall Score *</Label>
                        <Input id="worklist_ielts_score" v-model="form.ielts_score" type="number" step="0.5" min="0" max="9" placeholder="e.g., 6.5" />
                        <p v-if="form.errors.ielts_score" class="text-sm text-red-500">{{ form.errors.ielts_score }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="worklist_issue_date">Issue Date (Optional)</Label>
                        <Input id="worklist_issue_date" v-model="form.issue_date" type="date" />
                        <p v-if="form.errors.issue_date" class="text-sm text-red-500">{{ form.errors.issue_date }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="worklist_ielts_notes">IELTS Notes (Optional)</Label>
                        <Textarea id="worklist_ielts_notes" v-model="form.ielts_notes" placeholder="Optional IELTS notes..." />
                        <p v-if="form.errors.ielts_notes" class="text-sm text-red-500">{{ form.errors.ielts_notes }}</p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Switch id="worklist_missing_documents" v-model:model-value="form.missing_documents" />
                        <Label for="worklist_missing_documents">Missing IELTS file scan (exception)</Label>
                    </div>
                </template>

                <template v-if="usesPlacementTest">
                    <div class="space-y-2">
                        <Label for="worklist_english_level">English Level (0-5) *</Label>
                        <Select v-model="form.english_level">
                            <SelectTrigger id="worklist_english_level">
                                <SelectValue placeholder="Select level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="level in placementOptions.englishLevels" :key="level.value" :value="String(level.value)">
                                    {{ level.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.english_level" class="text-sm text-red-500">{{ form.errors.english_level }}</p>
                    </div>
                </template>

                <div class="space-y-2">
                    <Label for="worklist_notes">Notes (Optional)</Label>
                    <Textarea id="worklist_notes" v-model="form.notes" placeholder="Optional notes..." />
                    <p v-if="form.errors.notes" class="text-sm text-red-500">{{ form.errors.notes }}</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="emit('close')">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Classify</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
