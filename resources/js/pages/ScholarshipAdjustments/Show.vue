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
import { formatCurrency, formatDate, formatDateTime } from '@/utils/format';
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
    finance_review_note: string | null;
    decided_at: string | null;
    approved_at: string | null;
    proposed_by: { name: string } | null;
    approved_by: { name: string } | null;
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

const props = defineProps<{ dossier: Dossier; preview: ImpactPreview; can: { decide: boolean; approve: boolean } }>();

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
// Prefilled from the dossier so a decision already on record is what the panel
// shows. An undecided dossier starts on an EMPTY outcome on purpose: defaulting
// to 'keep' silently resolved dossiers as no_adjustment when the maker filled
// everything else and submitted without touching the select.
// adjusted_amount is `nullable` server-side; undefined keeps the Input's
// model type happy and is simply omitted from the payload when left blank.
const decideForm = useForm({
    decision_type: props.dossier.decision_type ?? '',
    adjusted_amount: props.dossier.decision_adjusted_amount !== null ? Number(props.dossier.decision_adjusted_amount) : (undefined as number | undefined),
    reason: props.dossier.decision_reason ?? '',
    exception_override_reason: '',
});
const onBehalfForm = useForm({ on_behalf_note: '' });
const minutesForm = useForm({ minutes: props.dossier.minutes ?? '' });
const overruleForm = useForm({ overrule_reason: '' });

const isDisputed = computed(() => props.dossier.confirmation_status === 'disputed');

/**
 * A proposal already on record is READ-ONLY by default. The checker's job here
 * is to approve what the maker proposed — an editable panel let an approver
 * overwrite the proposal (back to the old 'keep' default) and then approve
 * their own rewrite in one sitting, with no trace of the original.
 * Revising stays possible, but only behind an explicit action and only for
 * someone who holds the decide permission.
 */
const isProposed = computed(() => props.dossier.status === 'ready_for_decision');
const isEditing = ref(false);
const decisionLocked = computed(() => isProposed.value && !isEditing.value);

/**
 * Once approved, the decision panel's form is gone for good — without a
 * read-back the card renders empty and nobody can see what was actually
 * decided. The record itself is what this shows: outcome, the before/after
 * scholarship, the reason, and who proposed/approved it.
 */
const isSettled = computed(() => props.dossier.decision_type !== null && !isProposed.value);

/** Reads the award value the same way the amount field does (percentage vs money). */
function formatAward(value: string | number | null): string {
    if (value === null) return '—';
    return props.dossier.original_type === 'percentage' ? `${Number(value)}% học phí` : formatCurrency(Number(value));
}

/** What the approved outcome means for the student's fees, in plain words. */
const settledOutcome = computed(() => {
    switch (props.dossier.status) {
        case 'applied':
            return 'Học phí của học kỳ áp dụng đã được cập nhật theo quyết định này.';
        case 'no_adjustment':
            return 'Học bổng giữ nguyên nên học phí không thay đổi.';
        case 'cancelled':
            return 'Học bổng đã bị huỷ.';
        case 'finance_review_required':
            // Finance's own explanation when it has one — it names the actual
            // blocker (invoice already paid, DNG in flight, …).
            return props.dossier.finance_review_note ?? 'Hệ thống chưa tự cập nhật được học phí — phòng tài chính cần xử lý thủ công hồ sơ này.';
        case 'not_applicable':
            return (
                props.dossier.finance_review_note ??
                'Kỳ áp dụng không thu học phí theo lộ trình đóng tiền nên điều chỉnh này không có gì để áp dụng.'
            );
        case 'approved':
            return 'Đã duyệt. Học phí sẽ được cập nhật khi hoá đơn của học kỳ áp dụng được phát hành.';
        default:
            return null;
    }
});

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
        toast.error(errors.error ?? Object.values(errors)[0] ?? 'Không thực hiện được thao tác này.');
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
    decideForm.post(route('scholarship-adjustments.decide', props.dossier.id), {
        ...visitOptions,
        onSuccess: (visited: Page) => {
            // Back to read-only once the revision is on record — chaining to
            // visitOptions keeps the flash toast this page relies on.
            isEditing.value = false;
            visitOptions.onSuccess(visited);
        },
    });
}

