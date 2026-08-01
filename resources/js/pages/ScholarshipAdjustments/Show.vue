<script setup lang="ts">
import type { Errors, Page } from '@inertiajs/core';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import TimePicker from '@/components/ui/TimePicker.vue';
import { formatCurrency, formatDate } from '@/utils/format';
import { Calendar as CalendarIcon, Clock } from 'lucide-vue-next';
import { CONFIRMATION_STATUS_LABELS, DECISION_TYPE_LABELS, DOSSIER_SOURCE_LABELS, DOSSIER_STATUS_LABELS, INTERVIEW_STATUS_LABELS, SCHOLARSHIP_TYPE_LABELS, labelFor } from './dossier-labels';

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
    confirmation_status: string | null;
    confirmed_on_behalf: boolean;
    student_comment: string | null;
    dispute_overrule_reason: string | null;
    decision_type: string | null;
    decision_adjusted_amount: string | null;
    decision_reason: string | null;
    student: { student_id: string; full_name: string } | null;
    source_semester: { name: string } | null;
    target_semester: { name: string } | null;
}

interface ImpactPreview {
    has_invoice: boolean;
    tuition_base: number;
    original_type: string | null;
    original_amount: number | null;
    current_discount: number;
    adjusted_discount: number | null;
    payable_before: number;
    payable_after: number | null;
    delta: number | null;
}

const props = defineProps<{ dossier: Dossier; preview: ImpactPreview }>();

const page = usePage<{ errors: Record<string, string> }>();
const flashError = computed(() => page.props.errors?.error ?? null);

// "30% of tuition" reads better than the raw "percentage / 30" pair.
const formattedOriginalAward = computed(() => {
    const amount = props.dossier.original_amount;
    if (props.dossier.original_type === 'percentage') return `${amount}% of tuition`;
    return `${labelFor(SCHOLARSHIP_TYPE_LABELS, props.dossier.original_type)}: ${amount}`;
});

// DatePicker and TimePicker each own one half of the value; they are joined
// back into the single `scheduled_at` datetime the endpoint expects.
const scheduleForm = useForm({ scheduled_date: '', scheduled_time: '', mode: 'in_person', location: '' });
const completeForm = useForm({ minutes: '', participants: [] as string[] });
// adjusted_amount is `nullable` server-side; undefined keeps the Input's
// model type happy and is simply omitted from the payload when left blank.
const decideForm = useForm({ decision_type: 'keep', adjusted_amount: undefined as number | undefined, reason: '', exception_override_reason: '' });
const onBehalfForm = useForm({ on_behalf_note: '' });
const minutesForm = useForm({ minutes: props.dossier.minutes ?? '' });
const overruleForm = useForm({ overrule_reason: '' });

const isDisputed = computed(() => props.dossier.confirmation_status === 'disputed');

/**
 * Live money impact while the maker types. Deliberately server-computed rather
 * than mirrored in JS: the discount is clamped to the tuition base, so a local
 * copy of that rule would eventually disagree with what is actually charged.
 */
// Only a reduction takes a typed value: suspend_full is implicitly zero, and
// keep/defer/cancel never reach Finance at all — the server discards an amount
// on those, so the field must not invite one.
const takesAmount = computed(() => decideForm.decision_type === 'reduce');

const impact = ref<ImpactPreview>(props.preview);
const impactLoading = ref(false);
let impactTimer: ReturnType<typeof setTimeout> | undefined;

async function fetchImpact(amount: number | undefined) {
    impactLoading.value = true;
    try {
        const response = await fetch(route('scholarship-adjustments.decision-preview', props.dossier.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
            },
            body: JSON.stringify({ adjusted_amount: amount ?? null }),
        });
        if (response.ok) impact.value = await response.json();
    } finally {
        impactLoading.value = false;
    }
}

watch(
    () => [decideForm.adjusted_amount, decideForm.decision_type] as const,
    ([amount, type]) => {
        // Switching away from a reduction drops any number already typed, so a
        // stale value cannot be submitted with an outcome that ignores it.
        if (!takesAmount.value && decideForm.adjusted_amount !== undefined) {
            decideForm.adjusted_amount = undefined;
        }

        clearTimeout(impactTimer);
        // Suspending the scholarship entirely means a remaining value of zero,
        // whatever sits in the amount box.
        const effective = type === 'suspend_full' ? 0 : takesAmount.value ? amount : undefined;
        impactTimer = setTimeout(() => fetchImpact(effective ?? undefined), 300);
    },
);

