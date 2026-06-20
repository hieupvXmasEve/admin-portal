# Exec Plan

## Goal

Make the new Finance UI's Due Reminders surface the primary worklist/action page
for DNG payment reminders, including PTL exam-resit overdue monitoring.

## Scope

In scope:

- Move `DNG Due Reminders` from Legacy Finance sidebar into
  `Finance Office (New UI) -> Thu & Đối soát`.
- Keep `finance.operations.due-calendar` as the route unless implementation
  discovery finds a strong reason to rename.
- Extend the due-reminder query to show PTL exam-resit context and overdue
  state.
- Show `needs_charge_or_dng` handoff rows for overdue exam-resit sources without
  an active DNG reminder target.
- Support in-page preview/confirm/send for student and parent reminders.
- Update `last_reminder_at` / `last_reminded_at` only after successful reminder
  delivery.
- Keep paid state derived from canonical Finance charge/payment evidence.

Out of scope:

- Student self-request portal/API.
- Paid cancellation refund/reversal.
- DNG provider protocol changes.
- Manual paid confirmation for exam resit.
- A second passive-only reminder page.

## Risk Classification

Risk flags:

- external-systems
- public-contracts
- existing-behavior
- multi-domain
- weak-proof

Hard gates:

- Reminder email delivery behavior.
- Any change to DNG due/reminder recipient selection.
- Any change to Finance paid-state derivation.
- Any change that makes Academic the owner of fee collection.

## Work Phases

1. Discovery
   - Re-read `DueCalendar.vue`, `ListDueItemsQuery`, reminder actions, Batch
     Studio reminder preview/commit, DNG worklist, and `ExamResitAttempt` charge
     linkage.
   - Verify how DNG request rows link to `ExamResitAttempt` for PTL after
     ACAD-RET-001.
2. Navigation and IA
   - Move the existing Due Reminders sidebar entry into the new Finance Office
     `Thu & Đối soát` group.
   - Decide whether Batch Studio Reminders remains visible in the Batch Studio
     hub or becomes a deep workflow only.
3. Query and row contract
   - Add PTL/exam-resit source context to due rows.
   - Add `needs_charge_or_dng`, `remindable`, and `blocked` reminder states.
   - Add overdue calculation from scheduled exam time plus grace days for
     unpaid-before-payment exam resit rows.
4. UI workflow
   - Add filters and badges for fee type/source/due state.
   - Keep selected-row preview/confirm/send in the same page.
   - Hand off non-remindable source rows to DNG worklist/Batch Studio DNG.
5. Delivery and audit
   - Reuse existing Finance reminder actions where safe.
   - Update reminder timestamps only after successful send.
   - Log skipped rows and delivery outcomes.
6. Validation and Harness
   - Run targeted Finance reminder/query tests, Academic exam-resit tests when
     source joins are touched, frontend lint/format, and `git diff --check`.
   - Record Harness trace with exact proof and any baseline validation gaps.

## Stop Conditions

Pause for human confirmation if:

- PTL DNG rows cannot be linked reliably to `ExamResitAttempt`.
- Reminder recipient rules differ for PTL from standard payment reminders.
- Batch Studio preview-token safety cannot be reused without major rewrite.
- A needed reminder timestamp would require overwriting historical payment or DNG
  evidence.
- The sidebar move conflicts with another accepted Finance cutover story.
