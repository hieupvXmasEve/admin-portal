# 12 — Enforce the exact DNG reservation lifecycle

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Make the DNG active slot represent one exact live collection target. A request may be reused only when its payer, campus, fee type, semester, invoice-line targets, installment targets, amount, fingerprint, and provider identity match the requested reservation. Terminal requests must release their active slot atomically so a later legitimate collection is not blocked.

Align the Batch Studio preview and commit behavior with the approved lifecycle. The UI must not promise automatic cancellation and replacement unless the backend actually performs that guarded operation.

## Acceptance criteria

- [x] Reuse of an existing DNG request requires an exact match of payer, campus, fee type, semester, targets, amount, fingerprint, settlement version policy, and deterministic provider identity.
- [x] A mismatched active request is never silently linked to a new invoice line or installment.
- [x] Paid, cancelled, and failed terminal transitions release `active_slot_key` atomically while preserving provider and historical evidence.
- [x] A new valid request can occupy the released slot without violating the unique active-slot constraint.
- [x] The approved conflict behavior is implemented consistently: either fail closed for staff resolution or perform a guarded cancellation-and-replacement flow before creating the new target.
- [x] Batch Studio preview, confirmation copy, result counters, and backend behavior describe the same conflict and replacement semantics.
- [x] Tests cover terminal-slot release, exact retry reuse, changed semester, changed target lines, changed installment, changed amount/fingerprint, and concurrent slot acquisition.
- [x] Characterization includes the cancelled-slot cases represented locally by DNG `#160` for student `AUH16667` and DNG `#233` for student `AUH111841`, without hard-coding those production-derived rows into automated fixtures.

## Blocked by

- [Issue 11 — Push the next installment atomically](11-push-next-installment-atomically.md)

## Verification

- `./scripts/dev.sh artisan migrate --force` released terminal historical slots without altering their provider evidence. Local DNG `#160` (`AUH16667`) and `#233` (`AUH111841`) remain `cancelled` with `active_slot_key = null`.
- `./scripts/dev.sh artisan test --compact` completed successfully. Focused DNG, webhook, reconciliation, Batch Studio, worklist, cancellation, and installment suites also passed.
- `./scripts/dev.sh npm run type-check` completed successfully.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` completed successfully.
