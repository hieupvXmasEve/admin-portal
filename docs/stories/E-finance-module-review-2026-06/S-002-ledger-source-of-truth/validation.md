# Validation

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

## Proof Strategy

Prove that every touched balance surface reads the same canonical ledger result
and that void/reversal paths stay netted by signed rows.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Canonical snapshot math for discounts, payments, reversals, legacy credits |
| Integration | Settlement worklist, dashboard stats, auto allocation, invoice refresh |
| Integration | Concurrent webhook + batch allocation cannot over-allocate one payment |
| Integration | Snapshot rebuild command/runbook clears stale cache, including `paid_at` |
| Integration | DNG-vs-ledger reconciliation flags mismatched rails without double-counting |
| E2E | Smoke one Finance balance page if frontend props change |
| Platform | Docker wrapper commands |
| Performance | Ensure replacement does not add uncontrolled N+1 loops |
| Logs/Audit | No loss of payment/discount reversal audit rows |

## Fixtures

- Student with active charge and no payment.
- Student with scholarship/voucher allocation.
- Student with payment reversal or voided invoice line.
- Student with legacy negative charge line if present.
- Two workers attempting to allocate the same unapplied payment.
- DNG request bridged to a payment for a student that also has an invoice due
  row, to prove KPI rail handling.

## Commands

```text
./scripts/dev.sh test --filter=Settlement
./scripts/dev.sh test --filter=Finance
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run type-check
git diff --check
```

## Acceptance Evidence

Implemented 2026-06-14. Branch `dev`.

### Scope delivered

- **FIN-01** — consolidated the 3 admin `deriveInvoiceSnapshot` duplicates
  (`ListSettlementWorklistQuery`, `PreviewAutoAllocateQuery`,
  `AutoAllocatePaymentsAction`) to thin adapters over
  `SettlementService::deriveInvoiceSnapshot`; removed the stale-cache `max()`
  mixing. Rewrote `PaymentService::autoAllocatePayment` off the charge-centric
  `FinanceCharge::balance` path onto canonical line outstanding.
- **DB-01** — `InvoiceGenerationService` now uses `firstOrCreate`; `amount_snapshot`
  is frozen at creation, reactivation restores status only.
- **FIN-11 / DB-09** — payment-row `lockForUpdate` + recompute-under-lock in
  `AllocatePaymentAction`, `PaymentService::allocatePayment`, and
  `AutoAllocatePaymentsAction`. Operation idempotency via lock + check-before-write,
  no schema (per epic scheduling note — no stop condition triggered).
- **FIN-02 / FIN-03** — defensive backstop: canonical excludes allocations whose
  parent discount is `reversed`; voided lines already excluded via
  `isBillableActiveLine`. Signed-ledger netting pinned by tests.
- **NT4 / DB-14 / DB-15** — renamed `student_invoices.{subtotal,discount_total,
  total_amount,paid_amount,paid_at}` → `cached_*` (migration
  `2026_06_14_000001`). Model exposes balance through ledger-derived accessors;
  legacy attribute names kept working via aliases (props/contracts unchanged).
  `cached_paid_at` keeps first-paid time and clears on reversal. Added
  `finance:rebuild-invoice-snapshots`.
- **DB-13** — added read-only invariant `INV-12` (bridged DNG request amount must
  equal its canonical payment). KPI aggregation unchanged (the double-count
  decision is a stop condition / later story).

### Scope decisions (deviations recorded)

- **`status` column NOT renamed.** `student_invoices.status` is an overloaded
  lifecycle column (written by `markAsPaid`/`closeInvoice`/charge-gen, read by
  `scopeReusableForChargeGeneration`), not pure cache. The live payment-derived
  status is already exposed via `real_time_status`. Renaming it would break
  lifecycle semantics for no balance-correctness gain.
- **Academic `GetStudentFeeSummaryQuery::deriveInvoiceSnapshot` left untouched**
  (student-facing). Consolidating it could shift student API numbers where cache
  was stale — out of scope per Portal Impact: None. Tracked as a portal follow-up.

### Test output

```text
./scripts/dev.sh test (S-002 new + core money tests)
  PASS  LedgerSourceOfTruthTest            (FIN-01: worklist/preview/auto-allocate == canonical)
  PASS  LedgerDefensiveBackstopTest        (FIN-02/03 signed-ledger + reversed-discount backstop)
  PASS  InvoiceSnapshotImmutabilityTest    (DB-01 freeze + reactivation)
  PASS  AllocationConcurrencyGuardTest     (FIN-11 no over-allocation across two paths)
  PASS  InvoiceCacheRebuildTest            (NT4 rename + rebuild + DB-15 paid_at clear)
  PASS  DngLedgerReconciliationInvariantTest (DB-13 INV-12)
  PASS  VoidReleaseAllocationTest          (existing — no regression)
  PASS  StudentInvoiceDerivedSnapshotTest  (existing — no regression)
  PASS  CreditMemoPaymentTest              (existing — no regression)
  PASS  SettlementWorklistTest             (existing — no regression)
  Tests: 29 passed (127 assertions)
```

