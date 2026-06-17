# Exec Plan

## Goal

Make Student 360 installment actions safe by ensuring only staff-approved,
ledger-collectable installments can display as `Trả góp` or be pushed to DNG.

## Scope

In scope:

- Define how the system marks a staff-approved installment plan.
- Update Student 360 installment-card query semantics.
- Add backend guards to installment DNG push actions.
- Align UI permissions with backend route permissions.
- Prepare deterministic data-repair queries for stale backfilled installment
  rows.
- Add regression tests for student `588`-style settled historical charges.

Out of scope:

- Running production data repair before target rows are exported and reviewed.
- Changing student or lecturer portal APIs.
- Reworking unrelated DNG webhook checksum/idempotency behavior.
- Replacing the full ledger source-of-truth story.

## Risk Classification

Risk flags:

- Data model.
- External systems.
- Existing behavior.
- Weak proof.
- Public/admin-visible behavior.

Hard gates:

- Data model and historical data repair.
- External provider behavior because unsafe rows can create DNG requests.

Lane: high-risk.

## Work Phases

1. Discovery: export all actionable `pending/awaiting` installments with charge
   ledger balance, charge status, DNG status, and source/backfill evidence.
2. Design: choose explicit staff-approval representation and document migration
   or repair implications.
3. Guard first: add backend rejection for zero-balance, voided, non-approved, or
   stale installment pushes.
4. Read model: update Student 360 status cards to show only approved collectable
   plans as `Trả góp`.
5. Data repair: after backup and target-row review, mark or migrate stale
   backfilled rows so they no longer appear actionable.
6. UI: update copy/actions so unpaid non-installment charges route staff to the
   normal DNG collection workflow or explicit plan creation.
7. Verification: run targeted backend tests, frontend checks for touched files,
   and finance invariant sampling.
8. Evidence: attach before/after data counts, command output, and any reviewed
   repair SQL to this story.

## Stop Conditions

Pause for human confirmation if:

- The team has not decided whether single-row staff-approved plans are valid.
- Any production repair would cancel, delete, or relabel DNG-linked rows.
- A migration is needed to encode plan approval.
- The fix would change student-facing API contracts.
- Validation would rely only on UI hiding without backend write guards.
