# 18 — Chuyển historical và as-of reports

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Chuyển historical collection, aging, period-close reports và exports sang as-of Settlement Position hoặc canonical signed-ledger timeline. Mỗi output khai báo as-of timestamp/timezone và business-effective timestamp rules. Payment, reversal, refund hoặc void xảy ra sau kỳ báo cáo không được viết lại số lịch sử; legacy evidence thiếu chronology phải được surface theo documented rule.

## Acceptance criteria

- [x] Inventory phân loại historical/as-of consumers và loại bỏ current-position recomputation cho prior periods.
- [x] Output ghi as-of timestamp/timezone và dùng business-effective timestamps theo contract.
- [x] Later payment/reversal/refund/void không thay đổi prior-period snapshot ngoài explicit restatement workflow.
- [x] Legacy evidence thiếu reliable effective time trả integrity issue hoặc documented exclusion; không dùng `created_at` để đoán.
- [x] Historical totals reconcile với signed-ledger timeline và line-level evidence.
- [x] Current và as-of readers cho kết quả khác đúng trong fixtures có later events.
- [ ] Long-range report/export đạt issue-defined batch, memory, query plan và duration budgets.

## Blocked by

- [02 — Tổng hợp Settlement Position theo business scope](../../wave-0-foundation/issues/02-aggregate-batch-as-of-settlement-position.md)
- [13 — Chuyển invoice paid cache thành cash-only](../../wave-2-cash-cache/issues/13-cash-only-invoice-paid-cache.md)

## Verification

- Historical / as-of Collection Progress and aging now use `SettlementPositionReader` with the requested timestamp, preserve later payment and later void history, and expose line-level breakdowns plus consumer inventory metadata.
- Invoice export accepts the same as-of context and declares position mode, timestamp, timezone, and business-effective timestamp rules in the workbook.
- Historical unapplied cash is explicitly excluded until a canonical payment-surplus timeline reader exists; no current-position recomputation or `created_at` chronology inference is used.
- Focused verification: `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Reporting` plus settlement-position regression slice — 51 passed, 413 assertions; scoped Prettier/ESLint, Pint, and `git diff --check` passed.
- `./scripts/dev.sh test` exited `255` without diagnostics in the current Docker environment; `./scripts/dev.sh npm run type-check` reached `vue-tsc` but exited `134` from Node heap OOM.

## Remaining human/release verification

- Run representative production-volume EXPLAIN, query-count, memory, lock-wait, and p95/duration benchmarks for the historical report and export classes, and record the issue-defined budgets.
- Define/implement a canonical payment-surplus timeline reader before including historical unapplied cash in authoritative totals.
- Complete staging/browser verification and environment-wide test/typecheck gates before moving the issue to `completed`.
