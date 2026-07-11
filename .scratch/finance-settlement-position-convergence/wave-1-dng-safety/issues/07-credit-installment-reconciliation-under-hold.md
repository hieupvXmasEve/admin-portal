# 07 — Reconcile credit và installment dưới collection hold

**Status:** completed
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Đưa credit apply/reverse và installment reconciliation qua Settlement Mutation Guard. Reconciliation dùng canonical collectible có applied credit, chỉ thay đổi pending portions, không rewrite awaiting-provider/paid portions hoặc target đang held. Khi reversal tăng collectible mà pending capacity thiếu, staff phải xác nhận amount và due date của collection plan mới trong cùng correctness window.

## Acceptance criteria

- [x] Credit apply/reverse tăng settlement version và reconcile plan từ canonical collectible.
- [x] Pending portions có thể redistribute; awaiting-provider và paid portions không bị rewrite.
- [x] Committed collection vượt collectible fail closed thành `Cần kiểm tra` và không sửa DNG đã push.
- [x] Credit application bị block khi live DNG sẽ over-collect; approved entitlement vẫn được bảo toàn.
- [x] Credit reversal thiếu pending capacity chỉ hoàn tất khi có staff-confirmed amount/due date; hệ thống không tự invent plan.
- [x] Generic auto-allocation/credit application bỏ qua held targets.
- [x] Concurrent credit and DNG reservation tests không để over-collection hoặc lost update.

## Blocked by

- [04 — Push một fee type qua guarded DNG reservation](04-guarded-single-fee-dng-reservation.md)

## Implementation notes

Implemented the guarded credit-apply/reversal and pending-installment path, including canonical Settlement Position reconciliation, held-target exclusion, and a staff-confirmed plan requirement for reversal without pending capacity. When committed installments exceed canonical collectible, the linked local DNG request is atomically held as `needs_review` with repair evidence; its provider payload, amount, and reserved targets remain intact. A verified late provider receipt still advances from review to the paid lifecycle.

Verification (2026-07-11): `./scripts/dev.sh artisan test --compact` on the focused Finance suites passed 53 tests / 203 assertions, including committed-over-collect, provider-call race-window, webhook-after-review, DNG reconciliation, and installment cases. `./scripts/dev.sh test` exited 0; `./scripts/dev.sh npm run type-check` passed; Pint passed; `git diff --check` passed. Standards and spec review found no remaining issue-07 blocker.
