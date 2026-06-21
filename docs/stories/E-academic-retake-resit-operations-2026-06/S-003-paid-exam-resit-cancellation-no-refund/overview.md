# ACAD-RET-003 Paid Exam-Resit Cancellation Without Refund

## Status

implemented

## Lane

high-risk

## Portal Impact

student

This story changes staff-facing exam-resit cancellation behavior and the
student-visible state that follows it. It must preserve student Finance portal
payment evidence and may affect `/api/v1/student/timetable` visibility for
assigned exam-resit sittings. If implementation changes any student API response
shape, update `docs/api/student/timetable.md` and the student Nuxt portal types.

## Current Behavior

`ACAD-RET-001` added the Academic exam-resit (`thi lại`) source, scheduling,
completion, and staff worklist. The current cancel action allows cancelling
requested, approved, or scheduled attempts only while unpaid. A paid attempt is
blocked and the worklist hides the cancel action because the previous story
deferred the HQ refund/reversal path.

This is too strict for the operational case where Academic needs to cancel an
exam-resit sitting after the student has already paid, but the business decision
is **no refund**. In that case the provider/payment evidence must stay visible
in the student portal, and the staff action should cancel the exam-resit
operation or schedule without refunding the paid DNG/payment. The later
`ACAD-RET-004` ledger contract clarifies that the cancelled source charge is
voided and released cash remains as unapplied credit.

There is a second unsafe edge: if HQ already created the `exam_resit_fee` charge
and DNG request but the student has not paid yet, cancellation should still be
possible, but staff must see a clear confirmation that the fee-to-collect will be
cancelled/voided and the student will be notified.

## Target Behavior

Academic staff can cancel an exam-resit attempt from the `/exam-resit` worklist
even when it is scheduled or paid, as long as the student has not completed the
resit and no no-show result has been recorded.

Cancellation has two fee outcomes:

- **Paid attempt:** cancel the Academic exam-resit operation or assigned sitting,
  keep paid DNG/payment evidence intact, do not refund, do not cancel paid DNG
  evidence, void the cancelled source charge, and keep the paid amount visible
  in the student Finance portal as unapplied credit.
- **Charge created but unpaid:** require an explicit staff confirmation message,
  then cancel the Academic exam-resit operation, void the active
  `exam_resit_fee` charge, cancel any awaiting DNG request for that charge, and
  make clear to the student that the fee is no longer payable.

In both cases the student receives an email after the cancellation succeeds. The
email must say that the exam-resit attempt has been cancelled. For a paid
attempt, it must also say the paid fee remains recorded and no refund is created
by this action. For an unpaid charge, it must say the pending fee collection has
been cancelled.

Student timetable behavior must remain precise: a cancelled exam-resit sitting
must not appear as an upcoming scheduled exam. Student Finance must still show
paid payment/credit evidence for paid attempts because payment evidence is
retained.

## Affected Users

- Academic staff: cancel exam-resit attempts from `/exam-resit` with the correct
  confirmation and audit trail.
- HQ/Finance staff: trust that paid evidence remains intact and unpaid DNG/charge
  obligations are cancelled only after staff confirmation.
- Students: receive a clear cancellation email and still see paid finance
  history when they had already paid.

## Affected Product Docs

- `docs/stories/E-academic-retake-resit-operations-2026-06/README.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/overview.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/design.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/validation.md`
- `docs/api/student/timetable.md` if the student timetable payload changes

## Non-Goals

- Do not implement an external refund or auto-reallocation flow.
- Do not erase payment records or DNG paid evidence for paid attempts.
- Do not allow cancellation after a completed resit result or recorded no-show.
- Do not add student self-service cancellation.
- Do not change DNG provider webhook semantics.
