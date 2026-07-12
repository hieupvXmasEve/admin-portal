# 14 — Hiển thị settlement breakdown trên invoice detail

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Chuyển staff invoice detail sang canonical Settlement Position và hiển thị rõ phí gốc, giảm giá, đã thu bằng cash, credit đã áp dụng và còn phải thu, kèm exact Payable Line breakdown. Invalid position hiển thị `Cần kiểm tra` và evidence phù hợp quyền; page không tự tính `total - paid` hoặc fallback cache/formula cũ.

## Acceptance criteria

- [x] Invoice summary và từng line dùng cùng canonical position/breakdown.
- [x] UI tách Gross/Discount/Đã thu/Credit đã áp dụng/Còn phải thu với approved staff wording.
- [x] Aggregate reconcile chính xác với visible line breakdown, gồm rounding remainder.
- [x] Invalid scope không hiển thị untrusted remaining hoặc money action; staff thấy `Cần kiểm tra` và drilldown evidence.
- [x] Cash-only cache chỉ là projection/compatibility data, không thay canonical values trên detail.
- [x] No local arithmetic trong controller, query hoặc Vue component.
- [x] Feature/render tests bao phủ mixed cash+credit, reversal, Còn dư và invalid position.

## Comments

Implemented the staff invoice detail cutover to `SettlementPositionReader::forInvoice()`.

- Controller serializes canonical Money values, raw issue evidence, exact payable-line breakdown, and explicit reconciliation metadata; legacy invoice snapshot arithmetic is no longer used for detail totals.
- Invoice lines render the same canonical components as the aggregate. Invalid positions expose `Cần kiểm tra` with issue evidence and no trusted remaining amount.
- Cash surplus remains a separate payment projection and is sourced through the disposition-aware student payment history query; it does not replace canonical invoice values.
- Added feature/render coverage for mixed cash + credit, credit reversal, cash surplus after disposition, stale cache values, exact reconciliation/rounding remainder, and invalid over-application.

Verification evidence:

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/InvoiceSettlementBreakdownTest.php` — 3 passed, 67 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/DetailRouteContractTest.php tests/Feature/Finance/SettlementPosition/AggregateSettlementPositionReaderTest.php tests/Feature/Finance/SettlementPosition/CurrentPayableSettlementPositionReaderTest.php` — 17 passed, 163 assertions.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- Scoped Prettier and ESLint for `resources/js/pages/Finance/Invoices/Show.vue` — passed.
- Full Unit suite — 116 passed, 1 existing Notification registry failure (`EmailContentRegistryTest`). Full Feature suite exits 255 without output in the current Docker environment; the app/db containers are healthy but queue/scheduler/vite are unhealthy. Full type-check reports 344 existing errors across 143 files; the changed invoice page is not among them.

Remaining human verification: review the staff UI in staging, including invalid evidence visibility and cash surplus presentation, then move this issue to the completed tracker state after the environment-wide gates are resolved or explicitly waived.

## Blocked by

- [02 — Tổng hợp Settlement Position theo business scope](../../wave-0-foundation/issues/02-aggregate-batch-as-of-settlement-position.md)
- [13 — Chuyển invoice paid cache thành cash-only](../../wave-2-cash-cache/issues/13-cash-only-invoice-paid-cache.md)
