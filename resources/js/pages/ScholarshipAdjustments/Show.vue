<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface Dossier {
    id: number;
    status: string;
    interview_status: string;
    needs_data_review: boolean;
    source: string;
    manual_exception_reason: string | null;
    failed_courses_snapshot: Array<{ unit_code: string; unit_name: string; attempt_no: number; grade_finalized_date: string | null }>;
    original_scholarship_code: string;
    original_type: string;
    original_amount: string;
    interview_scheduled_at: string | null;
    interview_mode: string | null;
    minutes: string | null;
    minutes_version: number;
    decision_type: string | null;
    decision_adjusted_amount: string | null;
    decision_reason: string | null;
    student: { student_id: string; full_name: string } | null;
    source_semester: { name: string } | null;
    target_semester: { name: string } | null;
}

const props = defineProps<{ dossier: Dossier }>();

const scheduleForm = useForm({ scheduled_at: '', mode: 'in_person', location: '' });
const completeForm = useForm({ minutes: '', participants: [] as string[] });
const decideForm = useForm({ decision_type: 'keep', adjusted_amount: null as number | null, reason: '', exception_override_reason: '' });

function submitSchedule() {
    scheduleForm.post(route('scholarship-adjustments.interview.schedule', props.dossier.id), { preserveScroll: true });
}

function submitComplete() {
    completeForm.post(route('scholarship-adjustments.interview.complete', props.dossier.id), { preserveScroll: true });
}

function submitDecide() {
    decideForm.post(route('scholarship-adjustments.decide', props.dossier.id), { preserveScroll: true });
}

function submitApprove() {
    useForm({}).post(route('scholarship-adjustments.approve', props.dossier.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Scholarship Adjustment Dossier" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">{{ dossier.student?.full_name }} ({{ dossier.student?.student_id }})</h2>
            <p class="text-muted-foreground mt-1 text-sm">{{ dossier.source_semester?.name }} → {{ dossier.target_semester?.name }}</p>
        </div>
        <Badge>{{ dossier.status }}</Badge>
    </div>

    <Alert v-if="dossier.needs_data_review" variant="destructive" class="mt-4">
        <AlertDescription>
            This dossier includes a record finalized before the is_passed gate fix (2026-07-04). Mandatory manual verification required before deciding.
        </AlertDescription>
    </Alert>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Evidence</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-sm"><span class="text-muted-foreground">Source:</span> {{ dossier.source }}</p>
                <p v-if="dossier.manual_exception_reason" class="text-sm"><span class="text-muted-foreground">Exception reason:</span> {{ dossier.manual_exception_reason }}</p>
                <p class="mt-2 text-sm"><span class="text-muted-foreground">Original scholarship:</span> {{ dossier.original_scholarship_code }} ({{ dossier.original_type }}, {{ dossier.original_amount }})</p>
                <ul class="mt-3 space-y-1 text-sm">
                    <li v-for="(course, i) in dossier.failed_courses_snapshot" :key="i">
                        {{ course.unit_code }} — {{ course.unit_name }} (attempt {{ course.attempt_no }}, finalized {{ course.grade_finalized_date }})
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Interview — {{ dossier.interview_status }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <form v-if="dossier.interview_status === 'not_scheduled' || dossier.interview_status === 'rescheduled'" class="space-y-3" @submit.prevent="submitSchedule">
                    <div class="space-y-2">
                        <Label for="scheduled_at">Scheduled at</Label>
                        <Input id="scheduled_at" v-model="scheduleForm.scheduled_at" type="datetime-local" />
                    </div>
                    <Button type="submit" :disabled="scheduleForm.processing">Schedule</Button>
                </form>

                <form v-if="dossier.interview_status === 'scheduled'" class="space-y-3" @submit.prevent="submitComplete">
                    <div class="space-y-2">
                        <Label for="minutes">Minutes</Label>
                        <Textarea id="minutes" v-model="completeForm.minutes" rows="4" />
                    </div>
                    <Button type="submit" :disabled="completeForm.processing">Complete interview</Button>
                </form>

                <div v-if="dossier.minutes" class="text-sm">
                    <p class="text-muted-foreground">Minutes (v{{ dossier.minutes_version }}):</p>
                    <p class="whitespace-pre-wrap">{{ dossier.minutes }}</p>
                </div>
            </CardContent>
        </Card>

        <Card class="lg:col-span-2">
            <CardHeader>
                <CardTitle>Decision</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <form v-if="dossier.status === 'interviewed' || dossier.status === 'ready_for_decision'" class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitDecide">
                    <div class="space-y-2">
                        <Label for="decision_type">Decision</Label>
                        <select id="decision_type" v-model="decideForm.decision_type" class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm">
                            <option value="keep">Keep</option>
                            <option value="reduce">Reduce</option>
                            <option value="suspend_full">Suspend full</option>
                            <option value="defer">Defer</option>
                            <option value="cancel">Cancel</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="adjusted_amount">Adjusted amount</Label>
                        <Input id="adjusted_amount" v-model.number="decideForm.adjusted_amount" type="number" step="0.01" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label for="reason">Reason</Label>
                        <Textarea id="reason" v-model="decideForm.reason" rows="2" />
                    </div>
                    <div class="md:col-span-2">
                        <Button type="submit" :disabled="decideForm.processing">Submit decision (maker)</Button>
                    </div>
                </form>

                <div v-if="dossier.status === 'ready_for_decision'">
                    <p class="text-muted-foreground text-sm">Proposed: {{ dossier.decision_type }} — {{ dossier.decision_reason }}</p>
                    <Button class="mt-2" variant="secondary" @click="submitApprove">Approve (checker)</Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
