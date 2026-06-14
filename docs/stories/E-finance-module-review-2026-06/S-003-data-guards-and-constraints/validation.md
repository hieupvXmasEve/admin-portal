# Validation

## Proof Strategy

Prove that dirty-data blockers are zero before adding constraints, then prove the
new guards reject recurrence without breaking normal finance flows.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Retry-on-collision and enum/status/charge-type helpers |
| Integration | Migration guards, duplicate cleanup dry-run, FK behavior |
| Integration | Optional operation-token schema enforces only the intended mutation boundary |
| Integration | Charset/collation check confirms Vietnamese text safety |
| Integration | Money-column migrations preserve historical values through dry-run/backfill proof |
| E2E | Not required unless an admin cleanup UI is added |
| Platform | Migration on restored snapshot or disposable DB |
| Performance | `EXPLAIN` for new hot composite indexes if query paths change |
| Logs/Audit | Cleanup output lists affected ids and post-check counts |

## Fixtures

- Duplicate invoice group.
- Duplicate webhook payload hash group.
- Voucher application with nullable references.
- Existing valid status values for each guarded table.
- Finance rows with Vietnamese notes/labels.
- Historical invoice/payment amount rows before migration.

## Commands

```text
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan migrate --pretend
./scripts/dev.sh test --filter=Finance
./scripts/dev.sh artisan pint
git diff --check
```

## Acceptance Evidence

Implemented 2026-06-14. Branch `dev`. DB: MariaDB 11.4 (CHECK enforced),
default charset `utf8mb4` / `utf8mb4_unicode_ci` (DB-25 satisfied at DB level).

### What shipped (additive, non-destructive)

Migrations (`database/migrations/2026_06_14_000002..000006`):

- `..000002_add_voucher_application_foreign_keys` — FK `voucher_applications.invoice_id`
  and `.finance_charge_id` → `nullOnDelete` (DB-08 / FIN-14). Pre-checked 0 orphans.
- `..000003_add_finance_money_sign_checks` — CHECK `payments.amount > 0`,
  `finance_charge_installments.amount > 0`, and a charge_type-aware
  `finance_charges` sign guard: **debit types `>= 0`, credit types `<= 0`,
  adjustment any** (DB-06). This forbids the dangerous sign-FLIP (a debit stored
  negative or a credit stored positive) in both directions while allowing a
  zero-amount charge (zero has no sign error and models a real zero-debt /
  waived invoice — e.g. the reminder-skip tests). The legacy negative-amount
  *debit* line (a discount stored as `tuition_term = -3M`) can no longer be
  produced by current code, so this CHECK correctly blocks recreating it; the one
  reader test that still verifies the netting backstop on that legacy shape seeds
  it with `check_constraint_checks` disabled. (Idempotent add/drop via
  `information_schema.CHECK_CONSTRAINTS`.)
- `..000004_add_finance_status_check_constraints` — CHECK on
  `finance_charge_installments.status`, `dng_payment_requests.status`,
  `dng_webhook_events.processing_status` mirroring the PHP value sets (DB-05 / B5.2).
  Lifecycle review status intentionally excluded (owned by
  E-finance-lifecycle-exceptions; `ignored` dead-status is an open product decision).
- `..000005_add_invoice_discounts_reference_unique` — unique
  `(invoice_id, discount_type, reference_key, discount_source)` (DB-10), where
  `reference_key` is a persisted generated column `COALESCE(reference_id, 0)`.
  Using the sentinel (rather than the nullable `reference_id` directly) closes the
  MariaDB "NULLs are distinct" hole: two NULL-reference discounts with the same
  (invoice, type, source) are now blocked. It still matches the
  `createOrRefreshInvoiceDiscount` firstOrNew contract (which keys on
  reference_id) without changing the column's nullability or the code path.
  Pre-checked 0 duplicate groups.
- `..000006_add_finance_performance_indexes` — `student_invoices(semester_id,status,due_date)`,
  `(status,due_date)`; `dng_payment_requests(status,due_date)`, `(student_id,status)`,
  `(dng_transaction_id)`; `invoice_lines(charge_id,status)` (DB-16/17). Verified
  against live schema as non-redundant (FK columns already auto-indexed).

Code:

- FIN-13 retry-on-collision: `CreateFinanceChargeAction::createInvoiceForSemester`
  now retries up to 5x on a unique `invoice_number` violation; generator widened to
  6 random digits so retries diverge. (`invoice_number` was already unique.)
