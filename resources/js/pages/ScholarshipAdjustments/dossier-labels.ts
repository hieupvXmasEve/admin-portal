/**
 * Human-readable labels for the scholarship-adjustment enums.
 *
 * The backend stores machine values (`ready_for_decision`, `suspend_full`, …);
 * nothing in this feature's UI should render one of those directly. Shared by
 * Index.vue, Show.vue, and CandidatesPreview.vue so the wording stays identical
 * across the list, the detail page, and the scan screen.
 *
 * Source of truth for the value sets:
 * app/Modules/Academic/Progression/Models/ScholarshipAdjustmentDossier.php
 */

type LabelMap = Record<string, string>;

export const DOSSIER_STATUS_LABELS: LabelMap = {
    identified: 'Identified',
    interview_scheduled: 'Interview scheduled',
    interviewed: 'Interview done',
    awaiting_student_confirmation: 'Awaiting student confirmation',
    ready_for_decision: 'Awaiting approval',
    approved: 'Approved',
    applied: 'Applied to fees',
    closed: 'Closed',
    student_disputed: 'Student disputed',
    student_no_show: 'Student did not attend',
    confirmation_overdue: 'Confirmation overdue',
    no_adjustment: 'No adjustment needed',
    cancelled: 'Cancelled',
    finance_review_required: 'Needs finance review',
};

export const INTERVIEW_STATUS_LABELS: LabelMap = {
    not_scheduled: 'Not scheduled',
    scheduled: 'Scheduled',
    completed: 'Completed',
    student_no_show: 'Student did not attend',
    rescheduled: 'Rescheduled',
    cancelled: 'Cancelled',
};

export const CONFIRMATION_STATUS_LABELS: LabelMap = {
    pending: 'Waiting for student',
    confirmed: 'Confirmed by student',
    disputed: 'Disputed by student',
    declined: 'Declined by student',
    overdue: 'Overdue',
    dispute_overruled: 'Dispute overruled by approver',
};

export const DECISION_TYPE_LABELS: LabelMap = {
    keep: 'Keep scholarship',
    reduce: 'Reduce scholarship',
    suspend_full: 'Suspend entirely',
    defer: 'Defer decision',
    cancel: 'Cancel scholarship',
};

export const DOSSIER_SOURCE_LABELS: LabelMap = {
    system: 'Auto-detected',
    manual: 'Added manually',
};

export const SCHOLARSHIP_TYPE_LABELS: LabelMap = {
    percentage: 'Percentage',
    fixed: 'Fixed amount',
};

/** Falls back to the raw value so an unmapped enum is visible, never blank. */
export function labelFor(map: LabelMap, value: string | null | undefined, fallback = '—'): string {
    if (!value) return fallback;
    return map[value] ?? value;
}
