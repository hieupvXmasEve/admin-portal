# 12 — Migrate DNG active và cutover production

**Status:** ready-for-human
**Portal impact:** student

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Đưa toàn bộ active DNG population vào exact reservation/receipt model và thực hiện atomic production cutover. Migration chỉ backfill khi payer, provider rail/campus, fee type, Payable Line/installment và provider identity có exact evidence; không infer bằng amount. Operational tooling chạy dry-run, phân loại repair/cancel/paid bridge/unknown, chứng minh shadow gate, provider lookup, performance và deployment compatibility trước khi bật switch.

## Acceptance criteria

- [x] Dry-run inventory 100% active DNG và phân loại exact-link, unknown, paid-unbridged, campus/rail conflict và unmatched receipt.
- [x] Backfill idempotent, không push provider và không đoán target từ amount/fee type/timing.
- [ ] Zero unresolved active requests và zero paid-but-unbridged attributable receipts trước cutover; unsafe rows giữ exception và block scope.
- [x] Active slot uniqueness đúng provider rail/campus + Billing Account + fee type, gồm campus transfer cases.
- [ ] Shadow-check bao phủ active DNG + Billing Accounts mutated 90 ngày và đạt zero unexplained mismatch trong 7 ngày liên tiếp tại same snapshot/version.
- [ ] Provider `ItemId` lookup/manual confirmation, pay-one/pay-all portal proof, race/idempotency tests và production performance budgets đều pass.
- [ ] Deployment giữ schema/workers/webhooks/reconciliation backward/forward compatible; kill switch dừng new collection nhưng không dừng receipt capture/bridge/reconcile/cancellation completion.

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/FinanceDngCutoverCommandTest.php tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php tests/Feature/Finance/StudentFinanceDngAccessTest.php tests/Feature/Finance/Dng/DngPaymentServiceTest.php tests/Feature/Finance/Dng/CaptureDngProviderReceiptActionTest.php` — **25 passed (185 assertions)**.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `finance:dng-cutover` is read-only by default; `--backfill` writes only exact reservation metadata/targets and is idempotent. It returns non-zero when inventory is incomplete, unsafe, limited, or has unresolved classifications.
- `FINANCE_DNG_COLLECTION_MODE=off` blocks new collection, reservation, batch create, and student payment-access generation; receipt capture/bridge/reconciliation code paths do not use this guard.
- `./scripts/dev.sh npm run type-check` — blocked by Node heap OOM (exit 134), with no changed frontend files.
- `./scripts/dev.sh test` — exit 255 without diagnostics from the wrapper; focused DNG regression remains green.

## Comments

### 2026-07-12 — Implementation and review

- Added `finance:dng-cutover` inventory/backfill tooling with exact-link resolution from existing FinanceCharge/pivot/installment evidence only; it never infers targets from amount, fee type, or timing and never calls DNG.
- Added action classifications for repair, cancel/reconcile, and paid bridge, plus unresolved/unknown receipt counts and provider-rail/campus/account/fee active-slot conflict detection.
- Added the `legacy` / `cutover` / `off` collection mode. `cutover` blocks legacy create/batch paths; `off` is fail-closed for new collection while receipt capture remains available.
- Review found no remaining standards blocker after moving inventory to a Query and backfill to an Action. Remaining handoff gates are provider lookup/manual evidence, seven consecutive same-snapshot shadow-clean days, portal pay-one/pay-all proof, production performance budgets, deployment compatibility, and a live zero-unresolved inventory.

## Blocked by

- [06 — Xử lý DNG receipt lệch hoặc chưa khớp](06-mismatched-and-unmatched-dng-receipts.md)
- [07 — Reconcile credit và installment dưới collection hold](07-credit-installment-reconciliation-under-hold.md)
- [08 — Hủy collection mà không void obligation](08-cancel-collection-without-voiding-obligation.md)
- [09 — Hủy obligation qua Finance Cancellation Operation](09-finance-cancellation-operation.md)
- [10 — Xử lý Còn dư sau paid obligation void](10-paid-void-surplus-disposition.md)
- [11 — Sinh viên thanh toán một fee type hoặc tất cả](11-student-pay-one-or-all-fee-types.md)
