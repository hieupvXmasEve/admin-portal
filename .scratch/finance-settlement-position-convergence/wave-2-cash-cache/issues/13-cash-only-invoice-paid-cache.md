# 13 — Chuyển invoice paid cache thành cash-only

**Status:** completed
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Đổi semantic `cached_paid_amount` thành matched cash only end-to-end. Writer mới lấy cash component từ canonical Settlement Position, credit tiếp tục live-derived riêng. Một idempotent rebuild chuyển dữ liệu cũ, consumer deployment không bao giờ quan sát mixed cash-plus-credit/cash-only meaning, và INV-2 trở thành parity guard cho semantic mới.

## Acceptance criteria

- [x] Writer lưu cash applied only; discount và credit không được fold vào paid cache.
- [x] Rebuild command có dry-run, row counts/mismatch evidence, idempotent rerun và scoped execution.
- [x] Reader/deployment order không để consumer mới đọc cache cũ hoặc consumer cũ hiểu cache mới sai nghĩa. Human staging verification passed.
- [x] Invoice payment status vẫn xét cash + credit đúng canonical settlement mà không relabel credit thành cash.
- [x] INV-2 và cache-drift tooling được cập nhật cho cash-only meaning và focused rebuild đạt zero unexplained drift sau rerun.
- [x] Reversal, paid void và credit apply/reverse cập nhật/rebuild cache đúng.
- [x] Rollback/roll-forward procedure không khôi phục cash-plus-credit semantic. Human staging verification passed.

## Blocked by

- [01 — Đọc Settlement Position hiện tại cho một khoản phí](../../wave-0-foundation/issues/01-current-payable-settlement-position.md)

## Comments

### 2026-07-12 — Implementation

- `SettlementService::recalculateInvoiceSnapshot()` now reads `SettlementPositionReader::forInvoice()` and writes `cached_paid_amount` from matched cash only. Credit remains separate for status/remaining; credit-only paid invoices do not receive a cash `cached_paid_at`.
- `finance:rebuild-invoice-snapshots` now supports `--dry-run`, `--semester`, `--campus`, row counts, per-invoice drift evidence, invalid-position reporting, and idempotent rerun verification. Invalid canonical positions fail closed.
- `INV-2`, audit warnings, audit graph drift checks, and Student 360 cache-drift signals now use cash-only parity. Paid-void clearing and credit reversal recalculate paths were covered.
- Focused verification: `./scripts/dev.sh test tests/Feature/Finance/InvoiceCacheRebuildTest.php tests/Feature/Finance/CreditInstallmentReconciliationUnderHoldTest.php` — 12 passed / 48 assertions. `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed; `git diff --check` passed.
- Broader Finance regression was attempted: 717 passed / 109 failed. Failures include pre-existing unrelated baseline issues such as `PaymentService.php` referencing undefined `$allowHeldTargets`, CSRF/fixture failures, and other dirty-worktree suite drift. Full wrapper `./scripts/dev.sh test` exited 255 without output.
- Implementation status was initially `ready-for-human`; production deployment sequencing and rollback/roll-forward were completed through the human staging verification recorded below. Parent PRD and sibling issue states were not changed.

### 2026-07-12 — Local scoped rebuild verification

- Full local dry-run was intentionally not written: `556` scanned, `0` drifted, `259` invalid, `0` failed. Invalid rows are existing fixture/data-shape issues such as inactive payable lines, inactive finance charges, and missing currency.
- Selected valid scope `campus=2, semester=2`: `37/37` invoices had valid canonical Settlement Positions.
- Scoped dry-run before rebuild: `37` scanned, `0` drifted, `0` invalid, `0` failed.
- Scoped rebuild: `37` scanned, `37` rebuilt, `0` invalid, `0` failed.
- Scoped dry-run after rebuild: `37` scanned, `0` drifted, `0` invalid, `0` failed.
- Full `finance:audit-invariants --sample` still reports existing out-of-scope findings: `INV-2=1` (invoice `1471`), `INV-6=29`, `INV-13=1`. No full-dataset clean sign-off claimed.
- Human verification was pending at the time of this local rebuild note; see the completion entry below.

### 2026-07-12 — Human verification complete

- Human verified the required staging UI scenarios: cash-only, discount versus cash, outstanding invoice, Student 360 refresh, mixed cash/credit, credit-only, credit reversal, and paid-void behavior.
- All scenarios passed. Deployment sequencing and rollback/roll-forward behavior were also accepted.
- Issue status moved to `completed`.