/** Money decisions are refused server-side until the student has confirmed. */
const isMoneyDecision = computed(() => ['reduce', 'suspend_full'].includes(decideForm.decision_type));
const confirmationBlocks = computed(() => isMoneyDecision.value && !['confirmed', 'dispute_overruled'].includes(props.dossier.confirmation_status ?? ''));

/**
 * Controllers here flash via back()->with('success') / withErrors(['error']),
 * which land in page.props.flash / page.props.errors (session-based) — the
 * global useFlashToast bridge only handles Inertia's native flash, so every
 * submit surfaces its own result. Domain guards (confirmation gate,
 * maker != checker) reject via withErrors and would otherwise be invisible.
 */
const visitOptions = {
    preserveScroll: true,
    onSuccess: (visited: Page) => {
        const flash = visited.props.flash as { success?: string } | undefined;
        if (flash?.success) toast.success(flash.success);
    },
    onError: (errors: Errors) => {
        // `error` is the domain-guard key; anything else is a field validation
        // message, which must still reach the user rather than a generic line.
        toast.error(errors.error ?? Object.values(errors)[0] ?? 'Could not complete that action.');
    },
};

function submitOnBehalf() {
    onBehalfForm.post(route('scholarship-adjustments.confirm-on-behalf', props.dossier.id), visitOptions);
}

function submitEditMinutes() {
    minutesForm.post(route('scholarship-adjustments.interview.minutes', props.dossier.id), visitOptions);
}

function submitOverrule() {
    overruleForm.post(route('scholarship-adjustments.overrule-dispute', props.dossier.id), visitOptions);
}

function submitSchedule() {
    scheduleForm
        .transform((data) => ({
            mode: data.mode,
            location: data.location,
            scheduled_at: data.scheduled_date && data.scheduled_time ? `${data.scheduled_date} ${data.scheduled_time}` : '',
        }))
        .post(route('scholarship-adjustments.interview.schedule', props.dossier.id), visitOptions);
}

function submitComplete() {
    completeForm.post(route('scholarship-adjustments.interview.complete', props.dossier.id), visitOptions);
}

function submitDecide() {
    decideForm.post(route('scholarship-adjustments.decide', props.dossier.id), visitOptions);
}

function submitApprove() {
    useForm({}).post(route('scholarship-adjustments.approve', props.dossier.id), visitOptions);
}
</script>

