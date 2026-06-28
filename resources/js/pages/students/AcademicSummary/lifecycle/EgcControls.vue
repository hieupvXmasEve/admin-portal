<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, FileText, GraduationCap, Plus, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface StudentSummary {
    id: number;
    student_id: string;
    full_name: string;
    status: string;
}

interface PlacementSemester {
    id: number;
    name: string;
    code: string;
    start_date?: string;
    end_date?: string;
}

interface PlacementOptions {
    eventTypes: { value: string; label: string; labelEn: string }[];
    triggerSources: { value: string; label: string }[];
    semesters: PlacementSemester[];
    englishLevels: { value: number; label: string }[];
    ieltsScoreThreshold: number;
}

interface Props {
    student: StudentSummary;
    options: Record<string, unknown>;
}

const props = defineProps<Props>();

// The orchestrator types `options` loosely as Record<string, unknown>; the actual
// payload is built by LifecycleFormOptions::placementOptions(). Narrow it once here.
const placementOptions = computed(() => props.options as unknown as PlacementOptions);

const isPlacementDialogOpen = ref(false);
const isIeltsDialogOpen = ref(false);
const isLevelDialogOpen = ref(false);
const isTransitionDialogOpen = ref(false);

const placementForm = useForm({
    student_id: props.student.id,
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

const ieltsForm = useForm({
    student_id: props.student.id,
    overall_score: '',
    semester_id: '',
    issue_date: '',
    missing_documents: false,
    notes: '',
});

const levelForm = useForm({
    student_id: props.student.id,
    new_level: '',
    semester_id: '',
    trigger_source: 'manual_admin',
    notes: '',
});

const transitionForm = useForm({
    student_id: props.student.id,
    semester_id: '',
    ielts_certificate_id: '',
    allow_missing_documents: false,
    notes: '',
});

const usesPlacementTest = computed(
    () => !placementForm.has_ielts || Number(placementForm.ielts_score) < placementOptions.value.ieltsScoreThreshold,
);

const handlePlacementSubmit = (): void => {
    placementForm.post(route('students.placement.initialize', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Placement initialized successfully');
            isPlacementDialogOpen.value = false;
            placementForm.reset();
            placementForm.student_id = props.student.id;
        },
        onError: () => {
            toast.error('Failed to initialize placement. Please check the form.');
        },
    });
};

const handleIeltsSubmit = (): void => {
    ieltsForm.post(route('students.placement.ielts.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('IELTS certificate recorded successfully');
            isIeltsDialogOpen.value = false;
            ieltsForm.reset();
            ieltsForm.student_id = props.student.id;
        },
        onError: () => {
            toast.error('Failed to record IELTS certificate.');
        },
    });
};

const handleLevelSubmit = (): void => {
    levelForm.post(route('students.placement.level.update', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('English level updated successfully');
            isLevelDialogOpen.value = false;
            levelForm.reset();
            levelForm.student_id = props.student.id;
        },
        onError: () => {
            toast.error('Failed to update English level.');
        },
    });
};

