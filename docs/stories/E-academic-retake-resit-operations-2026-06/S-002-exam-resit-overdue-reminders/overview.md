# ACAD-RET-002 Exam-Resit Overdue Reminders

## Status

implemented (first slice) — 2026-06-21

## Lane

high-risk

## Portal Impact

none

This story changes internal staff/HQ web workflows and reminder delivery for
existing Finance obligations. It does not change `/api/v1/student/*` or
`/api/v1/lecturer/*` contracts.

## Current Behavior

`ACAD-RET-001` has created the Academic source contract for `thi lại`
(`ExamResitAttempt`), including charge handoff, DNG/payment sync, scheduling,
result completion, legacy reconciliation, Fee Monitor inference, and timetable
merge.

The remaining gap is collection follow-up for unpaid exam-resit attempts.
Academic can see `Chờ thanh toán`, but the system does not yet classify
exam-resit attempts as overdue, expose `last_reminded_at`, or provide a focused
HQ action to send PTL reminders from the same queue.

The existing `finance.operations.due-calendar` route renders the legacy
`DNG Due Reminders` page from DNG requests. The new Finance UI also has Batch
Studio reminder screens, but they require a wizard path before staff can inspect
the queue.

## Target Behavior

Move the existing Due Reminders surface into the new Finance Office IA and
upgrade it into the primary worklist/action page for payment reminders. Staff
should open the page and immediately see due/overdue rows, including PTL
exam-resit rows, then preview and send reminders from the same surface.

For exam resit:

- Overdue status is derived from Academic source state plus Finance payment
  evidence, never from a manual paid checkbox.
- A scheduled unpaid `ExamResitAttempt` becomes overdue after its scheduled
  sitting plus the snapshotted grace period, defaulting to 14 days.
- Rows that have not reached charge/DNG creation are visible as
  `needs_charge_or_dng` and hand off to the DNG worklist/Batch Studio DNG flow;
  they are not directly remindable.
- Rows with an active pushed DNG request can be reminded through the existing
  Finance reminder delivery path.
- `last_reminded_at` is visible and updated only after a successful send.

## Affected Users

- HQ/Finance staff: triage and send reminders for due/overdue DNG obligations,
  including PTL exam-resit fees.
- Academic staff: continue to see payment state while scheduling/completing exam
  resit, without owning reminder delivery or paid confirmation.
- Students/parents: receive payment reminders for PTL only when Finance has a
  valid reminder target.

## Affected Product Docs

- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/overview.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/design.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/validation.md`
- `docs/stories/E-finance-module-review-2026-06/S-017-finance-reporting-fee-monitor/overview.md`

## Non-Goals

- Do not create a second reminder page that only lists rows and sends staff to a
  separate page to act.
- Do not move paid-state ownership to Academic.
- Do not implement student self-request portal/API.
- Do not change DNG provider protocol or payment webhook behavior.
- Do not solve paid cancellation/refund/reversal in this story.
