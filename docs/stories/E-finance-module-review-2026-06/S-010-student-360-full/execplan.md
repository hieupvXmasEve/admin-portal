# Exec Plan

## Goal

Implement and accept Milestone 2 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as one
consolidated Harness story.

The milestone augments the Milestone 1 Student 360 shell into a full operator
surface without changing the route, duplicating money math, or weakening DNG and
payment safeguards.

## Scope

In scope:

- Status-card read models, grouped ledger, action flags, and unapplied payment
  id.
- Read-only manual allocation preview endpoint.
- Record-payment adapter over `PaymentService::recordPayment`.
- Reviewed DNG cancel flow with impact preview, blocking reasons, reason,
  acknowledgement, and linked-charge void gate.
- Student 360 display components: status cards, DNG stepper, and dual ledger
  lens.
- Action menu, drawers, installment push, and focus highlight.
- Full M2 validation, permission matrix, invariant evidence, and Harness trace.

Out of scope:

- Milestone 1 shell/foundation work.
- Cockpit and Batch Studio.
- New money calculations or ledger tables.
- Student/lecturer portal API changes.
- New campaign/collection-cycle persistence.

## Risk Classification

Risk flags:

- Authorization and campus scope.
- Finance write paths.
- DNG cancel/void behavior.
- PII on staff-facing pages.
- Existing behavior around payment allocation and installment push.
- Weak browser automation coverage for drawer/focus UI.

Hard gates:

- No route/destination change from M1.
- Write paths must wrap existing tested actions/services.
- DNG cancel must require `void_finance_charges` when linked charges exist.
- Money state changes require finance invariant evidence.
- No new money math in Vue, controllers, or ad hoc helpers.

## Work Phases

1. Add/read status-card and ledger read models.
2. Add read-only allocation preview.
3. Add record-payment adapter.
4. Add reviewed DNG cancel impact and submit flow.
5. Render the full Student 360 page.
6. Wire action menu, drawers, and focus behavior.
7. Run backend suites, frontend checks, invariant command, and manual UI smoke.
8. Record evidence on this story and keep the retired child packets historical.

## Stop Conditions

Pause for human confirmation if:

- A step requires new money math.
- A write path lacks an existing action/service boundary.
- DNG cancel cannot preserve audit/void permissions.
- The Student 360 route would need to change.
- A portal API contract would be affected.