<template>
    <Head title="Scholarship review" />

    <div class="flex items-center justify-between">
        <Heading :title="`${dossier.student?.full_name} (${dossier.student?.student_id})`" :description="`Failed in ${dossier.source_semester?.name} — any change applies to ${dossier.target_semester?.name}`" />
        <Badge>{{ labelFor(DOSSIER_STATUS_LABELS, dossier.status) }}</Badge>
    </div>

    <Alert v-if="flashError" variant="destructive" class="mt-4">
        <AlertDescription>{{ flashError }}</AlertDescription>
    </Alert>

    <Alert v-if="dossier.needs_data_review" variant="destructive" class="mt-4">
        <AlertDescription> Some grades here were finalised before a pass/fail calculation fix (4 July 2026) and may be inaccurate. Verify them manually before making a decision. </AlertDescription>
    </Alert>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Why this student is under review</CardTitle>
                <CardDescription>{{ labelFor(DOSSIER_SOURCE_LABELS, dossier.source) }}</CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="dossier.manual_exception_reason" class="text-sm"><span class="text-muted-foreground">Reason for adding manually:</span> {{ dossier.manual_exception_reason }}</p>
                <p class="text-sm">
                    <span class="text-muted-foreground">Current scholarship:</span>
                    {{ dossier.original_scholarship_code }} — {{ formattedOriginalAward }}
                </p>
                <p class="text-muted-foreground mt-4 mb-1 text-sm">Failed courses</p>
                <ul class="space-y-1 text-sm">
                    <li v-for="(course, i) in dossier.failed_courses_snapshot" :key="i">
                        {{ course.unit_code }} — {{ course.unit_name }}
                        <span class="text-muted-foreground">(attempt {{ course.attempt_no }}, graded {{ formatDate(course.grade_finalized_date) }})</span>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Interview</CardTitle>
                <CardDescription>{{ labelFor(INTERVIEW_STATUS_LABELS, dossier.interview_status) }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <form v-if="dossier.interview_status === 'not_scheduled' || dossier.interview_status === 'rescheduled'" class="space-y-3" @submit.prevent="submitSchedule">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label class="flex items-center gap-1.5"><CalendarIcon class="h-3.5 w-3.5" /> Date</Label>
                            <DatePicker v-model="scheduleForm.scheduled_date" placeholder="Pick a date" />
                        </div>
                        <div class="space-y-2">
                            <Label class="flex items-center gap-1.5"><Clock class="h-3.5 w-3.5" /> Time</Label>
                            <TimePicker v-model="scheduleForm.scheduled_time" />
                        </div>
                    </div>
                    <Button type="submit" :disabled="scheduleForm.processing || !scheduleForm.scheduled_date || !scheduleForm.scheduled_time"> Book the interview </Button>
                </form>

                <form v-if="dossier.interview_status === 'scheduled'" class="space-y-3" @submit.prevent="submitComplete">
                    <div class="space-y-2">
                        <Label for="minutes">What was agreed in the interview</Label>
                        <Textarea id="minutes" v-model="completeForm.minutes" rows="4" />
                    </div>
                    <InputError :message="completeForm.errors.minutes" />
                    <Button type="submit" :disabled="completeForm.processing">Save and ask the student to confirm</Button>
                </form>

                <div v-if="dossier.minutes" class="text-sm">
                    <p class="text-muted-foreground">Interview notes (revision {{ dossier.minutes_version }})</p>
                    <p class="whitespace-pre-wrap">{{ dossier.minutes }}</p>
                </div>
            </CardContent>
        </Card>

        <Card class="lg:col-span-2">
            <CardHeader>
                <CardTitle>Student confirmation</CardTitle>
                <CardDescription>The student must agree with the interview notes before a scholarship change can be decided.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm">
                    <Badge>{{ labelFor(CONFIRMATION_STATUS_LABELS, dossier.confirmation_status, 'Not requested yet') }}</Badge>
                    <Badge v-if="dossier.confirmed_on_behalf" variant="secondary" class="ml-1">Confirmed by staff</Badge>
                </p>
                <p v-if="dossier.student_comment" class="text-sm"><span class="text-muted-foreground">What the student said:</span> {{ dossier.student_comment }}</p>

                <!-- A student who answered in disagreement is never "confirmed"
                     for. Two honest routes: fix the notes, or overrule on the record. -->
                <div v-if="isDisputed" class="space-y-4">
                    <Alert variant="destructive">
                        <AlertDescription> The student disagreed with the interview notes. You cannot confirm on their behalf — correct the notes and ask again, or have an approver overrule the disagreement with a written reason. </AlertDescription>
                    </Alert>

                    <form class="space-y-2" @submit.prevent="submitEditMinutes">
                        <Label for="corrected_minutes">Corrected interview notes</Label>
                        <Textarea id="corrected_minutes" v-model="minutesForm.minutes" rows="4" />
                        <p class="text-muted-foreground text-xs">Saving creates a new revision and asks the student to confirm again.</p>
                        <InputError :message="minutesForm.errors.minutes" />
                        <Button type="submit" :disabled="minutesForm.processing || !minutesForm.minutes.trim()">Save corrected notes and ask again</Button>
                    </form>

                    <form class="space-y-2 border-t pt-4" @submit.prevent="submitOverrule">
                        <Label for="overrule_reason">Or overrule the disagreement — why the adjustment stands</Label>
                        <Textarea id="overrule_reason" v-model="overruleForm.overrule_reason" rows="2" />
                        <p class="text-muted-foreground text-xs">Kept on the record. The student stays marked as disagreeing — this does not record their agreement.</p>
                        <Button type="submit" variant="destructive" :disabled="overruleForm.processing || overruleForm.overrule_reason.trim().length < 10"> Overrule the disagreement </Button>
                    </form>
                </div>

                <p v-if="dossier.confirmation_status === 'dispute_overruled'" class="text-sm"><span class="text-muted-foreground">Overruled because:</span> {{ dossier.dispute_overrule_reason }}</p>

                <!-- Confirm-on-behalf: only for a student who has not answered. -->
                <form v-if="['pending', 'overdue'].includes(dossier.confirmation_status ?? '')" class="space-y-2" @submit.prevent="submitOnBehalf">
                    <Label for="on_behalf_note">How you reached the student and what they agreed to</Label>
                    <Textarea id="on_behalf_note" v-model="onBehalfForm.on_behalf_note" rows="2" />
                    <InputError :message="onBehalfForm.errors.on_behalf_note" />
                    <Button type="submit" variant="secondary" :disabled="onBehalfForm.processing">Confirm for the student</Button>
                </form>
            </CardContent>
        </Card>

        <Card class="lg:col-span-2">
            <CardHeader>
                <CardTitle>Scholarship decision</CardTitle>
                <CardDescription>One person proposes the change; a different person must approve it.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <form v-if="dossier.status === 'interviewed' || dossier.status === 'ready_for_decision'" class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitDecide">
                    <div class="space-y-2">
                        <Label for="decision_type">What happens to the scholarship</Label>
                        <Select v-model="decideForm.decision_type">
                            <SelectTrigger id="decision_type" class="w-full">
                                <SelectValue placeholder="Choose an outcome" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, value) in DECISION_TYPE_LABELS" :key="value" :value="value">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="decideForm.errors.decision_type" />
                    </div>
                    <div class="space-y-2">
                        <Label for="adjusted_amount">
                            Scholarship the student keeps
                            <span v-if="impact.original_type === 'percentage'" class="text-muted-foreground">(% of tuition)</span>
                            <span v-else-if="impact.original_type === 'fixed_amount'" class="text-muted-foreground">(amount in ₫)</span>
                        </Label>
                        <Input id="adjusted_amount" v-model.number="decideForm.adjusted_amount" type="number" step="0.01" :disabled="!takesAmount" />
                        <p class="text-muted-foreground text-xs">
                            <template v-if="decideForm.decision_type === 'suspend_full'">The scholarship drops to zero — no value needed.</template>
                            <template v-else-if="!takesAmount">This outcome does not change the scholarship, so no value is needed.</template>
                            <template v-else-if="impact.original_type === 'percentage'"> Currently {{ impact.original_amount }}% of tuition. Enter the percentage that remains, not the amount removed. </template>
                            <template v-else-if="impact.original_type === 'fixed_amount'"> Currently {{ formatCurrency(impact.original_amount) }}. Enter the amount that remains, not the amount removed. </template>
                            <template v-else>Leave blank unless you are reducing the scholarship.</template>
                        </p>
                        <InputError :message="decideForm.errors.adjusted_amount" />
                    </div>
                    <!-- What this decision costs the student, computed by Finance. -->
                    <div class="bg-muted/40 rounded-lg border p-4 md:col-span-2">
                        <p class="mb-3 text-sm font-medium">
                            Effect on this semester's tuition
                            <span v-if="impactLoading" class="text-muted-foreground font-normal">— updating…</span>
                        </p>

                        <p v-if="!impact.has_invoice" class="text-muted-foreground text-sm">
                            No tuition invoice exists for {{ dossier.target_semester?.name }} yet, so the exact amounts cannot be shown. They will apply automatically once the invoice is generated.
                        </p>

                        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <p class="text-muted-foreground text-xs">Tuition for the semester</p>
                                <p class="font-medium">{{ formatCurrency(impact.tuition_base) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">Scholarship covers</p>
                                <p class="font-medium">
                                    {{ formatCurrency(impact.current_discount) }}
                                    <template v-if="impact.adjusted_discount !== null">
                                        → <span class="text-destructive">{{ formatCurrency(impact.adjusted_discount) }}</span>
                                    </template>
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">Student pays</p>
                                <p class="font-medium">
                                    {{ formatCurrency(impact.payable_before) }}
                                    <template v-if="impact.payable_after !== null">
                                        → <span class="text-destructive">{{ formatCurrency(impact.payable_after) }}</span>
                                    </template>
                                </p>
                            </div>
                        </div>

                        <p v-if="impact.has_invoice && impact.delta !== null && impact.delta > 0" class="text-destructive mt-3 text-sm font-medium">The student will owe {{ formatCurrency(impact.delta) }} more.</p>
                        <p v-else-if="impact.has_invoice && impact.delta === 0 && impact.adjusted_discount !== null" class="mt-3 text-sm font-medium text-amber-600">
                            This changes nothing — the scholarship is already capped at the tuition amount, so the student pays the same.
                        </p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="reason">Why this outcome <span class="text-destructive">*</span></Label>
                        <Textarea id="reason" v-model="decideForm.reason" rows="2" />
                        <InputError :message="decideForm.errors.reason" />
                    </div>
                    <div class="md:col-span-2">
                        <Alert v-if="confirmationBlocks" variant="destructive" class="mb-3">
                            <AlertDescription> The student has not confirmed the interview notes yet, so a change that increases their fees cannot be submitted. Ask them to confirm, or record a confirmation on their behalf above. </AlertDescription>
                        </Alert>
                        <Button type="submit" :disabled="decideForm.processing || confirmationBlocks">Propose this decision</Button>
                    </div>
                </form>

                <div v-if="dossier.status === 'ready_for_decision'">
                    <p class="text-muted-foreground text-sm">Proposed: {{ labelFor(DECISION_TYPE_LABELS, dossier.decision_type) }} — {{ dossier.decision_reason }}</p>
                    <Button class="mt-2" variant="secondary" @click="submitApprove">Approve this decision</Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
