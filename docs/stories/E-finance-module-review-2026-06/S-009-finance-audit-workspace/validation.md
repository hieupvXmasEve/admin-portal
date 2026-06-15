# Validation

## Proof Strategy

Prove that the workspace is a read-mostly, permission-safe graph viewer that
matches existing Finance truth. The key proof is not just rendering: search
resolution, campus scoping, ledger event ordering, derived totals, cache drift
warnings, and source-page link parity must all be covered.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Resolver precedence for prefixed debug ids, invoice number, DNG item/payment/transaction id, exact student code, payment external ref, and fuzzy student search; `external_ref` never wins over a valid MSSV/invoice/DNG identifier; ambiguous bare integers never guess. |
| Unit | Timeline builder preserves signed `payment_applications` and `discount_allocations` events, including reversal/release rows. |
| Unit | Warning builder flags cached invoice snapshot drift by comparing `student_invoices.cached_*` against `SettlementService` derived values. |
| Unit | Shared invariant registry exposes INV-1..INV-15 once and is reused by both `finance:audit-invariants` and subject-scoped workspace warnings. |
| Unit | Command parity: `finance:audit-invariants` produces identical counts and still renders `ERROR` for a failing invariant after the registry refactor (errors surfaced, never swallowed to a clean ✅). |
| Integration | Authorized user can resolve visible invoice/payment/DNG/student targets and receive graph nodes, edges, timeline, warnings, and deep links. |
| Integration | Campus-scoped user receives the same generic empty/denied behavior for hidden-campus finance records without data leakage. |
| Integration | Graph covers one payment fanning out to multiple invoice lines/invoices and one DNG request linked to multiple charge/installment rows through `dng_payment_request_charges`. |
| Integration | Route access requires `view_finance_audit_workspace`; export is denied without `export_finance_audit_workspace`; with the permission the disabled export affordance is shown — the egress endpoint + audit entry are deferred to a follow-up slice. |
| E2E | Search, ambiguous-result selection, timeline display, warning display, source-page deep links, and authenticated share-link reopening. |
| Platform | Finance Office menu shows Audit Workspace as the primary entry while legacy source pages remain reachable. |
| Performance | Student-context search is bounded by explicit/default scope and avoids per-row `SettlementService` N+1 loops. |
| Logs/Audit | When the export endpoint ships, its audit entries must include actor, target, scope, timestamp, and outcome (deferred this slice); runtime logs never include raw DNG payloads or sensitive PII. |

## Fixtures

Required deterministic fixtures:

- Student with multiple invoices in one or more semesters.
- Payment with applications across multiple invoice lines and at least one
  reversal.
- Invoice with an active discount allocation and a release/reallocation row.
- DNG request with direct `finance_charge_id`.
- DNG request with multiple rows in `dng_payment_request_charges`, including an
  installment-linked row.
- Invoice with intentionally stale `cached_*` values for cache-drift warning
  tests.
- Two-campus data set where the same-looking identifier exists outside the
  user's current campus.
- Users with view-only, export, and no-access permission profiles.

## Commands

Implementation should add concrete tests and then run:

```text
./scripts/dev.sh test --filter=FinanceAuditWorkspace
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh artisan pint
```

Browser smoke is required for the final implementation because this is a primary
Finance UI surface.

## Acceptance Evidence

Record exact command output, browser smoke notes, and any remaining baseline
failures in Harness after implementation. Do not mark the story implemented
until access-control, graph/timeline correctness, and export audit behavior are
proved or explicitly deferred.
