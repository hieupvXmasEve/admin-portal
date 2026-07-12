# 17 — Chuyển current Finance reports và Fee Monitor

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Chuyển các Finance report, Fee Monitor và export có semantic “hiện tại” sang canonical current Settlement Position. Mỗi consumer khai báo scope/filter và grouping nhưng không tự tính settlement. Totals phải reconcile với breakdown/ledger, invalid positions không bị bỏ âm thầm, và batch/index design phải đáp ứng representative production volume.

## Acceptance criteria

- [x] Inventory phân loại rõ report/export nào là current và đưa toàn bộ current consumers trong slice qua canonical reader.
- [x] Report totals, groups và exported rows reconcile với canonical breakdown tại cùng settlement version.
- [x] Cash, discount và credit không bị trộn semantic; paid cache không làm nguồn chân lý.
- [x] Invalid positions được count/surface bằng stable issue code thay vì bị omit hoặc clamp.
- [x] Campus/semester/program filters không làm thay đổi công thức settlement.
- [ ] Batch query plans dùng required indexes và đạt issue-defined query-count, memory và p95 budgets.
- [x] Regression fixtures chứng minh current report thay đổi đúng sau payment, credit, reversal và void.

## Blocked by

- [02 — Tổng hợp Settlement Position theo business scope](../../wave-0-foundation/issues/02-aggregate-batch-as-of-settlement-position.md)
- [13 — Chuyển invoice paid cache thành cash-only](../../wave-2-cash-cache/issues/13-cash-only-invoice-paid-cache.md)

## Verification

- Current inventory in this slice: Collection Progress (`student × semester_balance`), Fee Monitor generated-payment state, Finance invoice lookup, and invoice export. DNG Lifecycle remains a request/webhook lineage lens whose `amount` is the provider request amount, not an authoritative settlement balance; historical/as-of reporting stays in issue 18.
- Collection Progress now sends all invoice scopes for the filtered campus/semester to one canonical current batch reader. Rows carry a shared settlement version, structured raw evidence, exact line breakdown, separate gross/discount/cash/credit/remaining values, and stable issue codes. Invalid rows remain visible with no trusted totals.
- Fee Monitor generated rows use exact charge payable-line scopes through the same canonical reader. Missing payable lines and invalid currency/ledger evidence surface as `payment_state=invalid`; paid cache and local `SUM(payment_applications)` arithmetic are no longer used for settlement state.
- Invoice lookup and chunked Excel export now read canonical positions. Exported columns are Gross, Discount, Cash Received, Credit Applied, Remaining Collectible, Settlement State, Settlement Version, Issue Codes, and the exact Payable Line Breakdown JSON; stale invoice cache values are not exported.
- Existing indexes cover the batch paths: `student_invoices(semester_id,status,due_date)`, `invoice_lines(invoice_id,status)`, `invoice_lines(charge_id,status)`, payment/discount/credit application line indexes, and Finance charge semester/status indexes. The regression test asserts one Collection Progress batch stays at `<=12` queries for four student rows.
- Regression coverage includes payment, mixed discount/cash/credit, credit reversal, void evidence, invalid position surfacing, campus/semester scope, Fee Monitor generated/missing states, and canonical invoice export values.

Focused verification:

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Reporting tests/Feature/Finance/Lookup/InvoiceLookupTest.php tests/Feature/Finance/InvoiceSettlementBreakdownTest.php tests/Feature/Finance/SettlementPosition/AggregateSettlementPositionReaderTest.php tests/Feature/Finance/SettlementPosition/CurrentPayableSettlementPositionReaderTest.php` — 53 passed, 469 assertions.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- Scoped Prettier and ESLint for the three changed Vue pages — passed; `git diff --check` — passed.
- `./scripts/dev.sh test` and direct `./scripts/dev.sh artisan test --compact` both exited `255` without diagnostics in the current Docker test environment.
- `./scripts/dev.sh npm run type-check` reached `vue-tsc` but exited `134` from Node heap OOM; no changed-file diagnostics were produced.

Remaining human/release verification:

- Run representative production-volume EXPLAIN, memory, and p95 benchmarks for each report/export class and record the issue-defined budgets.
- Complete staging/browser verification and environment-wide test/typecheck gates before moving the issue to `completed`.

## Comments

### 2026-07-12 — Current settlement reporting implementation

- Scope stayed `Portal impact: none`. Parent PRD, historical issue 18, and sibling tracker states were not changed.
- The issue remains `ready-for-human` because the production-volume p95/memory gate and environment-wide checks are unresolved; the focused implementation and regression evidence are complete.