const handleTransitionSubmit = (): void => {
    transitionForm.post(route('students.placement.transition', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Student transitioned to Intake Course successfully');
            isTransitionDialogOpen.value = false;
            transitionForm.reset();
            transitionForm.student_id = props.student.id;
        },
        onError: () => {
            toast.error('Failed to transition student.');
        },
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">EGC placement &amp; progression</CardTitle>
            <CardDescription>Record placement, IELTS, level changes, and the transition to the intake course.</CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col gap-2">
            <!-- Initialize placement -->
            <Dialog v-model:open="isPlacementDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" class="justify-start">
                        <Plus class="mr-2 h-4 w-4" />
                        Initialize placement
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Initialize student placement</DialogTitle>
                        <DialogDescription>Set the initial course stage and English level for {{ student.full_name }}.</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="handlePlacementSubmit">
                        <div class="space-y-2">
                            <Label for="placement_semester">Semester *</Label>
                            <Select v-model="placementForm.semester_id">
                                <SelectTrigger id="placement_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="semester in placementOptions.semesters" :key="semester.id" :value="String(semester.id)">
                                        {{ semester.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="placementForm.errors.semester_id" class="text-sm text-red-500">{{ placementForm.errors.semester_id }}</p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch id="has_ielts" v-model:model-value="placementForm.has_ielts" />
                            <Label for="has_ielts">Student has IELTS certificate</Label>
                        </div>

                        <template v-if="placementForm.has_ielts">
                            <div class="space-y-2">
                                <Label for="placement_ielts_score">IELTS Overall Score *</Label>
                                <Input id="placement_ielts_score" v-model="placementForm.ielts_score" type="number" step="0.5" min="0" max="9" placeholder="e.g., 6.5" />
                                <p v-if="placementForm.errors.ielts_score" class="text-sm text-red-500">{{ placementForm.errors.ielts_score }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="placement_issue_date">Issue Date (Optional)</Label>
                                <Input id="placement_issue_date" v-model="placementForm.issue_date" type="date" />
                                <p v-if="placementForm.errors.issue_date" class="text-sm text-red-500">{{ placementForm.errors.issue_date }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="placement_ielts_notes">IELTS Notes (Optional)</Label>
                                <Textarea id="placement_ielts_notes" v-model="placementForm.ielts_notes" placeholder="Optional IELTS notes..." />
                                <p v-if="placementForm.errors.ielts_notes" class="text-sm text-red-500">{{ placementForm.errors.ielts_notes }}</p>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="placement_missing_documents" v-model:model-value="placementForm.missing_documents" />
                                <Label for="placement_missing_documents">Missing IELTS file scan (exception)</Label>
                            </div>
                        </template>

                        <template v-if="usesPlacementTest">
                            <div class="space-y-2">
                                <Label for="placement_english_level">English Level (0-5) *</Label>
                                <Select v-model="placementForm.english_level">
                                    <SelectTrigger id="placement_english_level">
                                        <SelectValue placeholder="Select level" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="level in placementOptions.englishLevels" :key="level.value" :value="String(level.value)">
                                            {{ level.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="placementForm.errors.english_level" class="text-sm text-red-500">{{ placementForm.errors.english_level }}</p>
                            </div>
                        </template>

                        <div class="space-y-2">
                            <Label for="placement_trigger_source">Trigger Source (Optional)</Label>
                            <Select v-model="placementForm.trigger_source">
                                <SelectTrigger id="placement_trigger_source">
                                    <SelectValue placeholder="Select trigger source" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="source in placementOptions.triggerSources" :key="source.value" :value="source.value">
                                        {{ source.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="placementForm.errors.trigger_source" class="text-sm text-red-500">{{ placementForm.errors.trigger_source }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="placement_notes">Notes (Optional)</Label>
                            <Textarea id="placement_notes" v-model="placementForm.notes" placeholder="Optional notes..." />
                            <p v-if="placementForm.errors.notes" class="text-sm text-red-500">{{ placementForm.errors.notes }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isPlacementDialogOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="placementForm.processing">Initialize Placement</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Record IELTS -->
            <Dialog v-model:open="isIeltsDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" class="justify-start">
                        <FileText class="mr-2 h-4 w-4" />
                        Record IELTS
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Record IELTS certificate</DialogTitle>
                        <DialogDescription>Add a new IELTS certificate for {{ student.full_name }}.</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="handleIeltsSubmit">
                        <div class="space-y-2">
                            <Label for="ielts_overall_score">Overall Score *</Label>
                            <Input id="ielts_overall_score" v-model="ieltsForm.overall_score" type="number" step="0.5" min="0" max="9" placeholder="e.g., 6.5" />
                            <p v-if="ieltsForm.errors.overall_score" class="text-sm text-red-500">{{ ieltsForm.errors.overall_score }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="ielts_semester">Semester *</Label>
                            <Select v-model="ieltsForm.semester_id">
                                <SelectTrigger id="ielts_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="semester in placementOptions.semesters" :key="semester.id" :value="String(semester.id)">
                                        {{ semester.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="ieltsForm.errors.semester_id" class="text-sm text-red-500">{{ ieltsForm.errors.semester_id }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="ielts_issue_date">Issue Date (Optional)</Label>
                            <Input id="ielts_issue_date" v-model="ieltsForm.issue_date" type="date" />
                            <p v-if="ieltsForm.errors.issue_date" class="text-sm text-red-500">{{ ieltsForm.errors.issue_date }}</p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch id="ielts_missing_documents" v-model:model-value="ieltsForm.missing_documents" />
                            <Label for="ielts_missing_documents">Missing file scan (exception)</Label>
                        </div>

                        <div class="space-y-2">
                            <Label for="ielts_notes">Notes (Optional)</Label>
                            <Textarea id="ielts_notes" v-model="ieltsForm.notes" placeholder="Optional notes..." />
                            <p v-if="ieltsForm.errors.notes" class="text-sm text-red-500">{{ ieltsForm.errors.notes }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isIeltsDialogOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="ieltsForm.processing">Record IELTS</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Change English level -->
            <Dialog v-model:open="isLevelDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" class="justify-start">
                        <TrendingUp class="mr-2 h-4 w-4" />
                        Change English level
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Update English level</DialogTitle>
                        <DialogDescription>Record a new English level for {{ student.full_name }}.</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="handleLevelSubmit">
                        <div class="space-y-2">
                            <Label for="level_new_level">New Level *</Label>
                            <Select v-model="levelForm.new_level">
                                <SelectTrigger id="level_new_level">
                                    <SelectValue placeholder="Select new level" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="level in placementOptions.englishLevels" :key="level.value" :value="String(level.value)">
                                        {{ level.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="levelForm.errors.new_level" class="text-sm text-red-500">{{ levelForm.errors.new_level }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="level_semester">Semester *</Label>
                            <Select v-model="levelForm.semester_id">
                                <SelectTrigger id="level_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="semester in placementOptions.semesters" :key="semester.id" :value="String(semester.id)">
                                        {{ semester.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="levelForm.errors.semester_id" class="text-sm text-red-500">{{ levelForm.errors.semester_id }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="level_trigger_source">Trigger Source (Optional)</Label>
                            <Select v-model="levelForm.trigger_source">
                                <SelectTrigger id="level_trigger_source">
                                    <SelectValue placeholder="Select trigger source" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="source in placementOptions.triggerSources" :key="source.value" :value="source.value">
                                        {{ source.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="levelForm.errors.trigger_source" class="text-sm text-red-500">{{ levelForm.errors.trigger_source }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="level_notes">Notes (Optional)</Label>
                            <Textarea id="level_notes" v-model="levelForm.notes" placeholder="Optional notes..." />
                            <p v-if="levelForm.errors.notes" class="text-sm text-red-500">{{ levelForm.errors.notes }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isLevelDialogOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="levelForm.processing">Update Level</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Transition to intake course -->
            <Dialog v-model:open="isTransitionDialogOpen">
                <DialogTrigger as-child>
                    <Button class="justify-start">
                        <ArrowRight class="mr-2 h-4 w-4" />
                        Transition to intake course
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Transition to intake course</DialogTitle>
                        <DialogDescription>Change {{ student.full_name }}'s stage from Pre-Uni GC to Intake Course.</DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="handleTransitionSubmit">
                        <div class="space-y-2">
                            <Label for="transition_ielts_certificate_id">IELTS Certificate ID *</Label>
                            <Input id="transition_ielts_certificate_id" v-model="transitionForm.ielts_certificate_id" type="number" min="0" placeholder="Qualifying IELTS certificate ID" />
                            <p v-if="transitionForm.errors.ielts_certificate_id" class="text-sm text-red-500">{{ transitionForm.errors.ielts_certificate_id }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="transition_semester">Semester *</Label>
                            <Select v-model="transitionForm.semester_id">
                                <SelectTrigger id="transition_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="semester in placementOptions.semesters" :key="semester.id" :value="String(semester.id)">
                                        {{ semester.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="transitionForm.errors.semester_id" class="text-sm text-red-500">{{ transitionForm.errors.semester_id }}</p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch id="transition_allow_missing_documents" v-model:model-value="transitionForm.allow_missing_documents" />
                            <Label for="transition_allow_missing_documents">Allow missing documents (exception)</Label>
                        </div>

                        <div class="space-y-2">
                            <Label for="transition_notes">Notes (Optional)</Label>
                            <Textarea id="transition_notes" v-model="transitionForm.notes" placeholder="Optional notes..." />
                            <p v-if="transitionForm.errors.notes" class="text-sm text-red-500">{{ transitionForm.errors.notes }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isTransitionDialogOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="transitionForm.processing">Transition to Intake Course</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <p class="text-muted-foreground flex items-center gap-1 pt-1 text-xs">
                <GraduationCap class="h-3.5 w-3.5" />
                IELTS qualifying threshold: {{ placementOptions.ieltsScoreThreshold }}
            </p>
        </CardContent>
    </Card>
</template>
