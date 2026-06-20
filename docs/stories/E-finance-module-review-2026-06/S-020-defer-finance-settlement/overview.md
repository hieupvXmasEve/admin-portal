# FIN-REV-020 - Defer Finance Settlement

## Status

planned

## Lane

high-risk

## Current Behavior

Academic defer cases can preserve a whole semester or selected courses, but the
Finance meaning is not consistent enough for real operations.

Current gaps:

- `FULL` defer cases do not always have item-level course evidence.
- A deferred course registration can still be treated by some Finance exception
  logic as an enrolled registration that needs an active charge.
- Existing preserve logic can behave as "skip charge later", which conflicts
  with the practical Finance flow where a later re-enrollment should create a
  new charge and use any real paid balance already on the student's account.
- Voiding a source charge releases payments, discounts, installments, and may
  auto-reallocate cash unless the caller controls that behavior.
- DNG-linked charges cannot be voided safely without first resolving the live
  provider payment request.
- For `FORFEIT` and `PARTIAL`, the product rule is now explicit: consume only
  money the student has actually paid. Do not create synthetic debt for unpaid
  forfeited amounts unless a later accepted story defines a penalty product.

## Target Behavior

Defer is handled as a coordinated Academic + Finance transition using the
existing ledger as the source of truth.

Business target:

- `defer_cases` and `defer_case_items` express the academic right to preserve
  course participation.
- `course_registrations.registration_status = defer` means the original
  registration is non-billable for expected-fee and missing-charge logic.
- Finance charges, invoice lines, payment applications, discounts, DNG requests,
  and installments remain the money ledger. This story must not introduce a
  parallel settlement ledger by default.
- `PRESERVE` protects real paid cash by releasing it from the original deferred
  obligation and leaving it available for a future re-enrollment charge.
- `FORFEIT` and `PARTIAL` consume only real paid cash. The consumed portion is
  represented through existing Finance charge/adjustment mechanics, not through
  a new table and not by inventing unpaid debt.
- When the student later studies the deferred course again, Finance creates a
  normal new charge for the new registration and allocates any available
  account balance through the normal allocation flow.

## Affected Users

- Academic/Admin staff recording student defer actions.
- Finance staff reviewing charges, payments, exceptions, and Student 360.
- Finance leads auditing deferred students and historical carry-forward money.
- Students whose paid tuition is preserved or consumed by a defer policy.

## Affected Product Docs

- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/project-overview-pdr.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-002-ledger-source-of-truth/`
- `docs/stories/E-finance-module-review-2026-06/S-004-charge-discount-installment-correctness/`
- `docs/stories/E-finance-module-review-2026-06/S-005-dng-security-and-idempotency/`
- `docs/stories/E-finance-module-review-2026-06/S-017-finance-reporting-fee-monitor/`

## Portal Impact

Portal impact: none for the first implementation slice.

This story targets admin/staff Academic + Finance behavior. If implementation
changes `/api/v1/student/*` finance responses or student-visible payment
display, that follow-up must be split or explicitly update the student portal
contract.

## Acceptance Criteria

- Backfill item-level `defer_case_items` for existing full-scope defer cases
  where course registrations are known.
- Produce a dry-run/backfill report that separates auto-safe cases from cases
  needing review: no registration, no charge, multi-course tuition charge,
  discount, live DNG request, ambiguous partial amount, or inconsistent paid
  amount.
- Update runtime defer recording so new full-scope defer cases also capture
  item-level course evidence.
- Treat `registration_status = defer` as non-billable across expected-fee,
  missing-charge, retake-no-charge, Fee Monitor, and billing exception logic.
- Replace "skip charge later" behavior for preserve with "create normal charge
  on future re-enrollment and allocate preserved real cash".
- Apply defer Finance policy using existing ledger operations:
  - `PRESERVE`: release real paid cash from the deferred obligation without
    hidden auto-reallocation.
  - `FORFEIT`: consume only already-paid cash, capped at the paid amount.
  - `PARTIAL`: consume only already-paid cash up to the consumed amount; leave
    the remaining paid amount available for future allocation.
- Do not create a new settlement table or money ledger in the first
  implementation. If existing source references, charge metadata, action logs,
  and void reasons are not enough to prove auditability, pause and raise a
  separate design decision before adding storage.
- Keep DNG lifecycle safe: live DNG requests must be cancelled through the
  DNG-centric flow before a linked charge is voided or consumed.

## Non-Goals

- Do not introduce a parallel settlement ledger.
- Do not create unpaid forfeit/penalty debt for `FORFEIT` or `PARTIAL`.
- Do not rewrite the Finance ledger, payment allocation, or invoice snapshot
  model.
- Do not change DNG provider payloads, webhook handling, or checksum behavior.
- Do not add DB constraints until a dry-run proves historical data is clean or a
  human-approved remediation plan exists.
- Do not change student or lecturer portal APIs in this story.
