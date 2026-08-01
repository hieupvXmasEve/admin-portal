<script setup lang="ts">
import type { Errors, Page } from '@inertiajs/core';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface SemesterOption {
    id: number;
    code: string;
    name: string;
    start_date: string | null;
}

interface CandidateRow {
    student_id: number;
    student: { id: number; student_id: string; full_name: string } | null;
    failed_courses: Array<{ unit_code: string; unit_name: string; grade_finalized_date: string | null }>;
    needs_data_review: boolean;
}

interface Props {
    campusId: number;
    sourceSemesterId: number | null;
    targetSemesterId: number | null;
    semesters: SemesterOption[];
    preview: { candidates: CandidateRow[]; excluded_null_is_passed: number; already_has_dossier: number } | null;
    error: string | null;
}

const props = defineProps<Props>();

const targetSemesterId = ref<number | null>(props.targetSemesterId);
const sourceSemesterId = ref<number | null>(props.sourceSemesterId);

// Only semesters that start before the chosen target may be picked as source
// — mirrors the server-side validateSemesterPair() ordering rule.
function sourceOptions() {
    const target = props.semesters.find((s) => s.id === targetSemesterId.value);
    const targetStart = target?.start_date;
    if (!targetStart) return props.semesters;
    return props.semesters.filter((s) => s.start_date && s.start_date < targetStart);
}

function reload() {
    router.get(
        route('scholarship-adjustments.candidates.preview'),
        {
            campus_id: props.campusId,
            target_semester_id: targetSemesterId.value,
            source_semester_id: sourceSemesterId.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}

// Changing the target invalidates the current source (ordering rule), so the
// source resets and the reload runs without it.
function onTargetChange(value: unknown) {
    targetSemesterId.value = value ? Number(value) : null;
    sourceSemesterId.value = null;
    reload();
}

function onSourceChange(value: unknown) {
    sourceSemesterId.value = value ? Number(value) : null;
    reload();
}

const selected = ref<Set<number>>(new Set());

watch(
    () => props.preview,
    () => selected.value.clear(),
);

function toggle(studentId: number, checked: boolean) {
    if (checked) selected.value.add(studentId);
    else selected.value.delete(studentId);
}

const createForm = useForm({ student_ids: [] as number[] });

function submitCreate() {
    createForm.student_ids = Array.from(selected.value);
    createForm
        .transform((data) => ({
            ...data,
            campus_id: props.campusId,
            source_semester_id: sourceSemesterId.value,
            target_semester_id: targetSemesterId.value,
        }))
        .post(route('scholarship-adjustments.identify'), {
            preserveScroll: true,
            onSuccess: (visited: Page) => {
                const flash = visited.props.flash as { success?: string } | undefined;
                if (flash?.success) toast.success(flash.success);
                selected.value.clear();
                reload();
            },
            onError: (errors: Errors) => {
                toast.error(errors.error ?? Object.values(errors)[0] ?? 'Could not start the review.');
            },
        });
}
</script>

<template>
    <Head title="Find students to review" />

    <Heading title="Find students to review" description="See who failed a course in an earlier semester, then start a review only for the students you choose." />

    <Alert v-if="error" variant="destructive" class="mt-4">
        <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Choose the semesters</CardTitle>
        </CardHeader>
        <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="target">Semester the change would apply to</Label>
                <Select :model-value="targetSemesterId?.toString()" @update:model-value="onTargetChange">
                    <SelectTrigger id="target" class="w-full">
                        <SelectValue placeholder="Choose a semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="s in semesters" :key="s.id" :value="s.id.toString()">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label for="source">Semester the student failed in</Label>
                <Select :model-value="sourceSemesterId?.toString()" :disabled="!targetSemesterId" @update:model-value="onSourceChange">
                    <SelectTrigger id="source" class="w-full">
                        <SelectValue :placeholder="targetSemesterId ? 'Choose an earlier semester' : 'Choose the semester above first'" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="s in sourceOptions()" :key="s.id" :value="s.id.toString()">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </CardContent>
    </Card>

    <Card v-if="preview" class="mt-6">
        <CardHeader>
            <CardTitle>{{ preview.candidates.length }} student(s) can be reviewed</CardTitle>
            <CardDescription v-if="preview.excluded_null_is_passed || preview.already_has_dossier">
                Not shown: {{ preview.already_has_dossier }} already under review, {{ preview.excluded_null_is_passed }} course result(s) not graded yet.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead />
                        <TableHead>Student</TableHead>
                        <TableHead>Failed courses</TableHead>
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="preview.candidates.length === 0">
                        <TableCell colspan="4" class="text-muted-foreground text-center">No students need a review for these semesters.</TableCell>
                    </TableRow>
                    <TableRow v-for="row in preview.candidates" :key="row.student_id">
                        <TableCell>
                            <Checkbox :model-value="selected.has(row.student_id)" @update:model-value="(v) => toggle(row.student_id, !!v)" />
                        </TableCell>
                        <TableCell>
                            <div class="font-medium">{{ row.student?.full_name }}</div>
                            <div class="text-muted-foreground text-xs">{{ row.student?.student_id }}</div>
                        </TableCell>
                        <TableCell>
                            <ul class="space-y-0.5 text-sm">
                                <li v-for="(course, i) in row.failed_courses" :key="i">{{ course.unit_code }} — {{ course.unit_name }}</li>
                            </ul>
                        </TableCell>
                        <TableCell>
                            <Badge v-if="row.needs_data_review" variant="destructive">Check grades manually</Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="preview.candidates.length > 0" class="mt-4">
                <Button :disabled="selected.size === 0 || createForm.processing" @click="submitCreate"> Start review for {{ selected.size }} student(s) </Button>
            </div>
        </CardContent>
    </Card>
</template>
