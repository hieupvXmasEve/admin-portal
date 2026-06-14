# Finance Invariant Audit — Baseline Evidence

- Date run: 2026-06-14
- Command: `./scripts/dev.sh artisan finance:audit-invariants --sample`
- Dataset: local snapshot (per owner note in review §D1: local currently mirrors prod;
  prod has not generated new charges). Snapshot source/timestamp/restore command is
  **not yet recorded** — flag if this is promoted to formal prod evidence.
- Mode: read-only (SELECT only; command never mutates data).

## Context row counts

| Table | Rows |
| --- | --- |
| finance_charges | 996 |
| payments | 524 |
| payment_applications | 741 |
| student_invoices | 552 |
| invoice_lines | 985 |
| invoice_discounts | 395 |
| discount_allocations | 406 |
| student_scholarship_awards | 217 |

## Invariant results

| Invariant | Severity | Offending | Description |
| --- | --- | --- | --- |
| INV-1 | CRITICAL | ✅ 0 | Payment over-allocated (SUM applications > payment.amount) |
| INV-2 | HIGH | ✅ 0 | student_invoices.paid_amount cache drifts from live (active lines) |
| INV-3 | CRITICAL | ✅ 0 | Active positive charge NOT linked to exactly 1 active invoice_line |
| INV-4 | CRITICAL | ✅ 0 | Payment still applied to a VOID invoice_line (not reversed) |
| INV-5 | CRITICAL | ✅ 0 | Discount status=reversed but allocations still net > 0 |
| INV-6 | CRITICAL | ❌ 28 | Duplicate invoice for same (student_id, semester_id) |
| INV-7 | HIGH | ✅ 0 | Duplicate scholarship award for same student |
| INV-8 | CRITICAL | ✅ 0 | Negative balance (discount+paid > charge) on active positive charge |
| INV-9 | HIGH | ✅ 0 | Duplicate invoice_number |
| INV-10 | HIGH | ✅ 0 | Payment with non-positive amount |
| INV-11 | HIGH | ❌ 3 | duplicate webhook payload_hash (idempotency loss) |

Total offending rows/groups: **31**.

## Failure samples (`--sample`)

- **INV-6** (duplicate invoice per student+semester) sample ids: `1182, 1343, 1439, 1184, 1185`
- **INV-11** (duplicate webhook `payload_hash`) sample ids: `199, 216, 217`

## Interpretation

- Arithmetic / ledger invariants **PASS** (over-allocation, cache drift, void/reversed
  netting, negative balance) → per-invoice money math is internally consistent.
- Uniqueness / idempotency invariants **FAIL**: INV-6 (28 duplicate invoice groups) and
  INV-11 (3 duplicate webhook payload hashes). These are the exact dirty-data blockers
  for the future `UNIQUE(student_id, semester_id)` and `UNIQUE(payload_hash)` constraints.
- This baseline matches the review's §D1 local audit (2026-06-13): INV-6 = 28, INV-11 = 3.

## Blockers handed to later stories

- **S-002 / S-003**: do NOT add `UNIQUE(student_id, semester_id)` or restore
  `UNIQUE(payload_hash)` until these 28 + 3 rows are reconciled (per-group reconciliation,
  not bulk delete — review §D1).
- The 28 INV-6 groups carry active, paid lines → possible double-billing; correctness of
  those groups is undetermined and must be reconciled individually.
