# 13 — Chuyển invoice paid cache thành cash-only

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Đổi semantic `cached_paid_amount` thành matched cash only end-to-end. Writer mới lấy cash component từ canonical Settlement Position, credit tiếp tục live-derived riêng. Một idempotent rebuild chuyển dữ liệu cũ, consumer deployment không bao giờ quan sát mixed cash-plus-credit/cash-only meaning, và INV-2 trở thành parity guard cho semantic mới.

## Acceptance criteria

- [x] Writer lưu cash applied only; discount và credit không được fold vào paid cache.
- [x] Rebuild command có dry-run, row counts/mismatch evidence, idempotent rerun và scoped execution.
- [ ] Reader/deployment order không để consumer mới đọc cache cũ hoặc consumer cũ hiểu cache mới sai nghĩa. **Remaining:** production cutover gate và consumer deployment sequencing chưa nằm trong slice này.
- [x] Invoice payment status vẫn xét cash + credit đúng canonical settlement mà không relabel credit thành cash.
- [x] INV-2 và cache-drift tooling được cập nhật cho cash-only meaning và focused rebuild đạt zero unexplained drift sau rerun.
- [x] Reversal, paid void và credit apply/reverse cập nhật/rebuild cache đúng.
- [ ] Rollback/roll-forward procedure không khôi phục cash-plus-credit semantic. **Remaining:** cần human-owned production runbook/cutover decision.

## Blocked by

- [01 — Đọc Settlement Position hiện tại cho một khoản phí](../../wave-0-foundation/issues/01-current-payable-settlement-position.md)

## Comments

### 2026-07-12 — Implementation

- `SettlementService::recalculateInvoiceSnapshot()` now reads `SettlementPositionReader::forInvoice()` and writes `cached_paid_amount` from matched cash only. Credit remains separate for status/remaining; credit-only paid invoices do not receive a cash `cached_paid_at`.
- `finance:rebuild-invoice-snapshots` now supports `--dry-run`, `--semester`, `--campus`, row counts, per-invoice drift evidence, invalid-position reporting, and idempotent rerun verification. Invalid canonical positions fail closed.
- `INV-2`, audit warnings, audit graph drift checks, and Student 360 cache-drift signals now use cash-only parity. Paid-void clearing and credit reversal recalculate paths were covered.
- Focused verification: `./scripts/dev.sh test tests/Feature/Finance/InvoiceCacheRebuildTest.php tests/Feature/Finance/CreditInstallmentReconciliationUnderHoldTest.php` — 12 passed / 48 assertions. `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed; `git diff --check` passed.
- Broader Finance regression was attempted: 717 passed / 109 failed. Failures include pre-existing unrelated baseline issues such as `PaymentService.php` referencing undefined `$allowHeldTargets`, CSRF/fixture failures, and other dirty-worktree suite drift. Full wrapper `./scripts/dev.sh test` exited 255 without output.
- Status remains `ready-for-human` because production deployment sequencing and rollback/roll-forward procedure are not proven by this code slice. Parent PRD and sibling issue states were not changed.
