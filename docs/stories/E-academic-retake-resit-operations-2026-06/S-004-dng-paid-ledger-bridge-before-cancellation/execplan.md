# Exec Plan

## Goal

Bridge paid DNG evidence into the canonical local payment ledger before
cancelling exam-resit or course-retake sources, then void the cancelled source
charge without refund/reallocation so paid fees do not remain visible as unpaid
and the collected cash appears as unapplied credit while paid provider evidence
is preserved.

## Scope

In scope:

- Shared Finance bridge helper/action for paid DNG requests linked to a charge.
- Exam-resit cancellation paid-state classification from linked paid DNG.
- Course-retake cancellation paid-state classification from linked paid DNG.
- Paid cancellation voids the source charge with a no-refund reason and
  releases allocation to unapplied credit with `autoReallocate=false`.
- Tests proving paid DNG is bridged, paid DNG is not cancelled, and unpaid DNG is
  still cancelled only when unpaid.

Out of scope:

- Historical backfill command.
- Student Finance API shape changes.
- External refund workflow.
- Auto-reallocation workflow for released paid cash.
- New charge lifecycle status migration.

## Risk Classification

Risk flags:

- data-model
- audit-security
- external-provider
- public-contracts
- multi-domain
- student-portal
- existing-behavior

Hard gates:

- Do not cancel DNG requests with paid/reconciled evidence.
- Do not refund externally, cancel paid DNG/payment evidence, or auto-reallocate
  released paid cash for no-refund cancellation.
- Do not change student Finance balance formulas.
- Keep bridge idempotent; repeated cancellation attempts must not duplicate
  payments.

## Work Phases

1. Add failing tests for exam-resit and course-retake cancellation with paid DNG
   but missing local bridge.
2. Add shared Finance bridge action around existing `DngPaymentService`.
3. Wire exam-resit cancellation request/query/action to classify paid DNG.
4. Wire course-retake cancellation action to bridge paid DNG and avoid DNG cancel.
5. Void paid source charges after bridge with `autoReallocate=false`, keeping
   payment/DNG evidence and exposing released cash as unapplied credit.
6. Run targeted Academic, Finance, student Finance/API, Pint, and frontend checks.
7. Update story evidence and Harness trace.

## Stop Conditions

Pause if:

- paid DNG amount cannot be allocated deterministically to a charge;
- a cancellation scenario requires refund/reversal semantics;
- provider state and local charge amount mismatch beyond existing bridge caps;
- route/UI contract needs a new staff acknowledgement not already covered by
  ACAD-RET-003.