Full `tests/Feature/Finance` + `tests/Unit/Finance`: **26 failed, 285 passed**.
The 26 failures are PRE-EXISTING on `dev` HEAD (verified before any change):
web-route/CSRF (`AutoAllocatePaymentsTest`, `PaymentPagesTest`,
`StudentFinanceDngAccessTest`), `DngReconciliationServiceTest` constructor
`ArgumentCountError`, and `Egc/GenerateEgcChargesTest` `ErrorException`. None are
in the ledger/settlement math this story changes; baseline was 271 passed → now
285 (the +14 are this story's new tests).

### Audit output (`finance:audit-invariants --sample`, dev DB ~= prod)

```text
INV-1  ✅ 0   Payment over-allocated
INV-2  ✅ 0   student_invoices.cached_paid_amount cache drift   <- renamed column, still 0
INV-3  ✅ 0   Active positive charge -> exactly 1 active line
INV-4  ✅ 0   Payment on void line
INV-5  ✅ 0   Reversed discount still allocated
INV-6  ❌ 28  Duplicate invoice (student_id, semester_id)        <- PRE-EXISTING data debt (S-003)
INV-7  ✅ 0   Duplicate scholarship award
INV-8  ✅ 0   Negative balance
INV-9  ✅ 0   Duplicate invoice_number
INV-10 ✅ 0   Non-positive payment
INV-11 ❌ 3   Duplicate webhook payload_hash                     <- PRE-EXISTING data debt (S-003/S-005)
INV-12 ✅ 0   Bridged DNG request amount diverges from payment   <- NEW (DB-13)
```

Money/ledger invariants unchanged at 0 after the cached_* migration + recalc
rewrite (INV-2 = 0 on all real invoices proves the cache rebuild is consistent).
INV-6/INV-11 are the unchanged dirty-data backlog owned by S-003.

### Other gates

- `pint` — clean on all touched files.
- `git diff --check` — clean.
- `npm run type-check` — not run; no frontend files changed and all admin props
  (`total_amount`/`paid_amount`/`subtotal`/`discount_total`/`paid_at`) are
  preserved via model accessors, so Inertia prop shapes are unchanged.

### Review round 1 fixes (2026-06-14)

- **[P1] Line overpay hole closed.** `PaymentService::allocatePayment` now locks
  the target `InvoiceLine` (`lockForUpdate`), verifies the line belongs to the
  payment's student, and caps each apply by `min(requested, payment unapplied,
  line outstanding)`. A too-large request (DNG pivot rounding / post-push
  discount) becomes unapplied credit instead of overpaying the line. Tests:
  `AllocationConcurrencyGuardTest` (line-outstanding cap, cross-student skip).
- **[P1] Line outstanding now matches the snapshot under reversed discounts.**
  `getLineDiscountAmount`/`getChargeDiscountAmount` exclude allocations whose
  parent discount is `reversed` (query-builder twin of the snapshot backstop), so
  allocation no longer stops early while the invoice still shows the balance.
  Test: `LedgerDefensiveBackstopTest` (line outstanding == snapshot remaining).
- **[P2] `retake_unpaid_count` no longer treats the amount cache as truth.**
  Switched `whereColumn('cached_paid_amount','<','cached_total_amount')` to
  `where('status','!=','paid')` (recalc-maintained lifecycle status, consistent
  with the worklist). A fully ledger-derived dashboard count remains FIN-28.
- **[P2] Harness artifacts unstaged.** `.harness-backup/...` and
  `harness.db.pre-update-*.bak` removed from the index so they cannot enter the
  S-002 commit.

Re-verified: `AllocationConcurrencyGuardTest` (4) + `LedgerDefensiveBackstopTest`
(4) pass; DNG suite + Void/Release + core money tests = 106 passed, only the
pre-existing `DngReconciliationServiceTest` ArgumentCountError still failing.

### Review round 2 fixes (2026-06-14)

- **[P1] Rebuild/recalc no longer resurrects cancelled invoices.**
  `recalculateInvoiceSnapshot` preserves terminal lifecycle statuses
  (`cancelled`/`void`) instead of overwriting them with the derived payment
  status. Fixed at the writer, so it holds for every recalc call site, not just
  the rebuild command. Test: `InvoiceCacheRebuildTest` (cancelled survives both
  the command and a direct recalc).
- **[P1] Batch allocation now locks the line too.**
  `AutoAllocatePaymentsAction` re-fetches each line with `lockForUpdate` and
  re-reads outstanding under lock before writing — closing the different-payment
  same-line race the payment lock alone did not cover. Behavioural guard:
  `AllocationConcurrencyGuardTest` (batch + bridge on two payments never overpay
  one line). True OS-thread interleaving remains out of the harness; the lock is
  correctness-by-construction.
- **[P2] `retake_unpaid_count` now matches the worklist exactly** —
  `whereNotIn('status', ['paid','cancelled'])` (was `!= 'paid'`, which still
  counted cancelled invoices as unpaid).

Re-verified: round-2 tests pass (`InvoiceCacheRebuildTest` 4, `AllocationConcurrencyGuardTest`
5); DNG suite + Void/Release + dashboard + core money tests pass, only the
pre-existing `DngReconciliationServiceTest` ArgumentCountError remains.

### Follow-ups / unresolved

- Portal slice: route `GetStudentFeeSummaryQuery` + student `FinanceController`
  through the canonical calculation.
- KPI double-count decision (DNG due vs invoice due in one KPI) remains a product
  decision; INV-12 only proves the rails reconcile.
- INV-6 (28 duplicate invoice groups) must be reconciled before adding
  `UNIQUE(student_id, semester_id)` — S-003.