- FIN-25 model/DB parity: `FinanceCharge::CHARGE_TYPES` now includes `course_fee`
  and `bhyt` (added `CREDIT_CHARGE_TYPES`); `StudentInvoice::NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES`
  trimmed to DB-enum values (`paid`, `cancelled`); `DngWebhookEvent::STATUS_PENDING`
  declared for parity with the column default.
- Dry-run remediation tool: `finance:export-duplicate-finance-data` (read-only).
  Console shows a compact triage table; a JSON artifact (always written, default
  `storage/app/finance/s003-duplicate-export.json`) carries full row-level detail —
  invoice_line, charge, payment_application, discount and DNG ids with
  amounts/statuses — for safe canonical selection. DNG linkage is resolved through
  BOTH the direct `dng_payment_requests.finance_charge_id` and the
  `dng_payment_request_charges` pivot (aggregate/multi-charge requests, whose
  direct FK may be NULL); each entry records `link_source` (direct|pivot|direct+pivot)
  and `pivot_amount`. On the current dataset this surfaces 33 pivot-linked DNG
  requests across 31 duplicate invoices that the direct-only join missed. `--limit`
  only caps the detailed rows collected; reported totals always reflect the true
  group counts (`total` vs `shown`). No row is ever mutated.

### Invariants — before == after (additive guards changed nothing)

```
finance:audit-invariants (dev DB, before and after migrations):
  INV-1..5, 7..10, 12  ✅ 0
  INV-6   ❌ 28   Duplicate invoice (student_id, semester_id)   <- still deferred (DB-03)
  INV-11  ❌ 3    Duplicate webhook payload_hash                <- still deferred (DB-04)
```

### Migration safety proof

`migrate` → `migrate:rollback` → `migrate` all clean on prod-shaped dev data
(every up()/down() reversible; all CHECK/FK/unique validated against existing rows
without violation).

### Tests

New (all green): `tests/Feature/Finance/DataGuards/{FinanceMoneySignCheck,
FinanceStatusCheck,VoucherApplicationForeignKey,InvoiceDiscountReferenceUnique,
FinanceEnumDbParity}Test` (incl. NULL-reference discount + both-direction sign +
zero-allowed cases), `InvoiceNumberCollisionRetryTest`,
`ExportDuplicateFinanceDataCommandTest` → **40 passed**.

Full `tests/Feature/Finance` + `tests/Unit/Finance`: **26 failed, 334 passed**.
The 26 are the SAME pre-existing failures recorded in S-002 (web/CSRF in
`AutoAllocatePaymentsTest`/`PaymentPagesTest`/`StudentFinanceDngAccessTest`,
`DngReconciliationServiceTest` `ArgumentCountError`, `Egc/GenerateEgcChargesTest`
`ErrorException`). **Zero new regressions.**

Sign-CHECK iteration (review feedback): the confirmed spec was charge_type-aware
both directions. A strict `>0/<0` form rejected (a) the legacy negative-debit
reader fixture and (b) two reminder tests that model a zero-debt invoice with a
zero-amount `tuition_term` charge. Resolved per approval as **debit `>=0` / credit
`<=0`** — both sign-flip directions are still blocked; zero (a real waived/zero-debt
state) is allowed. Legacy negative-debit shape stays forbidden on write and is
seeded with the CHECK disabled only in its one reader test.

`pint` clean on all touched files. `git diff --check` clean.

### Deferred (require human decision / out of this slice — see execplan Stop Conditions)

- **DB-03** unique `student_invoices(student_id, semester_id)` and **DB-04** restore
  unique `dng_webhook_events.payload_hash`: blocked by INV-6=28 / INV-11=3. The
  export shows most groups are NOT duplicate billings of the same charge but a real
  second charge (extra fee / BHYT) in its own invoice, both paid — so remediation is
  merge/consolidate preserving money, not delete. 31 of those invoices also carry
  pivot-linked DNG requests (33 total), so consolidation must re-point DNG linkage,
  not just lines/payments. Canonical selection is a human/finance decision, not an
  automatable heuristic. Run `finance:export-duplicate-finance-data`, review, then a
  follow-up adds the two unique migrations once counts are 0.
- **DB-24** soft-deletes on `payments` / `student_invoices`: deferred (per decision).
- **DB-09** operation-token storage: not needed — S-002 achieved idempotency with
  lock + check-before-write (no schema).
- Lifecycle review status CHECK + dead `ignored` status: owned by
  E-finance-lifecycle-exceptions.

## Commands (as run)

```text
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan migrate --force            # + migrate:rollback proof
./scripts/dev.sh artisan finance:export-duplicate-finance-data --limit=3
./scripts/dev.sh test tests/Feature/Finance tests/Unit/Finance
vendor/bin/pint <touched files>
git diff --check
```
