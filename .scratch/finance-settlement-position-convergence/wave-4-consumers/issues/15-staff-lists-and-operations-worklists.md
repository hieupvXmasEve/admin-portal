# 15 — Chuyển staff lists và operations worklists

**Status:** ready-for-human
**Portal impact:** none

## Parent

[Finance Settlement Position Convergence](../../PRD.md)

## What to build

Chuyển staff-facing current-balance lists, Finance operations worklists/cockpit cards và actionability decisions sang batch Settlement Position. Consumer chọn business scope/filter nhưng không tính balance. Chỉ công việc thực sự actionable được hiển thị; invalid/mismatched/unmatched/unknown cases đi vào Exceptions với `Cần kiểm tra`, không bị trộn vào normal collection queue.

## Acceptance criteria

- [x] Inventory xác định tất cả staff list/worklist/current KPI consumers trong slice và xóa local arithmetic ở từng consumer.
- [x] Batch reader cung cấp values/breakdown/issues với parity single read và không N+1.
- [x] Paid/historical DNG không bị trình bày như current collection work.
- [ ] Invalid/held/unknown scopes không tạo push/allocate action và được route tới Exceptions.
- [x] Approved staff labels `Đã thu`, `Còn phải thu`, `Còn dư`, `Cần kiểm tra` giữ đúng semantic.
- [x] Authorization/campus scoping được áp trước khi đọc/batch aggregate.
- [ ] Representative production-volume list tests đạt query-count và p95 budgets của issue.

## Blocked by

- [02 — Tổng hợp Settlement Position theo business scope](../../wave-0-foundation/issues/02-aggregate-batch-as-of-settlement-position.md)
- [06 — Xử lý DNG receipt lệch hoặc chưa khớp](../../wave-1-dng-safety/issues/06-mismatched-and-unmatched-dng-receipts.md)
- [13 — Chuyển invoice paid cache thành cash-only](../../wave-2-cash-cache/issues/13-cash-only-invoice-paid-cache.md)

## Verification

- Added `SettlementPositionWorklistReader` and `SettlementPositionWorklistPresenter` as the shared staff-list seam. `ListDngWorklistQuery`, `ListSettlementWorklistQuery`, `GetBillingDashboardStatsQuery`, `GetBillingDashboardStudentsQuery`, and the Finance Cockpit KPI/queue now consume canonical batch positions rather than local settlement formulas.
- Campus/semester/student filters run before exact payable-line ids are sent to the Finance batch reader. Invalid or missing payable-line scopes are excluded from normal collection rows, returned under `exceptions`, and expose `Cần kiểm tra` with no push/allocate amount. Held/unknown DNG-state coverage remains a human verification item.
- DNG rows cap installment-aware next-push amounts to canonical remaining collectible; the cockpit exposes a `Settlement cần kiểm tra` queue from the canonical KPI stats.
- Staff UI rows/cards now show canonical status fields and nullable money for invalid positions; no trusted amount is rendered for `Cần kiểm tra`.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/SettlementPosition/StaffWorklistConsumersTest.php` — passed (2 tests, 10 assertions).
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh npm exec eslint -- resources/js/pages/Finance/Operations/Settlement.vue resources/js/pages/Finance/Operations/DngWorklist.vue resources/js/pages/Finance/Operations/Dashboard.vue` — passed.
- `./scripts/dev.sh npm exec prettier --check resources/js/pages/Finance/Operations/Settlement.vue resources/js/pages/Finance/Operations/DngWorklist.vue resources/js/pages/Finance/Operations/Dashboard.vue` — passed.
- Existing aggregate reader tests passed in the combined focused run; the performance baseline file could not be used in that combined invocation because its included helper expects Pest file-local `$this->invoice` setup (`Undefined property ...::$invoice`).
- Full `./scripts/dev.sh npm run type-check` was attempted with a 45-second timeout and produced no diagnostics before timing out. The older DNG/Settlement/Billing tests still use legacy fixtures without canonical obligation/currency/payable-line data, so their old arithmetic assertions are not valid acceptance evidence for this cutover.

## Comments

### 2026-07-12 — Wave-4 consumer implementation

- Scope intentionally remains `Portal impact: none`; only staff-facing Finance web consumers and their backend queries were changed.
- Parent PRD and sibling issue states were not changed.