/** Abandoning a revision restores what is actually on record, not the edits. */
function cancelEditing() {
    isEditing.value = false;
    decideForm.decision_type = props.dossier.decision_type ?? '';
    decideForm.adjusted_amount = props.dossier.decision_adjusted_amount !== null ? Number(props.dossier.decision_adjusted_amount) : undefined;
    decideForm.reason = props.dossier.decision_reason ?? '';
    decideForm.clearErrors();
}

function submitApprove() {
    useForm({}).post(route('scholarship-adjustments.approve', props.dossier.id), visitOptions);
}
</script>

<template>
    <Head title="Xét điều chỉnh học bổng" />

    <div class="flex items-center justify-between">
        <Heading :title="`${dossier.student?.full_name} (${dossier.student?.student_id})`" :description="`Trượt môn ở ${dossier.source_semester?.name} — mọi điều chỉnh sẽ áp dụng cho ${dossier.target_semester?.name}`" />
        <Badge>{{ labelFor(DOSSIER_STATUS_LABELS, dossier.status) }}</Badge>
    </div>

    <Alert v-if="flashError" variant="destructive" class="mt-4">
        <AlertDescription>{{ flashError }}</AlertDescription>
    </Alert>

    <Alert v-if="dossier.needs_data_review" variant="destructive" class="mt-4">
        <AlertDescription> Một số điểm ở đây được chốt trước khi hệ thống sửa lỗi tính đạt/không đạt (ngày 4/7/2026) nên có thể chưa chính xác. Hãy kiểm tra lại thủ công trước khi ra quyết định. </AlertDescription>
    </Alert>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Lý do sinh viên này được đưa vào xét</CardTitle>
                <CardDescription>{{ labelFor(DOSSIER_SOURCE_LABELS, dossier.source) }}</CardDescription>
            </CardHeader>
            <CardContent>
                <p v-if="dossier.manual_exception_reason" class="text-sm"><span class="text-muted-foreground">Lý do thêm thủ công:</span> {{ dossier.manual_exception_reason }}</p>
                <p class="text-sm">
                    <span class="text-muted-foreground">Học bổng hiện tại:</span>
                    {{ dossier.original_scholarship_code }} — {{ formattedOriginalAward }}
                </p>
                <p class="text-muted-foreground mt-4 mb-1 text-sm">Môn bị trượt</p>
                <ul class="space-y-1 text-sm">
                    <li v-for="(course, i) in dossier.failed_courses_snapshot" :key="i">
                        {{ course.unit_code }} — {{ course.unit_name }}
                        <span class="text-muted-foreground">(lần học thứ {{ course.attempt_no }}, chốt điểm {{ formatDate(course.grade_finalized_date) }})</span>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Phỏng vấn</CardTitle>
                <CardDescription>{{ labelFor(INTERVIEW_STATUS_LABELS, dossier.interview_status) }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <form v-if="dossier.interview_status === 'not_scheduled' || dossier.interview_status === 'rescheduled'" class="space-y-3" @submit.prevent="submitSchedule">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label class="flex items-center gap-1.5"><CalendarIcon class="h-3.5 w-3.5" /> Ngày</Label>
                            <DatePicker v-model="scheduleForm.scheduled_date" placeholder="Chọn ngày" />
                        </div>
                        <div class="space-y-2">
                            <Label class="flex items-center gap-1.5"><Clock class="h-3.5 w-3.5" /> Giờ</Label>
                            <TimePicker v-model="scheduleForm.scheduled_time" />
                        </div>
                    </div>
                    <Button type="submit" :disabled="scheduleForm.processing || !scheduleForm.scheduled_date || !scheduleForm.scheduled_time"> Đặt lịch phỏng vấn </Button>
                </form>

                <form v-if="dossier.interview_status === 'scheduled'" class="space-y-3" @submit.prevent="submitComplete">
                    <div class="space-y-2">
                        <Label for="minutes">Nội dung đã thống nhất trong buổi phỏng vấn</Label>
                        <Textarea id="minutes" v-model="completeForm.minutes" rows="4" />
                    </div>
                    <InputError :message="completeForm.errors.minutes" />
                    <Button type="submit" :disabled="completeForm.processing">Lưu biên bản và gửi sinh viên xác nhận</Button>
                </form>

                <div v-if="dossier.minutes" class="text-sm">
                    <p class="text-muted-foreground">Biên bản phỏng vấn (bản chỉnh sửa thứ {{ dossier.minutes_version }})</p>
                    <p class="whitespace-pre-wrap">{{ dossier.minutes }}</p>
                </div>
            </CardContent>
        </Card>

        <Card class="lg:col-span-2">
            <CardHeader>
                <CardTitle>Xác nhận của sinh viên</CardTitle>
                <CardDescription>Sinh viên phải đồng ý với biên bản phỏng vấn thì mới ra được quyết định điều chỉnh học bổng.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm">
                    <Badge>{{ labelFor(CONFIRMATION_STATUS_LABELS, dossier.confirmation_status, 'Chưa gửi yêu cầu xác nhận') }}</Badge>
                    <Badge v-if="dossier.confirmed_on_behalf" variant="secondary" class="ml-1">Cán bộ xác nhận thay</Badge>
                </p>
                <p v-if="dossier.student_comment" class="text-sm"><span class="text-muted-foreground">Ý kiến của sinh viên:</span> {{ dossier.student_comment }}</p>

                <!-- A student who answered in disagreement is never "confirmed"
                     for. Two honest routes: fix the notes, or overrule on the record. -->
                <div v-if="isDisputed" class="space-y-4">
                    <Alert variant="destructive">
                        <AlertDescription> Sinh viên không đồng ý với biên bản phỏng vấn. Bạn không thể xác nhận thay — hãy sửa lại biên bản rồi gửi hỏi lại, hoặc để người có quyền duyệt bác bỏ phản đối kèm lý do bằng văn bản. </AlertDescription>
                    </Alert>

                    <form class="space-y-2" @submit.prevent="submitEditMinutes">
                        <Label for="corrected_minutes">Biên bản phỏng vấn đã chỉnh sửa</Label>
                        <Textarea id="corrected_minutes" v-model="minutesForm.minutes" rows="4" />
                        <p class="text-muted-foreground text-xs">Lưu lại sẽ tạo một bản chỉnh sửa mới và gửi sinh viên xác nhận lại.</p>
                        <InputError :message="minutesForm.errors.minutes" />
                        <Button type="submit" :disabled="minutesForm.processing || !minutesForm.minutes.trim()">Lưu biên bản đã sửa và hỏi lại</Button>
                    </form>

                    <form class="space-y-2 border-t pt-4" @submit.prevent="submitOverrule">
                        <Label for="overrule_reason">Hoặc bác bỏ phản đối — nêu lý do vẫn giữ quyết định điều chỉnh</Label>
                        <Textarea id="overrule_reason" v-model="overruleForm.overrule_reason" rows="2" />
                        <p class="text-muted-foreground text-xs">Nội dung được lưu vào hồ sơ. Sinh viên vẫn được ghi nhận là không đồng ý — thao tác này không thay sinh viên đồng ý.</p>
                        <Button type="submit" variant="destructive" :disabled="overruleForm.processing || overruleForm.overrule_reason.trim().length < 10"> Bác bỏ phản đối </Button>
                    </form>
                </div>

                <p v-if="dossier.confirmation_status === 'dispute_overruled'" class="text-sm"><span class="text-muted-foreground">Lý do bác bỏ:</span> {{ dossier.dispute_overrule_reason }}</p>

                <!-- Confirm-on-behalf: only for a student who has not answered. -->
                <form v-if="['pending', 'overdue'].includes(dossier.confirmation_status ?? '')" class="space-y-2" @submit.prevent="submitOnBehalf">
                    <Label for="on_behalf_note">Bạn đã liên hệ sinh viên bằng cách nào và sinh viên đồng ý điều gì</Label>
                    <Textarea id="on_behalf_note" v-model="onBehalfForm.on_behalf_note" rows="2" />
                    <InputError :message="onBehalfForm.errors.on_behalf_note" />
                    <Button type="submit" variant="secondary" :disabled="onBehalfForm.processing">Xác nhận thay sinh viên</Button>
                </form>
            </CardContent>
        </Card>

        <Card class="lg:col-span-2">
            <CardHeader>
                <CardTitle>Quyết định về học bổng</CardTitle>
                <CardDescription>Một người đề xuất thay đổi; một người khác phải duyệt.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <form v-if="dossier.status === 'interviewed' || dossier.status === 'ready_for_decision'" class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitDecide">
                    <div class="space-y-2">
                        <Label for="decision_type">Hình thức xử lý học bổng</Label>
                        <Select v-model="decideForm.decision_type" :disabled="decisionLocked">
                            <SelectTrigger id="decision_type" class="w-full">
                                <SelectValue placeholder="Chọn hình thức xử lý" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, value) in DECISION_TYPE_LABELS" :key="value" :value="value">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="decideForm.errors.decision_type" />
                    </div>
                    <div class="space-y-2">
                        <Label for="adjusted_amount">
                            Mức học bổng sinh viên còn được giữ
                            <span v-if="impact.original_type === 'percentage'" class="text-muted-foreground">(% học phí)</span>
                            <span v-else-if="impact.original_type === 'fixed_amount'" class="text-muted-foreground">(số tiền, ₫)</span>
                        </Label>
                        <Input id="adjusted_amount" v-model.number="decideForm.adjusted_amount" type="number" step="0.01" :disabled="!takesAmount || decisionLocked" />
                        <p class="text-muted-foreground text-xs">
                            <template v-if="decideForm.decision_type === 'suspend_full'">Học bổng về 0 — không cần nhập giá trị.</template>
                            <template v-else-if="!takesAmount">Hình thức này không làm thay đổi học bổng nên không cần nhập giá trị.</template>
                            <template v-else-if="impact.original_type === 'percentage'"> Hiện tại là {{ impact.original_amount }}% học phí. Nhập phần trăm CÒN LẠI, không phải phần bị cắt. </template>
                            <template v-else-if="impact.original_type === 'fixed_amount'"> Hiện tại là {{ formatCurrency(impact.original_amount) }}. Nhập số tiền CÒN LẠI, không phải phần bị cắt. </template>
                            <template v-else>Để trống trừ khi bạn đang giảm học bổng.</template>
                        </p>
                        <InputError :message="decideForm.errors.adjusted_amount" />
                    </div>
                    <!-- What this decision costs the student, computed by Finance. -->
                    <div class="bg-muted/40 rounded-lg border p-4 md:col-span-2">
                        <p class="mb-3 text-sm font-medium">
                            Ảnh hưởng tới học phí của học kỳ này
                            <span v-if="impactLoading" class="text-muted-foreground font-normal">— đang cập nhật…</span>
                        </p>

                        <p v-if="!impact.has_invoice" class="text-muted-foreground text-sm">
                            {{ dossier.target_semester?.name }} chưa có hoá đơn học phí nên chưa hiển thị được số tiền cụ thể. Điều chỉnh sẽ tự áp dụng khi hoá đơn được phát hành.
                        </p>

                        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <p class="text-muted-foreground text-xs">Học phí học kỳ</p>
                                <p class="font-medium">{{ formatCurrency(impact.tuition_base) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">Học bổng bù</p>
                                <p class="font-medium">
                                    {{ formatCurrency(impact.current_discount) }}
                                    <template v-if="impact.adjusted_discount !== null">
                                        → <span class="text-destructive">{{ formatCurrency(impact.adjusted_discount) }}</span>
                                    </template>
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">Sinh viên phải đóng</p>
                                <p class="font-medium">
                                    {{ formatCurrency(impact.payable_before) }}
                                    <template v-if="impact.payable_after !== null">
                                        → <span class="text-destructive">{{ formatCurrency(impact.payable_after) }}</span>
                                    </template>
                                </p>
                            </div>
                        </div>

                        <p v-if="impact.has_invoice && impact.delta !== null && impact.delta > 0" class="text-destructive mt-3 text-sm font-medium">Sinh viên sẽ phải đóng thêm {{ formatCurrency(impact.delta) }}.</p>
                        <p v-else-if="impact.has_invoice && impact.delta === 0 && impact.adjusted_discount !== null" class="mt-3 text-sm font-medium text-amber-600">
                            Thay đổi này không làm chênh lệch số tiền — học bổng vốn đã bị chặn ở mức học phí nên sinh viên vẫn đóng như cũ.
                        </p>
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="reason">Lý do chọn hình thức xử lý này <span class="text-destructive">*</span></Label>
                        <Textarea id="reason" v-model="decideForm.reason" rows="2" :disabled="decisionLocked" />
                        <InputError :message="decideForm.errors.reason" />
                    </div>
                    <div class="md:col-span-2">
                        <Alert v-if="confirmationBlocks && !decisionLocked" variant="destructive" class="mb-3">
                            <AlertDescription> Sinh viên chưa xác nhận biên bản phỏng vấn nên chưa thể gửi một thay đổi làm tăng học phí. Hãy nhắc sinh viên xác nhận, hoặc xác nhận thay sinh viên ở phần trên. </AlertDescription>
                        </Alert>
                        <Button v-if="!decisionLocked" type="submit" :disabled="decideForm.processing || confirmationBlocks">
                            {{ isProposed ? 'Lưu quyết định đã sửa' : 'Gửi đề xuất quyết định' }}
                        </Button>
                    </div>
                </form>

                <!-- Checker view: the proposal above is read-only until someone
                     who may decide explicitly chooses to revise it. -->
                <div v-if="isProposed" class="flex flex-wrap items-center gap-2">
                    <p v-if="decisionLocked" class="text-muted-foreground w-full text-sm">
                        Do {{ dossier.proposed_by?.name ?? 'một đồng nghiệp' }} đề xuất — duyệt nguyên như vậy, hoặc sửa lại trước khi duyệt.
                    </p>
                    <Button v-if="can.approve && decisionLocked" variant="secondary" @click="submitApprove">Duyệt quyết định này</Button>
                    <Button v-if="can.decide && decisionLocked" variant="outline" @click="isEditing = true">Sửa lại quyết định</Button>
                    <Button v-if="isEditing" variant="ghost" @click="cancelEditing">Huỷ</Button>
                    <p v-if="!can.approve && decisionLocked" class="text-muted-foreground w-full text-xs">Bạn không có quyền duyệt quyết định này.</p>
                </div>

                <!-- Quyết định đã chốt: form không còn hiển thị nữa, nên đây là
                     nơi duy nhất đọc lại được đã quyết định những gì. -->
                <div v-if="isSettled" class="space-y-4">
                    <div class="bg-muted/40 grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-3">
                        <div>
                            <p class="text-muted-foreground text-xs">Hình thức xử lý</p>
                            <p class="font-medium">{{ labelFor(DECISION_TYPE_LABELS, dossier.decision_type) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Mức học bổng</p>
                            <p class="font-medium">
                                {{ formatAward(dossier.original_amount) }}
                                <template v-if="dossier.decision_adjusted_amount !== null">
                                    → <span class="text-destructive">{{ formatAward(dossier.decision_adjusted_amount) }}</span>
                                </template>
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Áp dụng cho</p>
                            <p class="font-medium">{{ dossier.target_semester?.name }}</p>
                        </div>
                    </div>

                    <p class="text-sm"><span class="text-muted-foreground">Lý do:</span> {{ dossier.decision_reason }}</p>

                    <div class="text-muted-foreground grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                        <p>Người đề xuất: {{ dossier.proposed_by?.name ?? '—' }} · {{ formatDateTime(dossier.decided_at) }}</p>
                        <p>Người duyệt: {{ dossier.approved_by?.name ?? '—' }} · {{ formatDateTime(dossier.approved_at) }}</p>
                    </div>

                    <Alert v-if="settledOutcome" :variant="dossier.status === 'finance_review_required' ? 'destructive' : 'default'">
                        <AlertDescription>{{ settledOutcome }}</AlertDescription>
                    </Alert>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
