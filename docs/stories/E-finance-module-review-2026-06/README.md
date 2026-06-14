# Finance Module Review 2026-06 Story Set

Source review: `docs/features/finance/finance-module-review-2026-06-13.md`

This epic decomposes the Finance module review into small high-risk Harness
stories. The order intentionally keeps measurement and immediate safety first,
then fixes money truth, data guards, DNG reliability, operations cleanup, and
finally UI/BOD surfaces.

## Story Order

| Order | Story id | Story packet | Main scope | Depends on |
| --- | --- | --- | --- | --- |
| 1 | `FIN-REV-001-audit-and-safety-gates` | `S-001-audit-and-safety-gates/` | Run/record invariants, close immediate dangerous UI/action gaps | None |
| 2 | `FIN-REV-002-ledger-source-of-truth` | `S-002-ledger-source-of-truth/` | One balance source, immutable invoice snapshot, allocation locks/idempotency, transparent cache/rebuild, DNG-vs-ledger reconciliation | 001 |
| 3 | `FIN-REV-003-data-guards-and-constraints` | `S-003-data-guards-and-constraints/` | Clean duplicates, then add DB uniqueness/check/FK/status/model guards and any operation-token schema needed by 002 | 001, 002 for final constraints |
| 4 | `FIN-REV-004-charge-discount-installment-correctness` | `S-004-charge-discount-installment-correctness/` | Scholarship/EGC/installment/pivot/void amount correctness | 001, preferably 002 |
| 5 | `FIN-REV-005-dng-security-and-idempotency` | `S-005-dng-security-and-idempotency/` | Webhook checksum, dedup, locks, cancelled status ordering | 001, 003 for payload cleanup |
| 6 | `FIN-REV-006-operations-stubs-and-performance` | `S-006-operations-stubs-and-performance/` | Replace stub ops, due-reminder naming/counts, query pagination/perf | 001 |
| 7 | `FIN-REV-007-finance-ui-foundation-and-navigation` | `S-007-finance-ui-foundation-and-navigation/` | Shared finance UI foundation, navigation links, unsafe form rewrites | 001, 002 for money display confidence |
| 8 | `FIN-REV-008-bod-finance-oversight` | `S-008-bod-finance-oversight/` | Read-only BOD finance overview with charts after cache/rebuild and DNG-vs-invoice rail decisions | 002, 003, 007 |

## Shared Rules

- Portal impact is `none` unless a future slice explicitly changes
  `/api/v1/student/*` or `/api/v1/lecturer/*`.
- Product code implementation must use Docker wrappers in `./scripts/dev.sh`.
- Do not add DB constraints until matching dirty-data checks are zero or a
  human-approved remediation plan exists.
- Do not hide finance data only in Vue when backend queries/actions can still
  operate on the unsafe rows.
- Every implementation slice must run targeted tests and record `finance:audit-invariants`
  evidence when money or DNG state can change.
- `FIN-11` and `DB-09` are P1 Finance core work, not DNG-only work: allocation
  concurrency must be owned by the canonical settlement/allocation story.
- `DB-13`, `DB-14`, and `DB-15` are blockers for leadership aggregates: do not
  build BOD metrics from stale cache columns or by double-counting invoice and
  DNG rails.
- Lifecycle due exception residuals are split: duplicated acknowledge/resolve
  logic belongs to `E-finance-lifecycle-exceptions`; DNG cancel audit ordering
  is cross-cutting with the DNG hardening story.
- Scheduling note: `FIN-REV-002` should first try lock-and-check idempotency
  without schema. If the implementation proves an operation token is required,
  pause that slice and let `FIN-REV-003` add the minimal storage before
  continuing.
